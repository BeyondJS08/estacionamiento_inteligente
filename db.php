<?php
// db.php - Database connection and initialization

$db_file = __DIR__ . '/parking.db';

try {
    // Create (or open) the SQLite database
    $pdo = new PDO("sqlite:" . $db_file);
    
    // Set error mode to exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Create the table if it does not exist
    $create_table_sql = "
    CREATE TABLE IF NOT EXISTS parking_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        distance REAL NOT NULL,
        estado TEXT NOT NULL,
        frecuencia_buzzer INTEGER NOT NULL,
        alerta TEXT NOT NULL,
        fecha_hora DATETIME DEFAULT (datetime('now', 'localtime'))
    );
    ";
    
    $pdo->exec($create_table_sql);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
