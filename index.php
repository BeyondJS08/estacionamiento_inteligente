<?php
// index.php - Smart Parking System Dashboard with Real-Time Updates
require_once __DIR__ . '/db.php';

// Initial fetch for server-side rendering (SSR) on first page load
try {
    $total_query = $pdo->query("SELECT COUNT(*) FROM parking_logs");
    $total_records = $total_query->fetchColumn();

    $latest_query = $pdo->query("SELECT * FROM parking_logs ORDER BY id DESC LIMIT 1");
    $latest_reading = $latest_query->fetch();

    $avg_query = $pdo->query("SELECT AVG(distance) FROM parking_logs");
    $avg_distance = round($avg_query->fetchColumn(), 1);

    $warn_query = $pdo->query("SELECT COUNT(*) FROM parking_logs WHERE estado = 'Acercandose'");
    $warn_count = $warn_query->fetchColumn();

    $critical_query = $pdo->query("SELECT COUNT(*) FROM parking_logs WHERE alerta = 'CRÍTICO'");
    $critical_count = $critical_query->fetchColumn();
    
    $filter_status = isset($_GET['estado']) ? $_GET['estado'] : 'all';
    
    if ($filter_status !== 'all') {
        $stmt = $pdo->prepare("SELECT * FROM parking_logs WHERE estado = :estado ORDER BY id DESC LIMIT 5");
        $stmt->execute([':estado' => $filter_status]);
        $logs = $stmt->fetchAll();
    } else {
        $logs = $pdo->query("SELECT * FROM parking_logs ORDER BY id DESC LIMIT 5")->fetchAll();
    }
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estacionamiento Inteligente</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    
    <style>
        :root {
            --bg-primary: #0a0f1d;
            --bg-secondary: #131c31;
            --bg-card: #1b263e;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --accent-primary: #3b82f6;
            --accent-hover: #2563eb;
            
            --color-success: #10b981;
            --color-warning: #f59e0b;
            --color-danger: #ef4444;
            
            --glow-success: rgba(16, 185, 129, 0.2);
            --glow-warning: rgba(245, 158, 11, 0.2);
            --glow-danger: rgba(239, 68, 68, 0.2);
            
            --border-color: rgba(255, 255, 255, 0.08);
            --border-glow: rgba(59, 130, 246, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            padding-bottom: 3rem;
            background-image: 
                radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.05) 0px, transparent 50%);
            background-attachment: fixed;
        }

        header {
            background-color: rgba(19, 28, 49, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header-logo {
            background: linear-gradient(135deg, var(--accent-primary), #6366f1);
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
        }

        header h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ffffff 60%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            background-color: rgba(255, 255, 255, 0.04);
            padding: 0.5rem 1rem;
            border-radius: 99px;
            border: 1px solid var(--border-color);
        }

        .pulse-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--color-success);
            box-shadow: 0 0 10px var(--color-success);
            animation: pulse 1.8s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.9); opacity: 0.6; }
            50% { transform: scale(1.1); opacity: 1; box-shadow: 0 0 14px var(--color-success); }
            100% { transform: scale(0.9); opacity: 0.6; }
        }

        .dashboard-container {
            max-width: 1300px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        /* Hero Parking Visualizer */
        .parking-hero {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 968px) {
            .parking-hero {
                grid-template-columns: 1fr;
            }
        }

        .hero-card {
            background-color: var(--bg-secondary);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 2rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .hero-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%);
            z-index: 1;
            pointer-events: none;
        }

        .hero-details {
            z-index: 2;
        }

        .hero-label {
            text-transform: uppercase;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .hero-status-text {
            font-family: 'Outfit', sans-serif;
            font-size: 2.75rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .hero-status-text.state-libre {
            color: var(--color-success);
            text-shadow: 0 0 20px rgba(16, 185, 129, 0.2);
        }
        .hero-status-text.state-acercandose {
            color: var(--color-warning);
            text-shadow: 0 0 20px rgba(245, 158, 11, 0.2);
        }
        .hero-status-text.state-ocupado {
            color: var(--color-danger);
            text-shadow: 0 0 20px rgba(239, 68, 68, 0.2);
        }

        .hero-stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
            z-index: 2;
        }

        .hero-mini-stat h4 {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }

        .hero-mini-stat p {
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
        }

        /* Parking Spot Simulator Graphic */
        .spot-visualizer {
            background-color: var(--bg-secondary);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .car-bay {
            position: relative;
            width: 140px;
            height: 200px;
            border-left: 6px dashed rgba(255, 255, 255, 0.2);
            border-right: 6px dashed rgba(255, 255, 255, 0.2);
            border-bottom: 6px solid rgba(255, 255, 255, 0.2);
            border-radius: 0 0 12px 12px;
            margin: 1.5rem auto;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.5s ease;
        }

        .car-bay.bay-libre {
            border-color: rgba(16, 185, 129, 0.4);
            box-shadow: inset 0 -30px 40px rgba(16, 185, 129, 0.05);
        }
        .car-bay.bay-acercandose {
            border-color: rgba(245, 158, 11, 0.4);
            box-shadow: inset 0 -30px 40px rgba(245, 158, 11, 0.05);
        }
        .car-bay.bay-ocupado {
            border-color: rgba(239, 68, 68, 0.4);
            box-shadow: inset 0 -30px 40px rgba(239, 68, 68, 0.08);
        }

        .visualizer-sensor {
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }

        .sensor-device {
            width: 24px;
            height: 12px;
            background-color: #64748b;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.5);
        }

        .sensor-beam {
            width: 2px;
            height: 60px;
            background: linear-gradient(to bottom, var(--accent-primary), transparent);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .sensor-beam.beam-active {
            opacity: 0.8;
            animation: radarBeam 1.5s infinite linear;
        }

        @keyframes radarBeam {
            0% { height: 10px; opacity: 0.8; }
            100% { height: 130px; opacity: 0.1; }
        }

        .car-icon {
            font-size: 5rem;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            opacity: 0.1;
            transform: translateY(-80px) scale(0.6);
        }

        .car-bay.bay-ocupado .car-icon {
            opacity: 1;
            transform: translateY(10px) scale(1);
            color: #94a3b8;
        }
        
        .car-bay.bay-acercandose .car-icon {
            opacity: 0.5;
            transform: translateY(-20px) scale(0.85);
            color: #64748b;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background-color: var(--bg-secondary);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: var(--border-glow);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.08);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--accent-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-card:nth-child(2) .stat-icon {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--color-success);
        }
        .stat-card:nth-child(3) .stat-icon {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--color-warning);
        }
        .stat-card:nth-child(4) .stat-icon {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--color-danger);
        }
        .stat-card:nth-child(5) .stat-icon {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--color-danger);
        }

        .stat-info h3 {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-family: 'Outfit', sans-serif;
            font-size: 1.75rem;
            font-weight: 700;
        }

        /* Log Section */
        .log-section {
            background-color: var(--bg-secondary);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-header h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 600;
        }

        .controls {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .filter-btn {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .filter-btn:hover {
            color: var(--text-primary);
            border-color: var(--accent-primary);
        }

        .filter-btn.active {
            background-color: var(--accent-primary);
            border-color: var(--accent-primary);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
        }

        .action-btn {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            background-color: var(--accent-primary);
            border-color: var(--accent-primary);
            color: white;
        }

        /* Responsive Table styling */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
        }

        th {
            background-color: var(--bg-card);
            padding: 1rem 1.25rem;
            font-weight: 600;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.8px;
        }

        td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr {
            transition: background-color 0.2s ease;
        }

        tr:hover td {
            background-color: rgba(255, 255, 255, 0.02);
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.75rem;
            border-radius: 99px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-libre {
            background-color: rgba(16, 185, 129, 0.12);
            color: var(--color-success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        .badge-acercandose {
            background-color: rgba(245, 158, 11, 0.12);
            color: var(--color-warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        .badge-ocupado {
            background-color: rgba(239, 68, 68, 0.12);
            color: var(--color-danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }
        
        .alert-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .alert-ok { color: var(--color-success); }
        .alert-ok .alert-dot { background-color: var(--color-success); box-shadow: 0 0 8px var(--color-success); }
        
        .alert-advert { color: var(--color-warning); }
        .alert-advert .alert-dot { background-color: var(--color-warning); box-shadow: 0 0 8px var(--color-warning); }
        
        .alert-danger { color: var(--color-danger); }
        .alert-danger .alert-dot { background-color: var(--color-danger); box-shadow: 0 0 8px var(--color-danger); }

        .time-col {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .empty-state {
            padding: 3rem;
            text-align: center;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        /* Charts Section */
        .charts-section {
            margin-bottom: 2.5rem;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        @media (max-width: 968px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        .chart-card {
            background-color: var(--bg-secondary);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .chart-card h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            color: var(--text-primary);
        }

        .chart-card.full-width {
            grid-column: 1 / -1;
        }

        .chart-wrapper {
            position: relative;
            height: 280px;
            width: 100%;
        }

        .chart-wrapper.small {
            height: 240px;
        }
    </style>
</head>
<body>

    <header>
        <div class="header-title-container">
            <div class="header-logo">
                <i class="fa-solid fa-square-parking"></i>
            </div>
            <h1>Dashboard Estacionamiento Inteligente</h1>
        </div>
        <div class="header-status">
            <div class="pulse-indicator"></div>
            <span>Monitoreo Activo (ESP32)</span>
        </div>
    </header>

    <div class="dashboard-container">
        
        <!-- Parking Hero and Spot Visualizer -->
        <div class="parking-hero">
            <div class="hero-card">
                <div class="hero-details">
                    <p class="hero-label">Estado Actual del Cajón</p>
                    
                    <div id="current-status-container" class="hero-status-text <?php 
                        if ($latest_reading) {
                            if ($latest_reading['estado'] === 'Libre') echo 'state-libre';
                            elseif ($latest_reading['estado'] === 'Acercandose') echo 'state-acercandose';
                            else echo 'state-ocupado';
                        }
                    ?>">
                        <?php if ($latest_reading): ?>
                            <?php if ($latest_reading['estado'] === 'Libre'): ?>
                                <i class="fa-solid fa-circle-check"></i>
                            <?php elseif ($latest_reading['estado'] === 'Acercandose'): ?>
                                <i class="fa-solid fa-circle-exclamation"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($latest_reading['estado']); ?></span>
                        <?php else: ?>
                            <i class="fa-solid fa-circle-question"></i>
                            <span>Sin Datos</span>
                        <?php endif; ?>
                    </div>
                    
                    <p style="color: var(--text-secondary);">
                        Último dato recibido: <strong id="latest-reading-time"><?php echo $latest_reading ? htmlspecialchars($latest_reading['fecha_hora']) : '-'; ?></strong>
                    </p>
                </div>

                <div class="hero-stats-row">
                    <div class="hero-mini-stat">
                        <h4>Distancia</h4>
                        <p id="latest-distance"><?php echo $latest_reading ? $latest_reading['distance'] : '0.0'; ?> <span style="font-size: 0.9rem; color: var(--text-secondary); font-weight: normal;">cm</span></p>
                    </div>
                    <div class="hero-mini-stat">
                        <h4>Alerta</h4>
                        <p id="latest-alerta">
                            <?php 
                            if ($latest_reading) {
                                if ($latest_reading['alerta'] === 'OK') echo '<span class="alert-ok"><i class="fa-solid fa-check"></i> OK</span>';
                                elseif ($latest_reading['alerta'] === 'PRECAUCIÓN') echo '<span class="alert-advert"><i class="fa-solid fa-triangle-exclamation"></i> ADV</span>';
                                else echo '<span class="alert-danger"><i class="fa-solid fa-skull-crossbones"></i> CRIT</span>';
                            } else {
                                echo '-';
                            }
                            ?>
                        </p>
                    </div>
                    <div class="hero-mini-stat">
                        <h4>Buzzer</h4>
                        <p id="latest-buzzer"><?php echo $latest_reading ? ($latest_reading['frecuencia_buzzer'] > 0 ? $latest_reading['frecuencia_buzzer'] . ' <span style="font-size: 0.8rem; font-weight: normal;">Hz</span>' : 'Apagado') : '-'; ?></p>
                    </div>
                </div>
            </div>

            <div class="spot-visualizer">
                <p class="hero-label">Visualizador 3D del Cajón</p>
                <?php 
                    $bay_class = 'bay-libre';
                    $beam_class = '';
                    if ($latest_reading) {
                        if ($latest_reading['estado'] === 'Libre') {
                            $bay_class = 'bay-libre';
                            $beam_class = 'beam-active';
                        } elseif ($latest_reading['estado'] === 'Acercandose') {
                            $bay_class = 'bay-acercandose';
                            $beam_class = 'beam-active';
                        } else {
                            $bay_class = 'bay-ocupado';
                        }
                    }
                ?>
                <div class="car-bay <?php echo $bay_class; ?>" id="visualizer-bay">
                    <div class="visualizer-sensor">
                        <div class="sensor-device"></div>
                        <div class="sensor-beam <?php echo $beam_class; ?>" id="sensor-beam"></div>
                    </div>
                    <div class="car-icon">
                        <i class="fa-solid fa-car"></i>
                    </div>
                </div>
                <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;" id="visualizer-feedback">
                    <?php 
                    if ($latest_reading) {
                        if ($latest_reading['estado'] === 'Libre') echo 'El espacio está vacío.';
                        elseif ($latest_reading['estado'] === 'Acercandose') echo '¡Un vehículo se aproxima!';
                        else echo 'Cajón ocupado.';
                    } else {
                        echo 'Esperando conexión del sensor...';
                    }
                    ?>
                </p>
            </div>
        </div>

        <!-- Metrics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fa-solid fa-ruler-horizontal"></i>
                </div>
                <div class="stat-info">
                    <h3>Distancia Actual</h3>
                    <div class="stat-number" id="stat-current-distance">
                        <?php echo $latest_reading ? number_format($latest_reading['distance'], 1) : '0.0'; ?>
                        <span style="font-size: 1rem; font-weight: 500;">cm</span>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fa-solid fa-square-parking"></i>
                </div>
                <div class="stat-info">
                    <h3>Estado del Cajón</h3>
                    <div class="stat-number" id="stat-current-state" style="font-size: 1.35rem;">
                        <?php echo $latest_reading ? htmlspecialchars($latest_reading['estado']) : 'Sin datos'; ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <div class="stat-info">
                    <h3>Alerta Sonora</h3>
                    <div class="stat-number" id="stat-current-buzzer" style="font-size: 1.35rem;">
                        <?php echo $latest_reading ? ($latest_reading['frecuencia_buzzer'] > 0 ? 'Activada' : 'Inactiva') : '-'; ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fa-solid fa-database"></i>
                </div>
                <div class="stat-info">
                    <h3>Total de Lecturas</h3>
                    <div class="stat-number" id="stat-total-records"><?php echo $total_records; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fa-solid fa-car"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Espacios Ocupados</h3>
                    <div class="stat-number" id="stat-total-ocupados" style="color: var(--color-danger);">
                        <?php
                        $ocupados_count = $pdo->query("SELECT COUNT(*) FROM parking_logs WHERE estado = 'Ocupado'")->fetchColumn();
                        echo $ocupados_count;
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-section">
            <div class="charts-grid">
                <div class="chart-card full-width">
                    <h3>Historial de Distancia por Horas</h3>
                    <div class="chart-wrapper">
                        <canvas id="chart-distance"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <h3>Estados del Cajón</h3>
                    <div class="chart-wrapper small">
                        <canvas id="chart-estados"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <h3>Frecuencia de Alertas Sonoras</h3>
                    <div class="chart-wrapper small">
                        <canvas id="chart-buzzer"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <h3>Porcentaje de Ocupación</h3>
                    <div class="chart-wrapper small">
                        <canvas id="chart-ocupacion"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historical logs table -->
        <div class="log-section">
            <div class="table-header">
                <h2>Últimas 5 Lecturas</h2>
                <div class="controls">
                    <button onclick="setFilter('all')" class="filter-btn <?php echo $filter_status === 'all' ? 'active' : ''; ?>" id="btn-filter-all">Todos</button>
                    <button onclick="setFilter('Libre')" class="filter-btn <?php echo $filter_status === 'Libre' ? 'active' : ''; ?>" id="btn-filter-Libre">Libre</button>
                    <button onclick="setFilter('Acercandose')" class="filter-btn <?php echo $filter_status === 'Acercandose' ? 'active' : ''; ?>" id="btn-filter-Acercandose">Acercándose</button>
                    <button onclick="setFilter('Ocupado')" class="filter-btn <?php echo $filter_status === 'Ocupado' ? 'active' : ''; ?>" id="btn-filter-Ocupado">Ocupado</button>
                    
                    <button onclick="updateDashboard()" class="action-btn" title="Actualizar">
                        <i class="fa-solid fa-rotate" id="refresh-icon"></i>
                    </button>
                    <a href="seed.php" target="_blank" class="action-btn" title="Re-generar 150 datos (Seed)" onclick="return confirm('¿Re-generar 150 datos de prueba? Esto vaciará la tabla actual.');">
                        <i class="fa-solid fa-seedling"></i>
                    </a>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>Distancia (cm)</th>
                            <th>Estado</th>
                            <th>Buzzer (Hz)</th>
                            <th>Alerta / Alarma</th>
                            <th class="time-col">Fecha y Hora</th>
                        </tr>
                    </thead>
                    <tbody id="logs-table-body">
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $log): ?>
                                <?php 
                                    $badge_class = 'badge-libre';
                                    $alert_class = 'alert-ok';
                                    
                                    if ($log['estado'] === 'Libre') {
                                        $badge_class = 'badge-libre';
                                        $alert_class = 'alert-ok';
                                    } elseif ($log['estado'] === 'Acercandose') {
                                        $badge_class = 'badge-acercandose';
                                        $alert_class = 'alert-advert';
                                    } elseif ($log['estado'] === 'Ocupado') {
                                        $badge_class = 'badge-ocupado';
                                        $alert_class = 'alert-danger';
                                    }
                                ?>
                                <tr id="row-<?php echo $log['id']; ?>">
                                    <td><strong>#<?php echo $log['id']; ?></strong></td>
                                    <td><?php echo number_format($log['distance'], 1); ?> cm</td>
                                    <td>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($log['estado']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $log['frecuencia_buzzer'] > 0 ? htmlspecialchars($log['frecuencia_buzzer']) . ' Hz' : '<span style="color: var(--text-secondary); opacity: 0.6;">Inactivo</span>'; ?>
                                    </td>
                                    <td>
                                        <div class="alert-indicator <?php echo $alert_class; ?>">
                                            <div class="alert-dot"></div>
                                            <span><?php echo htmlspecialchars($log['alerta']); ?></span>
                                        </div>
                                    </td>
                                    <td class="time-col"><?php echo htmlspecialchars($log['fecha_hora']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="empty-row">
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fa-solid fa-database"></i>
                                        <p>No se encontraron datos en esta consulta.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- JavaScript for Live Data Polling (AJAX) -->
    <script>
        let currentFilter = '<?php echo $filter_status; ?>';
        let updateInterval;
        const charts = {};
        const chartColors = {
            libre: '#10b981',
            acercandose: '#f59e0b',
            ocupado: '#ef4444',
            primary: '#3b82f6',
            grid: 'rgba(255, 255, 255, 0.08)',
            text: '#94a3b8'
        };

        function commonChartOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: chartColors.text, font: { family: "'Plus Jakarta Sans', sans-serif", size: 12 } }
                    }
                },
                scales: {
                    x: {
                        grid: { color: chartColors.grid },
                        ticks: { color: chartColors.text, font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 } }
                    },
                    y: {
                        grid: { color: chartColors.grid },
                        ticks: { color: chartColors.text, font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 } }
                    }
                }
            };
        }

        function initCharts() {
            Chart.defaults.color = chartColors.text;
            Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";

            // 1. Distance history line chart
            const ctxDistance = document.getElementById('chart-distance');
            if (ctxDistance) {
                charts.distance = new Chart(ctxDistance, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Distancia (cm)',
                            data: [],
                            borderColor: chartColors.primary,
                            backgroundColor: 'rgba(59, 130, 246, 0.15)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 3,
                            pointBackgroundColor: chartColors.primary
                        }]
                    },
                    options: {
                        ...commonChartOptions(),
                        plugins: { legend: { display: false } },
                        scales: {
                            x: commonChartOptions().scales.x,
                            y: { ...commonChartOptions().scales.y, beginAtZero: true, title: { display: true, text: 'cm', color: chartColors.text } }
                        }
                    }
                });
            }

            // 2. Estado distribution doughnut
            const ctxEstados = document.getElementById('chart-estados');
            if (ctxEstados) {
                charts.estados = new Chart(ctxEstados, {
                    type: 'doughnut',
                    data: {
                        labels: ['Libre', 'Acercándose', 'Ocupado'],
                        datasets: [{
                            data: [0, 0, 0],
                            backgroundColor: [chartColors.libre, chartColors.acercandose, chartColors.ocupado],
                            borderWidth: 0,
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right', labels: { color: chartColors.text, font: { size: 12 } } }
                        }
                    }
                });
            }

            // 3. Buzzer frequency bar chart
            const ctxBuzzer = document.getElementById('chart-buzzer');
            if (ctxBuzzer) {
                charts.buzzer = new Chart(ctxBuzzer, {
                    type: 'bar',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Lecturas',
                            data: [],
                            backgroundColor: [chartColors.libre, chartColors.acercandose, chartColors.ocupado],
                            borderRadius: 8,
                            borderWidth: 0
                        }]
                    },
                    options: {
                        ...commonChartOptions(),
                        plugins: { legend: { display: false } },
                        scales: {
                            x: commonChartOptions().scales.x,
                            y: { ...commonChartOptions().scales.y, beginAtZero: true, ticks: { stepSize: 1, color: chartColors.text } }
                        }
                    }
                });
            }

            // 4. Occupancy percentage doughnut
            const ctxOcupacion = document.getElementById('chart-ocupacion');
            if (ctxOcupacion) {
                charts.ocupacion = new Chart(ctxOcupacion, {
                    type: 'doughnut',
                    data: {
                        labels: ['Ocupado', 'Disponible'],
                        datasets: [{
                            data: [0, 100],
                            backgroundColor: [chartColors.ocupado, 'rgba(148, 163, 184, 0.2)'],
                            borderWidth: 0,
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right', labels: { color: chartColors.text, font: { size: 12 } } },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.label + ': ' + context.raw + '%';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        async function updateCharts() {
            try {
                const response = await fetch('charts.php');
                if (!response.ok) throw new Error('Chart fetch failed');
                const chartData = await response.json();
                if (chartData.status !== 'success') return;

                // Update distance history
                if (charts.distance && chartData.distance_history) {
                    charts.distance.data.labels = chartData.distance_history.map(p => p.hora);
                    charts.distance.data.datasets[0].data = chartData.distance_history.map(p => p.distancia);
                    charts.distance.update();
                }

                // Update estado distribution
                if (charts.estados && chartData.estado_distribution) {
                    const ed = chartData.estado_distribution;
                    charts.estados.data.datasets[0].data = [
                        ed['Libre'] || 0,
                        ed['Acercandose'] || 0,
                        ed['Ocupado'] || 0
                    ];
                    charts.estados.update();
                }

                // Update buzzer frequency
                if (charts.buzzer && chartData.buzzer_distribution) {
                    const labels = Object.keys(chartData.buzzer_distribution);
                    const values = Object.values(chartData.buzzer_distribution);
                    charts.buzzer.data.labels = labels;
                    charts.buzzer.data.datasets[0].data = values;
                    charts.buzzer.data.datasets[0].backgroundColor = labels.map(l => {
                        if (l === 'Inactivo') return chartColors.libre;
                        if (l === '1000 Hz') return chartColors.acercandose;
                        return chartColors.ocupado;
                    });
                    charts.buzzer.update();
                }

                // Update occupancy
                if (charts.ocupacion && chartData.ocupacion) {
                    const oc = chartData.ocupacion;
                    charts.ocupacion.data.datasets[0].data = [oc.porcentaje, 100 - oc.porcentaje];
                    charts.ocupacion.update();
                }
            } catch (err) {
                console.error('Error updating charts:', err);
            }
        }

        function setFilter(filter) {
            currentFilter = filter;
            
            // Update active state in buttons
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('btn-filter-' + filter).classList.add('active');
            
            // Fetch filtered data immediately
            updateDashboard();
        }

        async function updateDashboard() {
            const refreshIcon = document.getElementById('refresh-icon');
            if (refreshIcon) refreshIcon.classList.add('fa-spin');
            
            try {
                const response = await fetch(`data.php?estado=${currentFilter}`);
                if (!response.ok) throw new Error("Network response was not ok");
                
                const data = await response.json();
                if (data.status === 'success') {
                    // 1. Update Metrics Cards
                    if (data.latest_reading) {
                        document.getElementById('stat-current-distance').innerHTML = `${data.latest_reading.distance.toFixed(1)} <span style="font-size: 1rem; font-weight: 500;">cm</span>`;
                        document.getElementById('stat-current-state').innerText = data.latest_reading.estado;
                        document.getElementById('stat-current-buzzer').innerText = data.latest_reading.frecuencia_buzzer > 0 ? 'Activada' : 'Inactiva';
                    } else {
                        document.getElementById('stat-current-distance').innerText = '0.0';
                        document.getElementById('stat-current-state').innerText = 'Sin datos';
                        document.getElementById('stat-current-buzzer').innerText = '-';
                    }
                    document.getElementById('stat-total-records').innerText = data.total_records;
                    document.getElementById('stat-total-ocupados').innerText = data.ocupados_count;

                    // 2. Update Latest Status Card
                    const statusContainer = document.getElementById('current-status-container');
                    const timeEl = document.getElementById('latest-reading-time');
                    const distanceEl = document.getElementById('latest-distance');
                    const alertaEl = document.getElementById('latest-alerta');
                    const buzzerEl = document.getElementById('latest-buzzer');
                    
                    // Update Spot Visualizer
                    const visualizerBay = document.getElementById('visualizer-bay');
                    const sensorBeam = document.getElementById('sensor-beam');
                    const visualizerFeedback = document.getElementById('visualizer-feedback');
                    
                    if (data.latest_reading) {
                        const reading = data.latest_reading;
                        
                        // Status text and class mapping
                        statusContainer.className = 'hero-status-text';
                        let statusIcon = '';
                        let bayClass = 'bay-libre';
                        let beamClass = 'beam-active';
                        let feedbackText = '';
                        
                        if (reading.estado === 'Libre') {
                            statusContainer.classList.add('state-libre');
                            statusIcon = '<i class="fa-solid fa-circle-check"></i>';
                            bayClass = 'bay-libre';
                            feedbackText = 'El espacio está vacío.';
                        } else if (reading.estado === 'Acercandose') {
                            statusContainer.classList.add('state-acercandose');
                            statusIcon = '<i class="fa-solid fa-circle-exclamation"></i>';
                            bayClass = 'bay-acercandose';
                            feedbackText = '¡Un vehículo se aproxima!';
                        } else {
                            statusContainer.classList.add('state-ocupado');
                            statusIcon = '<i class="fa-solid fa-triangle-exclamation"></i>';
                            bayClass = 'bay-ocupado';
                            beamClass = ''; // turn off beam if car blocks it
                            feedbackText = 'Cajón ocupado.';
                        }
                        
                        statusContainer.innerHTML = `${statusIcon} <span>${reading.estado}</span>`;
                        timeEl.innerText = reading.fecha_hora;
                        
                        // Update Mini Stats
                        distanceEl.innerHTML = `${reading.distance.toFixed(1)} <span style="font-size: 0.9rem; color: var(--text-secondary); font-weight: normal;">cm</span>`;
                        
                        // Update Alerta indicator
                        if (reading.alerta === 'OK') {
                            alertaEl.innerHTML = '<span class="alert-ok"><i class="fa-solid fa-check"></i> OK</span>';
                        } else if (reading.alerta === 'PRECAUCIÓN') {
                            alertaEl.innerHTML = '<span class="alert-advert"><i class="fa-solid fa-triangle-exclamation"></i> ADV</span>';
                        } else {
                            alertaEl.innerHTML = '<span class="alert-danger"><i class="fa-solid fa-skull-crossbones"></i> CRIT</span>';
                        }
                        
                        // Update Buzzer
                        buzzerEl.innerHTML = reading.frecuencia_buzzer > 0 
                            ? `${reading.frecuencia_buzzer} <span style="font-size: 0.8rem; font-weight: normal;">Hz</span>`
                            : 'Apagado';
                            
                        // Update Visualizer
                        visualizerBay.className = `car-bay ${bayClass}`;
                        sensorBeam.className = `sensor-beam ${beamClass}`;
                        visualizerFeedback.innerText = feedbackText;
                    } else {
                        // Empty database state
                        statusContainer.className = 'hero-status-text';
                        statusContainer.innerHTML = '<i class="fa-solid fa-circle-question"></i> <span>Sin Datos</span>';
                        timeEl.innerText = '-';
                        distanceEl.innerText = '0.0';
                        alertaEl.innerText = '-';
                        buzzerEl.innerText = '-';
                        visualizerBay.className = 'car-bay bay-libre';
                        sensorBeam.className = 'sensor-beam';
                        visualizerFeedback.innerText = 'Esperando conexión del sensor...';
                    }
                    
                    // 3. Update Table Rows dynamically
                    const tbody = document.getElementById('logs-table-body');
                    if (data.logs && data.logs.length > 0) {
                        let html = '';
                        data.logs.forEach(log => {
                            let badgeClass = 'badge-libre';
                            let alertClass = 'alert-ok';
                            
                            if (log.estado === 'Libre') {
                                badgeClass = 'badge-libre';
                                alertClass = 'alert-ok';
                            } else if (log.estado === 'Acercandose') {
                                badgeClass = 'badge-acercandose';
                                alertClass = 'alert-advert';
                            } else if (log.estado === 'Ocupado') {
                                badgeClass = 'badge-ocupado';
                                alertClass = 'alert-danger';
                            }
                            
                            const buzzerText = log.frecuencia_buzzer > 0 
                                ? `${log.frecuencia_buzzer} Hz` 
                                : '<span style="color: var(--text-secondary); opacity: 0.6;">Inactivo</span>';
                                
                            html += `
                            <tr id="row-${log.id}">
                                <td><strong>#${log.id}</strong></td>
                                <td>${log.distance.toFixed(1)} cm</td>
                                <td>
                                    <span class="badge ${badgeClass}">
                                        ${log.estado}
                                    </span>
                                </td>
                                <td>${buzzerText}</td>
                                <td>
                                    <div class="alert-indicator ${alertClass}">
                                        <div class="alert-dot"></div>
                                        <span>${log.alerta}</span>
                                    </div>
                                </td>
                                <td class="time-col">${log.fecha_hora}</td>
                            </tr>
                            `;
                        });
                        tbody.innerHTML = html;
                    } else {
                        tbody.innerHTML = `
                        <tr id="empty-row">
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fa-solid fa-database"></i>
                                    <p>No se encontraron datos en esta consulta.</p>
                                </div>
                            </td>
                        </tr>
                        `;
                    }
                }
            } catch (err) {
                console.error("Error updating dashboard:", err);
            } finally {
                if (refreshIcon) {
                    setTimeout(() => refreshIcon.classList.remove('fa-spin'), 300);
                }
            }
        }

        // Initialize polling when the page loads
        document.addEventListener('DOMContentLoaded', () => {
            initCharts();
            updateCharts();
            // Poll data every 2 seconds (2000 ms)
            updateInterval = setInterval(() => {
                updateDashboard();
                updateCharts();
            }, 2000);
            console.log("Real-time polling started (every 2s)");
        });
    </script>
</body>
</html>
