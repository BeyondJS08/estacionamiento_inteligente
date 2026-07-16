<?php
// data.php - Return dashboard statistics and logs as JSON for real-time updates
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/db.php';

try {
    // 1. Fetch total count
    $total_query = $pdo->query("SELECT COUNT(*) FROM parking_logs");
    $total_records = $total_query->fetchColumn();

    // 2. Fetch latest reading
    $latest_query = $pdo->query("SELECT * FROM parking_logs ORDER BY id DESC LIMIT 1");
    $latest_reading = $latest_query->fetch();

    // 3. Fetch average distance
    $avg_query = $pdo->query("SELECT AVG(distance) FROM parking_logs");
    $avg_distance = round($avg_query->fetchColumn(), 1);

    // 4. Fetch warn alarms
    $warn_query = $pdo->query("SELECT COUNT(*) FROM parking_logs WHERE estado = 'Acercandose'");
    $warn_count = $warn_query->fetchColumn();

    // 5. Fetch critical alarms
    $critical_query = $pdo->query("SELECT COUNT(*) FROM parking_logs WHERE alerta = 'CRÍTICO'");
    $critical_count = $critical_query->fetchColumn();

    // 6. Fetch occupied (Ocupado) count
    $ocupados_query = $pdo->query("SELECT COUNT(*) FROM parking_logs WHERE estado = 'Ocupado'");
    $ocupados_count = $ocupados_query->fetchColumn();
    
    // 6. Fetch logs (limit to 100 for performance, or filter by state)
    $filter_status = isset($_GET['estado']) ? $_GET['estado'] : 'all';
    
    if ($filter_status !== 'all') {
        $stmt = $pdo->prepare("SELECT * FROM parking_logs WHERE estado = :estado ORDER BY id DESC LIMIT 5");
        $stmt->execute([':estado' => $filter_status]);
        $logs = $stmt->fetchAll();
    } else {
        $logs = $pdo->query("SELECT * FROM parking_logs ORDER BY id DESC LIMIT 5")->fetchAll();
    }

    echo json_encode([
        "status" => "success",
        "total_records" => intval($total_records),
        "avg_distance" => floatval($avg_distance),
        "warn_count" => intval($warn_count),
        "critical_count" => intval($critical_count),
        "ocupados_count" => intval($ocupados_count),
        "latest_reading" => $latest_reading ? [
            "id" => intval($latest_reading['id']),
            "distance" => floatval($latest_reading['distance']),
            "estado" => $latest_reading['estado'],
            "frecuencia_buzzer" => intval($latest_reading['frecuencia_buzzer']),
            "alerta" => $latest_reading['alerta'],
            "fecha_hora" => $latest_reading['fecha_hora']
        ] : null,
        "logs" => array_map(function($log) {
            return [
                "id" => intval($log['id']),
                "distance" => floatval($log['distance']),
                "estado" => $log['estado'],
                "frecuencia_buzzer" => intval($log['frecuencia_buzzer']),
                "alerta" => $log['alerta'],
                "fecha_hora" => $log['fecha_hora']
            ];
        }, $logs)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database query failed: " . $e->getMessage()]);
}
?>
