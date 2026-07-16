<?php
// seed.php - Seed database with ~150 realistic sensor data points simulating parking events
require_once __DIR__ . '/db.php';

try {
    // Clear any existing logs to start fresh
    $pdo->exec("DELETE FROM parking_logs");
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name='parking_logs'"); // Reset auto-increment
    
    $total_points = 150;
    $now = time();
    
    // We will generate data going back in time, e.g., one reading every 10 seconds.
    // 150 readings * 10 seconds = 1500 seconds (~25 minutes of parking activity)
    $data_points = [];
    
    // Simulating sequence: 
    // - Phase 1 (0-30): Empty spot (distance 40cm - 120cm, Libre)
    // - Phase 2 (30-45): Car entering (distance 25cm down to 10cm, Acercandose)
    // - Phase 3 (45-90): Car parked (distance 5cm - 8cm, Ocupado)
    // - Phase 4 (90-105): Car leaving (distance 10cm up to 25cm, Acercandose)
    // - Phase 5 (105-150): Empty spot again (distance 50cm - 110cm, Libre)
    
    for ($i = 0; $i < $total_points; $i++) {
        // Calculate timestamp back in time (newest data has current time)
        $timestamp = $now - (($total_points - 1 - $i) * 10);
        $fecha_hora = date("Y-m-d H:i:s", $timestamp);
        
        $distance = 100.0;
        $estado = "Libre";
        $frecuencia_buzzer = 0;
        $alerta = "OK";
        
        if ($i < 30) {
            // Libre (Empty)
            $distance = 80.0 + rand(-15, 25) + (rand(0, 9) / 10.0);
        } elseif ($i >= 30 && $i < 45) {
            // Acercandose (Car entering)
            // Interpolate distance from ~25cm down to ~10cm
            $progress = ($i - 30) / 15.0; // 0 to 1
            $distance = 25.0 - ($progress * 15.0) + (rand(-10, 10) / 10.0);
            $estado = "Acercandose";
            $frecuencia_buzzer = 1000;
            $alerta = "PRECAUCIÓN";
        } elseif ($i >= 45 && $i < 90) {
            // Ocupado (Car parked)
            $distance = 6.0 + (rand(-10, 15) / 10.0); // around 5-7.5 cm
            $estado = "Ocupado";
            $frecuencia_buzzer = 2000;
            $alerta = "CRÍTICO";
        } elseif ($i >= 90 && $i < 105) {
            // Acercandose (Car leaving)
            // Interpolate distance from ~10cm up to ~25cm
            $progress = ($i - 90) / 15.0; // 0 to 1
            $distance = 10.0 + ($progress * 15.0) + (rand(-10, 10) / 10.0);
            $estado = "Acercandose";
            $frecuencia_buzzer = 1000;
            $alerta = "PRECAUCIÓN";
        } else {
            // Libre (Empty again)
            $distance = 95.0 + rand(-20, 20) + (rand(0, 9) / 10.0);
        }
        
        // Ensure constraints match rules
        // In the Arduino sketch:
        // distance > 20.0 => Libre, 0 Hz, OK
        // distance >= 10.0 && distance <= 20.0 => Acercandose, 1000 Hz, PRECAUCIÓN
        // distance < 10.0 => Ocupado, 2000 Hz, CRÍTICO
        if ($distance > 20.0) {
            $estado = "Libre";
            $frecuencia_buzzer = 0;
            $alerta = "OK";
        } elseif ($distance >= 10.0 && $distance <= 20.0) {
            $estado = "Acercandose";
            $frecuencia_buzzer = 1000;
            $alerta = "PRECAUCIÓN";
        } else {
            $estado = "Ocupado";
            $frecuencia_buzzer = 2000;
            $alerta = "CRÍTICO";
        }
        
        $data_points[] = [
            'distance' => round($distance, 1),
            'estado' => $estado,
            'frecuencia_buzzer' => $frecuencia_buzzer,
            'alerta' => $alerta,
            'fecha_hora' => $fecha_hora
        ];
    }
    
    // Insert all data
    $stmt = $pdo->prepare("
        INSERT INTO parking_logs (distance, estado, frecuencia_buzzer, alerta, fecha_hora)
        VALUES (:distance, :estado, :frecuencia_buzzer, :alerta, :fecha_hora)
    ");
    
    $pdo->beginTransaction();
    foreach ($data_points as $point) {
        $stmt->execute($point);
    }
    $pdo->commit();
    
    echo json_encode(["status" => "success", "message" => "Successfully seeded 150 parking sensor log entries."]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["status" => "error", "message" => "Seeding failed: " . $e->getMessage()]);
}
?>
