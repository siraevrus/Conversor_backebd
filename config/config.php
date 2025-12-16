<?php

// Загрузка переменных окружения из .env файла
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Пропускаем комментарии
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Удаляем кавычки если есть
            $value = trim($value, '"\'');
            if (!empty($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// API конфигурация
$exchangeApiUrl = getenv('EXCHANGE_RATE_API_URL') ?: 'https://v6.exchangerate-api.com/v6/8cfd4a7237ec45affd505e47/latest/USD';
$updateInterval = getenv('UPDATE_INTERVAL_MINUTES') ?: 60;
define('EXCHANGE_RATE_API_URL', $exchangeApiUrl);
define('UPDATE_INTERVAL_MINUTES', $updateInterval);

// Настройки приложения
define('API_VERSION', 'v1');
define('TIMEZONE', 'Europe/Moscow');

// Установка часового пояса
date_default_timezone_set(TIMEZONE);

// Настройки для production/development
$appEnv = getenv('APP_ENV') ?: 'production';
$appDebug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);

if ($appEnv === 'production' || !$appDebug) {
    // Production: отключаем отображение ошибок, включаем логирование
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
} else {
    // Development: показываем ошибки
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// Заголовки для API (только для API запросов, не для админ панели)
if (php_sapi_name() !== 'cli') {
    $requestPath = $_SERVER['REQUEST_URI'] ?? '';
    // Устанавливаем JSON заголовки только для API запросов
    if (strpos($requestPath, '/api/') !== false) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        // Обработка preflight запросов
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
}

