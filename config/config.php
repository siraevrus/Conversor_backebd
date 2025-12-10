<?php

// API конфигурация
define('EXCHANGE_RATE_API_URL', 'https://v6.exchangerate-api.com/v6/8cfd4a7237ec45affd505e47/latest/USD');
define('UPDATE_INTERVAL_MINUTES', 60);

// Настройки приложения
define('API_VERSION', 'v1');
define('TIMEZONE', 'Europe/Moscow');

// Установка часового пояса
date_default_timezone_set(TIMEZONE);

// Включение отображения ошибок (отключить в продакшене)
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

