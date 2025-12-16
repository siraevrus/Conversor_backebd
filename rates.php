<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/ExchangeRate.php';

$exchangeRate = new ExchangeRate();
$baseCurrency = $_GET['base'] ?? 'USD';
$baseCurrency = strtoupper($baseCurrency);

// Получаем все курсы
$allRates = $exchangeRate->getAllRates($baseCurrency);

// Популярные валюты для отображения вверху
$popularCurrencies = ['EUR', 'RUB', 'GBP', 'JPY', 'CNY', 'CHF', 'AUD', 'CAD', 'NZD', 'SGD', 'HKD', 'SEK', 'NOK', 'DKK', 'PLN', 'TRY', 'INR', 'BRL', 'ZAR', 'MXN'];

// Фильтрация популярных валют
$popularRates = [];
$otherRates = [];

foreach ($allRates as $rate) {
    if (in_array($rate['target_currency'], $popularCurrencies)) {
        $popularRates[] = $rate;
    } else {
        $otherRates[] = $rate;
    }
}

// Сортируем популярные валюты по порядку в массиве
usort($popularRates, function($a, $b) use ($popularCurrencies) {
    $posA = array_search($a['target_currency'], $popularCurrencies);
    $posB = array_search($b['target_currency'], $popularCurrencies);
    return $posA - $posB;
});

// Получаем время последнего обновления
$lastUpdated = !empty($allRates) ? $allRates[0]['last_updated'] : null;
if ($lastUpdated) {
    $updateTime = new DateTime($lastUpdated);
    $now = new DateTime();
    $diff = $now->diff($updateTime);
    $minutesAgo = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Курсы валют - Conversor</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header .subtitle {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .info-bar {
            background: #f8f9fa;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-item strong {
            color: #667eea;
        }

        .base-currency-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .base-currency-selector select {
            padding: 8px 15px;
            border: 2px solid #667eea;
            border-radius: 8px;
            font-size: 1em;
            background: white;
            color: #333;
            cursor: pointer;
        }

        .content {
            padding: 40px;
        }

        .section-title {
            font-size: 1.8em;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .rates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 40px;
        }

        .rate-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 12px;
            padding: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
            border: 2px solid transparent;
        }

        .rate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border-color: #667eea;
        }

        .currency-code {
            font-size: 1.5em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 8px;
        }

        .currency-rate {
            font-size: 2em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .currency-name {
            font-size: 0.9em;
            color: #666;
        }

        .all-rates-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .all-rates-table th {
            background: #667eea;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .all-rates-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .all-rates-table tr:hover {
            background: #f8f9fa;
        }

        .all-rates-table tr:last-child td {
            border-bottom: none;
        }

        .currency-code-cell {
            font-weight: bold;
            color: #667eea;
            font-size: 1.1em;
        }

        .rate-cell {
            font-size: 1.2em;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .status-updated {
            background: #d4edda;
            color: #155724;
        }

        .status-old {
            background: #fff3cd;
            color: #856404;
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1em;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8em;
            }

            .rates-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }

            .info-bar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💱 Курсы валют</h1>
            <div class="subtitle">Актуальные курсы валют в реальном времени</div>
        </div>

        <div class="info-bar">
            <div class="info-item">
                <strong>Базовая валюта:</strong>
                <div class="base-currency-selector">
                    <select onchange="window.location.href='?base=' + this.value">
                        <option value="USD" <?= $baseCurrency === 'USD' ? 'selected' : '' ?>>USD (Доллар США)</option>
                        <option value="EUR" <?= $baseCurrency === 'EUR' ? 'selected' : '' ?>>EUR (Евро)</option>
                        <option value="RUB" <?= $baseCurrency === 'RUB' ? 'selected' : '' ?>>RUB (Российский рубль)</option>
                    </select>
                </div>
            </div>
            <div class="info-item">
                <strong>Последнее обновление:</strong>
                <?php if ($lastUpdated): ?>
                    <?= date('d.m.Y H:i', strtotime($lastUpdated)) ?>
                    <?php if (isset($minutesAgo)): ?>
                        <span class="status-badge <?= $minutesAgo < 60 ? 'status-updated' : 'status-old' ?>">
                            <?= $minutesAgo < 60 ? "Обновлено {$minutesAgo} мин. назад" : "Обновлено {$minutesAgo} мин. назад" ?>
                        </span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="status-badge status-old">Данные недоступны</span>
                <?php endif; ?>
            </div>
            <div class="info-item">
                <strong>Всего валют:</strong> <?= count($allRates) ?>
            </div>
        </div>

        <div class="content">
            <?php if (!empty($popularRates)): ?>
                <h2 class="section-title">⭐ Популярные валюты</h2>
                <div class="rates-grid">
                    <?php foreach ($popularRates as $rate): ?>
                        <div class="rate-card">
                            <div class="currency-code"><?= htmlspecialchars($rate['target_currency']) ?></div>
                            <div class="currency-rate"><?= number_format((float)$rate['rate'], 4) ?></div>
                            <div class="currency-name">1 <?= htmlspecialchars($baseCurrency) ?> = <?= number_format((float)$rate['rate'], 4) ?> <?= htmlspecialchars($rate['target_currency']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($otherRates)): ?>
                <h2 class="section-title">📊 Все валюты</h2>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Поиск валюты..." onkeyup="filterRates()">
                </div>
                <table class="all-rates-table" id="ratesTable">
                    <thead>
                        <tr>
                            <th>Валюта</th>
                            <th>Курс</th>
                            <th>Обновлено</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($otherRates as $rate): ?>
                            <tr>
                                <td class="currency-code-cell"><?= htmlspecialchars($rate['target_currency']) ?></td>
                                <td class="rate-cell"><?= number_format((float)$rate['rate'], 6) ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($rate['last_updated'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if (empty($allRates)): ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <h2>Данные недоступны</h2>
                    <p style="color: #666; margin-top: 10px;">Курсы валют еще не загружены. Пожалуйста, подождите.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function filterRates() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('ratesTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td')[0];
                if (td) {
                    const txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = '';
                    } else {
                        tr[i].style.display = 'none';
                    }
                }
            }
        }

        // Автообновление каждые 5 минут
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>

