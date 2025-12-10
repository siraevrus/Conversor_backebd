<?php

// Устанавливаем правильный Content-Type
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Простая проверка авторизации (в продакшене используйте полноценную систему)
session_start();

// Обработка входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        // Определяем правильный URL для редиректа
        $redirectUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
            . '://' . $_SERVER['HTTP_HOST'] . '/admin/';
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        $error = 'Неверный пароль';
        include __DIR__ . '/login.php';
        exit;
    }
}

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    include __DIR__ . '/login.php';
    exit;
}

// Получение данных для дашборда
$database = new Database();
$db = $database->getConnection();

// Статистика за сегодня
$today = date('Y-m-d');
$statsQuery = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN response_status = 200 THEN 1 ELSE 0 END) as successful_requests,
    SUM(CASE WHEN response_status != 200 THEN 1 ELSE 0 END) as failed_requests,
    AVG(response_time_ms) as avg_response_time,
    COUNT(DISTINCT device_id) as unique_devices
FROM api_requests 
WHERE DATE(created_at) = :today";

$stmt = $db->prepare($statsQuery);
$stmt->execute([':today' => $today]);
$todayStats = $stmt->fetch();

// Популярные endpoints
$popularEndpointsQuery = "SELECT endpoint, COUNT(*) as count, AVG(response_time_ms) as avg_time
FROM api_requests 
WHERE DATE(created_at) = :today
GROUP BY endpoint
ORDER BY count DESC
LIMIT 10";

$stmt = $db->prepare($popularEndpointsQuery);
$stmt->execute([':today' => $today]);
$popularEndpoints = $stmt->fetchAll();

// Последние ошибки
$errorsQuery = "SELECT * FROM error_logs 
ORDER BY created_at DESC 
LIMIT 10";

$errors = $db->query($errorsQuery)->fetchAll();

// Последние конвертации
$conversionsQuery = "SELECT cc.*, d.device_id as device_identifier
FROM currency_conversions cc
LEFT JOIN devices d ON cc.device_id = d.id
ORDER BY cc.created_at DESC
LIMIT 20";

$conversions = $db->query($conversionsQuery)->fetchAll();

// Статистика конвертаций
$conversionStatsQuery = "SELECT 
    from_currency, 
    to_currency, 
    COUNT(*) as count,
    SUM(amount) as total_amount
FROM currency_conversions
WHERE DATE(created_at) = :today
GROUP BY from_currency, to_currency
ORDER BY count DESC
LIMIT 10";

$stmt = $db->prepare($conversionStatsQuery);
$stmt->execute([':today' => $today]);
$conversionStats = $stmt->fetchAll();

// Последние обновления курсов
$rateUpdatesQuery = "SELECT * FROM rate_update_logs 
ORDER BY created_at DESC 
LIMIT 10";

$rateUpdates = $db->query($rateUpdatesQuery)->fetchAll();

// Общая статистика устройств
$devicesStatsQuery = "SELECT 
    COUNT(*) as total_devices,
    COUNT(CASE WHEN last_active >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as active_24h,
    COUNT(CASE WHEN last_active >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_7d
FROM devices";

$devicesStats = $db->query($devicesStatsQuery)->fetch();

include __DIR__ . '/dashboard.php';

