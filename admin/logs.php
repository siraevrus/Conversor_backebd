<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $redirectUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
        . '://' . $_SERVER['HTTP_HOST'] . '/admin/';
    header('Location: ' . $redirectUrl);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Параметры пагинации
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Фильтры
$filterEndpoint = $_GET['endpoint'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterDevice = $_GET['device'] ?? '';

// Построение запроса
$where = [];
$params = [];

if ($filterEndpoint) {
    $where[] = "ar.endpoint LIKE :endpoint";
    $params[':endpoint'] = "%$filterEndpoint%";
}

if ($filterStatus) {
    $where[] = "ar.response_status = :status";
    $params[':status'] = $filterStatus;
}

if ($filterDevice) {
    $where[] = "d.device_id LIKE :device";
    $params[':device'] = "%$filterDevice%";
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Подсчет общего количества
$countQuery = "SELECT COUNT(*) as total 
FROM api_requests ar
LEFT JOIN devices d ON ar.device_id = d.id
$whereClause";

$stmt = $db->prepare($countQuery);
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

// Получение логов
$query = "SELECT ar.*, d.device_id as device_identifier
FROM api_requests ar
LEFT JOIN devices d ON ar.device_id = d.id
$whereClause
ORDER BY ar.created_at DESC
LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Логи запросов - Админ панель</title>
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

        .header a {
            color: white;
            text-decoration: none;
            margin-right: 20px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filters form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filters label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }

        .filters input, .filters select {
            width: 100%;
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 0.9em;
        }

        .filters button {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }

        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-error {
            background: #f8d7da;
            color: #721c24;
        }

        .pagination {
            margin-top: 20px;
            text-align: center;
        }

        .pagination a {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 5px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .pagination a:hover {
            background: #5568d3;
        }

        .pagination .current {
            background: #555;
            cursor: default;
        }

        .params-preview {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="/admin/">← Назад к дашборду</a>
        <h1>📋 Логи запросов</h1>
    </div>

    <div class="container">
        <div class="filters">
            <form method="GET">
                <div>
                    <label>Endpoint:</label>
                    <input type="text" name="endpoint" value="<?= htmlspecialchars($filterEndpoint) ?>" placeholder="Например: /api/rates">
                </div>
                <div>
                    <label>Статус:</label>
                    <select name="status">
                        <option value="">Все</option>
                        <option value="200" <?= $filterStatus == '200' ? 'selected' : '' ?>>200 OK</option>
                        <option value="400" <?= $filterStatus == '400' ? 'selected' : '' ?>>400 Bad Request</option>
                        <option value="404" <?= $filterStatus == '404' ? 'selected' : '' ?>>404 Not Found</option>
                        <option value="500" <?= $filterStatus == '500' ? 'selected' : '' ?>>500 Error</option>
                    </select>
                </div>
                <div>
                    <label>Устройство:</label>
                    <input type="text" name="device" value="<?= htmlspecialchars($filterDevice) ?>" placeholder="ID устройства">
                </div>
                <div>
                    <button type="submit">Фильтровать</button>
                </div>
            </form>
        </div>

        <div class="table-container">
            <p style="margin-bottom: 15px;">
                Всего записей: <strong><?= number_format($total) ?></strong> | 
                Страница <?= $page ?> из <?= $totalPages ?>
            </p>

            <table>
                <thead>
                    <tr>
                        <th>Время</th>
                        <th>Метод</th>
                        <th>Endpoint</th>
                        <th>Статус</th>
                        <th>Время ответа</th>
                        <th>Размер</th>
                        <th>Устройство</th>
                        <th>IP</th>
                        <th>Параметры</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?></td>
                        <td><strong><?= htmlspecialchars($log['method']) ?></strong></td>
                        <td><code><?= htmlspecialchars($log['endpoint']) ?></code></td>
                        <td>
                            <?php if ($log['response_status'] == 200): ?>
                                <span class="badge badge-success"><?= $log['response_status'] ?></span>
                            <?php else: ?>
                                <span class="badge badge-error"><?= $log['response_status'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= $log['response_time_ms'] ? number_format($log['response_time_ms']) . ' мс' : 'N/A' ?></td>
                        <td><?= $log['response_size_bytes'] ? number_format($log['response_size_bytes']) . ' Б' : 'N/A' ?></td>
                        <td><?= htmlspecialchars($log['device_identifier'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                        <td class="params-preview" title="<?= htmlspecialchars($log['request_params'] ?? '') ?>">
                            <?= htmlspecialchars(substr($log['request_params'] ?? '', 0, 50)) ?><?= strlen($log['request_params'] ?? '') > 50 ? '...' : '' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&endpoint=<?= urlencode($filterEndpoint) ?>&status=<?= urlencode($filterStatus) ?>&device=<?= urlencode($filterDevice) ?>">← Назад</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&endpoint=<?= urlencode($filterEndpoint) ?>&status=<?= urlencode($filterStatus) ?>&device=<?= urlencode($filterDevice) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&endpoint=<?= urlencode($filterEndpoint) ?>&status=<?= urlencode($filterStatus) ?>&device=<?= urlencode($filterDevice) ?>">Вперед →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

