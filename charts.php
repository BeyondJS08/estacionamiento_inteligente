<?php
// charts.php - Return aggregated data for Chart.js dashboard widgets
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/db.php';

try {
    // 1. Distance history: last 50 readings with their timestamp
    $history_stmt = $pdo->query("SELECT fecha_hora, distance FROM parking_logs ORDER BY id ASC LIMIT 50");
    $history_raw = $history_stmt->fetchAll();
    $distance_history = array_map(function ($row) {
        return [
            'hora' => date('H:i', strtotime($row['fecha_hora'])),
            'distancia' => floatval($row['distance'])
        ];
    }, $history_raw);

    // 2. Estado distribution
    $estado_stmt = $pdo->query("SELECT estado, COUNT(*) AS total FROM parking_logs GROUP BY estado");
    $estado_distribution = [];
    foreach ($estado_stmt->fetchAll() as $row) {
        $estado_distribution[$row['estado']] = intval($row['total']);
    }
    // Ensure all expected keys exist
    foreach (['Libre', 'Acercandose', 'Ocupado'] as $key) {
        if (!isset($estado_distribution[$key])) {
            $estado_distribution[$key] = 0;
        }
    }

    // 3. Buzzer frequency distribution
    $buzzer_stmt = $pdo->query("SELECT frecuencia_buzzer, COUNT(*) AS total FROM parking_logs GROUP BY frecuencia_buzzer");
    $buzzer_distribution = [];
    foreach ($buzzer_stmt->fetchAll() as $row) {
        $label = $row['frecuencia_buzzer'] == 0 ? 'Inactivo' : $row['frecuencia_buzzer'] . ' Hz';
        $buzzer_distribution[$label] = intval($row['total']);
    }

    // 4. Occupancy percentage
    $total_stmt = $pdo->query("SELECT COUNT(*) FROM parking_logs");
    $total_records = intval($total_stmt->fetchColumn());

    $ocupados_stmt = $pdo->query("SELECT COUNT(*) FROM parking_logs WHERE estado = 'Ocupado'");
    $ocupados = intval($ocupados_stmt->fetchColumn());

    $ocupacion = [
        'total' => $total_records,
        'ocupados' => $ocupados,
        'porcentaje' => $total_records > 0 ? round(($ocupados / $total_records) * 100, 1) : 0
    ];

    echo json_encode([
        "status" => "success",
        "distance_history" => $distance_history,
        "estado_distribution" => $estado_distribution,
        "buzzer_distribution" => $buzzer_distribution,
        "ocupacion" => $ocupacion
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Chart query failed: " . $e->getMessage()]);
}
?>
