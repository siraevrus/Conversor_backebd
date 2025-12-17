<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ панель - Currency API</title>
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

        .header .subtitle {
            opacity: 0.9;
            font-size: 0.9em;
        }

        .header-links {
            float: right;
            margin-top: 10px;
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

        .logout {
            float: right;
            margin-top: -40px;
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            color: white;
            transition: background 0.3s;
        }

        .logout:hover {
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

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
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

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 1em;
            color: #666;
            transition: color 0.3s;
        }

        .tab.active {
            color: #667eea;
            border-bottom: 2px solid #667eea;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .refresh-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }

        .refresh-btn:hover {
            background: #5568d3;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-links">
            <a href="/admin/api_monitor.php" class="header-link">📡 Мониторинг API</a>
            <a href="/admin/logs.php" class="header-link">📝 Логи</a>
            <a href="/rates.php" class="header-link" target="_blank">💱 Курсы валют</a>
            <a href="?logout=1" class="header-link">Выйти</a>
        </div>
        <h1>📊 Админ панель</h1>
        <div class="subtitle">Управление и мониторинг Currency API</div>
    </div>

    <div class="container">
        <!-- Статистика за сегодня -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Запросов сегодня</div>
                <div class="value"><?= number_format($todayStats['total_requests'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Успешных</div>
                <div class="value" style="color: #28a745;"><?= number_format($todayStats['successful_requests'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Ошибок</div>
                <div class="value" style="color: #dc3545;"><?= number_format($todayStats['failed_requests'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Среднее время ответа</div>
                <div class="value"><?= number_format($todayStats['avg_response_time'] ?? 0, 0) ?> мс</div>
            </div>
            <div class="stat-card">
                <div class="label">Уникальных устройств</div>
                <div class="value"><?= number_format($todayStats['unique_devices'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Всего устройств</div>
                <div class="value"><?= number_format($devicesStats['total_devices'] ?? 0) ?></div>
            </div>
        </div>

        <!-- Популярные endpoints -->
        <div class="section">
            <h2>🔥 Популярные endpoints (сегодня)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Endpoint</th>
                        <th>Запросов</th>
                        <th>Среднее время</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($popularEndpoints as $endpoint): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($endpoint['endpoint']) ?></code></td>
                        <td><?= number_format($endpoint['count']) ?></td>
                        <td><?= number_format($endpoint['avg_time'], 0) ?> мс</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Вкладки для разных разделов -->
        <div class="section">
            <div style="margin-bottom: 20px;">
                <a href="/admin/logs.php" style="background: #667eea; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;">
                    📋 Просмотр всех логов запросов
                </a>
            </div>

            <div class="tabs">
                <button class="tab active" onclick="showTab('conversions')">Конвертации</button>
                <button class="tab" onclick="showTab('errors')">Ошибки</button>
                <button class="tab" onclick="showTab('updates')">Обновления курсов</button>
            </div>

            <!-- Конвертации -->
            <div id="conversions" class="tab-content active">
                <h2>💱 Последние конвертации</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Время</th>
                            <th>Сумма</th>
                            <th>Из</th>
                            <th>В</th>
                            <th>Результат</th>
                            <th>Курс</th>
                            <th>Устройство</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conversions as $conv): ?>
                        <tr>
                            <td><?= date('d.m.Y H:i', strtotime($conv['created_at'])) ?></td>
                            <td><?= number_format($conv['amount'], 2) ?></td>
                            <td><strong><?= htmlspecialchars($conv['from_currency']) ?></strong></td>
                            <td><strong><?= htmlspecialchars($conv['to_currency']) ?></strong></td>
                            <td><?= number_format($conv['converted_amount'], 2) ?></td>
                            <td><?= number_format($conv['rate'], 4) ?></td>
                            <td><?= htmlspecialchars($conv['device_identifier'] ?? 'N/A') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3 style="margin-top: 30px;">📈 Популярные пары валют (сегодня)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Из</th>
                            <th>В</th>
                            <th>Конвертаций</th>
                            <th>Общая сумма</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conversionStats as $stat): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($stat['from_currency']) ?></strong></td>
                            <td><strong><?= htmlspecialchars($stat['to_currency']) ?></strong></td>
                            <td><?= number_format($stat['count']) ?></td>
                            <td><?= number_format($stat['total_amount'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Ошибки -->
            <div id="errors" class="tab-content">
                <h2>❌ Последние ошибки</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Время</th>
                            <th>Тип</th>
                            <th>Endpoint</th>
                            <th>Сообщение</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($errors as $error): ?>
                        <tr>
                            <td><?= date('d.m.Y H:i', strtotime($error['created_at'])) ?></td>
                            <td><span class="badge badge-error"><?= htmlspecialchars($error['error_type']) ?></span></td>
                            <td><code><?= htmlspecialchars($error['endpoint']) ?></code></td>
                            <td><?= htmlspecialchars(substr($error['error_message'], 0, 100)) ?><?= strlen($error['error_message']) > 100 ? '...' : '' ?></td>
                            <td><?= $error['http_status'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Обновления курсов -->
            <div id="updates" class="tab-content">
                <h2>🔄 История обновлений курсов</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Время</th>
                            <th>Базовая валюта</th>
                            <th>Курсов</th>
                            <th>Источник</th>
                            <th>Статус</th>
                            <th>Время выполнения</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rateUpdates as $update): ?>
                        <tr>
                            <td><?= date('d.m.Y H:i:s', strtotime($update['created_at'])) ?></td>
                            <td><strong><?= htmlspecialchars($update['base_currency']) ?></strong></td>
                            <td><?= number_format($update['rates_count']) ?></td>
                            <td><?= htmlspecialchars($update['update_source']) ?></td>
                            <td>
                                <?php if ($update['success']): ?>
                                    <span class="badge badge-success">Успешно</span>
                                <?php else: ?>
                                    <span class="badge badge-error">Ошибка</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $update['execution_time_ms'] ? number_format($update['execution_time_ms']) . ' мс' : 'N/A' ?></td>
                        </tr>
                        <?php if (!$update['success'] && $update['error_message']): ?>
                        <tr>
                            <td colspan="6" style="color: #dc3545; font-size: 0.9em;">
                                Ошибка: <?= htmlspecialchars($update['error_message']) ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Скрыть все вкладки
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Показать выбранную вкладку
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // Автообновление каждые 30 секунд
        setInterval(() => {
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
    exit;
}
?>

