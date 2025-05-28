<?php
// Autenticación de Admin
function isAdmin() {
    return $_SESSION['user_tipo'] === 'admin';
}

// Obtener préstamos pendientes
function getPendingLoans() {
    global $pdo;
    $sql = "SELECT p.*, u.nombre as nombre_cliente, pl.nombre_plan 
            FROM prestamos p
            JOIN usuarios u ON p.id_cliente = u.id
            JOIN planes_interes pl ON p.id_plan = pl.id
            WHERE p.estado = 'pendiente'";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// Cambiar estado de usuario
function toggleUserStatus($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE usuarios 
                          SET estatus = IF(estatus='activo', 'inactivo', 'activo')
                          WHERE id = ?");
    return $stmt->execute([$user_id]);
}

// Generar reporte de morosidad
function generateDelinquencyReport() {
    global $pdo;
    $sql = "SELECT u.nombre, p.* 
            FROM prestamos p
            JOIN usuarios u ON p.id_cliente = u.id
            WHERE p.estado = 'moroso'";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}


// includes/admin_functions.php

// Obtener total de usuarios por tipo
function getTotalUsers($tipo = null) {
    global $pdo;
    
    $sql = "SELECT COUNT(*) FROM usuarios";
    if($tipo) $sql .= " WHERE tipo = ?";
    
    $stmt = $pdo->prepare($sql);
    $tipo ? $stmt->execute([$tipo]) : $stmt->execute();
    
    return $stmt->fetchColumn();
}

// Obtener préstamos por estado
function getLoansByStatus($estado) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM prestamos WHERE estado = ?");
    $stmt->execute([$estado]);
    
    return $stmt->fetchColumn();
}

// Obtener monto total aprobado
function getTotalApprovedAmount() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT SUM(monto_aprobado) FROM prestamos WHERE estado = 'aprobado'");
    return $stmt->fetchColumn() ?: 0;
}

// Calcular tasa de morosidad
function getDelinquencyRate() {
    global $pdo;
    
    $total = getLoansByStatus('aprobado') + getLoansByStatus('liquidado');
    if($total == 0) return 0;
    
    $morosos = getLoansByStatus('moroso');
    return round(($morosos / $total) * 100, 2);
}

// Distribución de préstamos para gráfico
function getLoanDistribution() {
    global $pdo;
    $sql = "SELECT estado, COUNT(*) as cantidad FROM prestamos GROUP BY estado";
    $stmt = $pdo->query($sql);
    
    $result = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Asegurar que todos los estados estén presentes
    $estados = ['aprobado', 'pendiente', 'rechazado', 'moroso'];
    $data = [];
    
    foreach ($estados as $estado) {
        $data[] = $result[$estado] ?? 0;
    }

    return $data;
}

?>