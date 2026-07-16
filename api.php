<?php
// api.php - API endpoint for ESP32 WiFi uploads (supports both POST and GET)
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/db.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$distance = null;
$estado = "";
$frecuencia_buzzer = 0;

// 1. Try reading raw JSON body (typical for POST requests)
$json_data = json_decode(file_get_contents("php://input"), true);
if ($json_data) {
    $distance = isset($json_data['distance']) ? floatval($json_data['distance']) : null;
    $estado = isset($json_data['estado']) ? trim($json_data['estado']) : "";
    $frecuencia_buzzer = isset($json_data['frecuencia_buzzer']) ? intval($json_data['frecuencia_buzzer']) : 0;
} else {
    // 2. Fallback to $_REQUEST (handles standard POST forms and GET query parameters)
    $distance = isset($_REQUEST['distance']) ? floatval($_REQUEST['distance']) : null;
    $estado = isset($_REQUEST['estado']) ? trim($_REQUEST['estado']) : "";
    $frecuencia_buzzer = isset($_REQUEST['frecuencia_buzzer']) ? intval($_REQUEST['frecuencia_buzzer']) : 0;
}

// Validate required fields
if ($distance === null || empty($estado)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => "Missing required fields (distance, estado). You can send them via POST (JSON/Form) or GET parameters."
    ]);
    exit;
}

// Map the alert based on the status or distance
$alerta = "OK";
if ($estado === "Acercandose") {
    $alerta = "PRECAUCIÓN";
} elseif ($estado === "Ocupado") {
    $alerta = "CRÍTICO";
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO parking_logs (distance, estado, frecuencia_buzzer, alerta) 
        VALUES (:distance, :estado, :frecuencia_buzzer, :alerta)
    ");
    $stmt->execute([
        ':distance' => $distance,
        ':estado' => $estado,
        ':frecuencia_buzzer' => $frecuencia_buzzer,
        ':alerta' => $alerta
    ]);
    
    echo json_encode([
        "status" => "success",
        "message" => "Data saved successfully",
        "data" => [
            "id" => $pdo->lastInsertId(),
            "distance" => $distance,
            "estado" => $estado,
            "frecuencia_buzzer" => $frecuencia_buzzer,
            "alerta" => $alerta
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to save data: " . $e->getMessage()]);
}
?>
