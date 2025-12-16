<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/');
    exit();
}

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Получение статистики
$stats = [];

// Статистика за сегодня
$stmt = $conn->query("
    SELECT 
        COUNT(*) as total_requests,
        COUNT(DISTINCT device_id) as unique_devices,
        COUNT(DISTINCT ip_address) as unique_ips,
        COUNT(DISTINCT endpoint) as unique_endpoints
    FROM api_requests 
    WHERE DATE(created_at) = CURDATE()
");
$stats['today'] = $stmt->fetch();

// Топ endpoints
$stmt = $conn->query("
    SELECT 
        endpoint,
        method,
        COUNT(*) as requests_count,
        COUNT(DISTINCT device_id) as devices_count
    FROM api_requests 
    WHERE DATE(created_at) = CURDATE()
    GROUP BY endpoint, method
    ORDER BY requests_count DESC
    LIMIT 10
");
$stats['top_endpoints'] = $stmt->fetchAll();

// Активные устройства
$stmt = $conn->query("
    SELECT 
        d.device_id,
        d.platform,
        d.app_version,
        d.device_name,
        COUNT(ar.id) as requests_count,
        MAX(ar.created_at) as last_request
    FROM devices d
    LEFT JOIN api_requests ar ON d.id = ar.device_id
    WHERE ar.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY d.id, d.device_id, d.platform, d.app_version, d.device_name
    ORDER BY requests_count DESC
    LIMIT 20
");
$stats['active_devices'] = $stmt->fetchAll();

// Статистика по платформам
$stmt = $conn->query("
    SELECT 
        COALESCE(d.platform, 'Unknown') as platform,
        COUNT(DISTINCT d.id) as devices_count,
        COUNT(ar.id) as requests_count
    FROM api_requests ar
    LEFT JOIN devices d ON ar.device_id = d.id
    WHERE ar.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY d.platform
    ORDER BY requests_count DESC
");
$stats['platforms'] = $stmt->fetchAll();

// Последние запросы
$stmt = $conn->query("
    SELECT 
        ar.created_at as time,
        ar.endpoint,
        ar.method,
        ar.ip_address,
        d.platform,
        d.app_version,
        d.device_id
    FROM api_requests ar
    LEFT JOIN devices d ON ar.device_id = d.id
    ORDER BY ar.created_at DESC
    LIMIT 50
");
$stats['recent_requests'] = $stmt->fetchAll();

// Статистика по часам (последние 24 часа)
$stmt = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m-%d %H:00') as hour,
        COUNT(*) as requests_count
    FROM api_requests
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d %H:00')
    ORDER BY hour DESC
");
$stats['hourly'] = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мониторинг API - Админ панель</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 1.8em;
            margin-bottom: 10px;
        }

        .header-links {
            float: right;
            margin-top: -40px;
            display: flex;
            gap: 10px;
        }

        .header-link {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            color: white;
            transition: background 0.3s;
            font-size: 0.9em;
        }

        .header-link:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-card .label {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }

        .section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .section h2 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e9ecef;
        }

        table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        table tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .refresh-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            margin-bottom: 20px;
        }

        .refresh-btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-links">
            <a href="/admin/dashboard.php" class="header-link">📊 Дашборд</a>
            <a href="/admin/logs.php" class="header-link">📝 Логи</a>
            <a href="/rates.php" class="header-link" target="_blank">💱 Курсы</a>
            <a href="?logout=1" class="header-link">Выйти</a>
        </div>
        <h1>📡 Мониторинг API</h1>
        <div class="subtitle">Использование API приложениями</div>
    </div>

    <div class="container">
        <button class="refresh-btn" onclick="location.reload()">🔄 Обновить</button>

        <!-- Статистика за сегодня -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Запросов сегодня</div>
                <div class="value"><?= number_format($stats['today']['total_requests'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Уникальных устройств</div>
                <div class="value"><?= number_format($stats['today']['unique_devices'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Уникальных IP</div>
                <div class="value"><?= number_format($stats['today']['unique_ips'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Используемых endpoints</div>
                <div class="value"><?= number_format($stats['today']['unique_endpoints'] ?? 0) ?></div>
            </div>
        </div>

        <!-- Топ endpoints -->
        <div class="section">
            <h2>🔥 Топ используемых endpoints (сегодня)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Endpoint</th>
                        <th>Метод</th>
                        <th>Запросов</th>
                        <th>Устройств</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['top_endpoints'] as $endpoint): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($endpoint['endpoint']) ?></code></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($endpoint['method']) ?></span></td>
                        <td><?= number_format($endpoint['requests_count']) ?></td>
                        <td><?= number_format($endpoint['devices_count']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Статистика по платформам -->
        <div class="section">
            <h2>📱 Статистика по платформам (24 часа)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Платформа</th>
                        <th>Устройств</th>
                        <th>Запросов</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['platforms'] as $platform): ?>
                    <tr>
                        <td><?= htmlspecialchars($platform['platform']) ?></td>
                        <td><?= number_format($platform['devices_count']) ?></td>
                        <td><?= number_format($platform['requests_count']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Активные устройства -->
        <div class="section">
            <h2>📲 Активные устройства (24 часа)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Device ID</th>
                        <th>Платформа</th>
                        <th>Версия</th>
                        <th>Запросов</th>
                        <th>Последний запрос</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['active_devices'] as $device): ?>
                    <tr>
                        <td><code><?= htmlspecialchars(substr($device['device_id'], 0, 30)) ?>...</code></td>
                        <td><?= htmlspecialchars($device['platform'] ?? 'Unknown') ?></td>
                        <td><?= htmlspecialchars($device['app_version'] ?? '-') ?></td>
                        <td><?= number_format($device['requests_count']) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($device['last_request'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Последние запросы -->
        <div class="section">
            <h2>🕐 Последние запросы</h2>
            <table>
                <thead>
                    <tr>
                        <th>Время</th>
                        <th>Endpoint</th>
                        <th>Метод</th>
                        <th>Платформа</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['recent_requests'] as $request): ?>
                    <tr>
                        <td><?= date('d.m.Y H:i:s', strtotime($request['time'])) ?></td>
                        <td><code><?= htmlspecialchars($request['endpoint']) ?></code></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($request['method']) ?></span></td>
                        <td><?= htmlspecialchars($request['platform'] ?? 'Unknown') ?></td>
                        <td><?= htmlspecialchars($request['ip_address'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Автообновление каждые 30 секунд
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>

<?php
// Обработка выхода
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/');
    exit();
}
?>

