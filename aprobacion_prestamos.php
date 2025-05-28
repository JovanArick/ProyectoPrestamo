<?php
require_once '../includes/db.php';
require_once 'admin_functions.php';
require_once '../includes/auth.php';
require_once 'notificaciones_prestamo.php';
verificarSesionAdmin();

$alerta = null;

// Obtener préstamos pendientes
$prestamos = getPendingLoans();

// Procesar acciones del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $id = $_POST['id'];
    $accion = $_POST['accion'];

    $stmt = $pdo->prepare("SELECT * FROM prestamos WHERE id = ?");
    $stmt->execute([$id]);
    $prestamo = $stmt->fetch(PDO::FETCH_ASSOC);
    $id_cliente = $prestamo['id_cliente'];

    if ($accion === 'aprobar') {
        $monto = $prestamo['monto_solicitado'];
        $plazo = $prestamo['plazo'];

        if ($monto <= 0 || $plazo <= 0) {
            $alerta = "⚠️ El préstamo no tiene un monto o plazo válido.";
        } else {
            // Aprobar préstamo y asignar monto aprobado
            $pdo->prepare("UPDATE prestamos 
                SET estado = 'aprobado', 
                    fecha_aprobacion = NOW(), 
                    monto_aprobado = monto_solicitado 
                WHERE id = ?")
                ->execute([$id]);

            // Generar cuotas
            $interes = $prestamo['tasa_final'] / 100;
            $cuota_base = $monto / $plazo;
            $interes_mensual = ($monto * $interes) / $plazo;
            $saldo = $monto;
            $hoy = new DateTime();

            $stmtInsert = $pdo->prepare("INSERT INTO esquema_pagos (id_prestamo, numero_cuota, fecha_vencimiento, capital, interes, saldo_restante) VALUES (?, ?, ?, ?, ?, ?)");

            for ($i = 1; $i <= $plazo; $i++) {
                $fecha = $hoy->modify('+1 month')->format('Y-m-d');
                $saldo -= $cuota_base;
                $stmtInsert->execute([
                    $id,
                    $i,
                    $fecha,
                    $cuota_base,
                    $interes_mensual,
                    max($saldo, 0)
                ]);
            }

            enviarNotificacionPrestamo($pdo, $id_cliente, 'Préstamo aprobado', 'Tu solicitud de préstamo ha sido aprobada.');
            header("Location: aprobacion_prestamos.php");
            exit;
        }

    } elseif ($accion === 'rechazar') {
        $motivo = $_POST['motivo'] ?? 'Sin motivo especificado';
        $pdo->prepare("UPDATE prestamos SET estado = 'rechazado', motivo_rechazo = ? WHERE id = ?")
            ->execute([$motivo, $id]);

        enviarNotificacionPrestamo($pdo, $id_cliente, 'Préstamo rechazado', "Tu solicitud ha sido rechazada. Motivo: $motivo");
        header("Location: aprobacion_prestamos.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Aprobar Préstamos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">← Volver al Dashboard</a>
        <a href="logout.php" class="btn btn-danger">Cerrar Sesión</a>
    </div>
</nav>

<div class="container mt-4">
    <h2>Solicitudes Pendientes de Préstamo</h2>

    <?php if ($alerta): ?>
    <div class="alert alert-warning text-center">
        <?= htmlspecialchars($alerta) ?>
    </div>
    <?php endif; ?>

    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Cliente</th>
                <th>Monto</th>
                <th>Plan</th>
                <th>Plazo</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($prestamos as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['nombre_cliente']) ?></td>
                <td>$<?= number_format($p['monto_solicitado'], 2) ?></td>
                <td><?= htmlspecialchars($p['nombre_plan']) ?></td>
                <td><?= $p['plazo'] ?> meses</td>
                <td>
                    <form method="POST" class="d-flex gap-2">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button name="accion" value="aprobar" class="btn btn-success btn-sm">Aprobar</button>
                        <button name="accion" value="rechazar" class="btn btn-danger btn-sm" onclick="return confirm('¿Rechazar solicitud?')">Rechazar</button>
                        <input type="text" name="motivo" placeholder="Motivo rechazo" class="form-control form-control-sm" required>
                    </form>
                </td>
            </tr>
            <?php endforeach ?>
            <?php if (empty($prestamos)): ?>
            <tr>
                <td colspan="5" class="text-center">No hay solicitudes pendientes.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
