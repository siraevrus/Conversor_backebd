<?php

/**
 * Роутер для встроенного PHP сервера
 * Запуск: php -S localhost:8000 router.php
 */

$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Удаляем начальный слеш
$requestPath = ltrim($requestPath, '/');

// Обработка админ панели
if (strpos($requestPath, 'admin') === 0) {
    $adminPath = substr($requestPath, 6); // Убираем 'admin'
    $adminPath = ltrim($adminPath, '/'); // Убираем начальный слеш
    
    // Устанавливаем правильный Content-Type для HTML
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    
    if (empty($adminPath) || $adminPath === '') {
        require __DIR__ . '/admin/index.php';
        return true;
    }
    
    // Обработка файлов админ панели
    if ($adminPath === 'logs.php') {
        require __DIR__ . '/admin/logs.php';
        return true;
    }
    
    // Если файл не найден, перенаправляем на index.php
    require __DIR__ . '/admin/index.php';
    return true;
}

// Обработка API
if (strpos($requestPath, 'api') === 0) {
    require __DIR__ . '/api/index.php';
    return true;
}

// Обработка статических файлов
if (file_exists(__DIR__ . '/' . $requestPath) && is_file(__DIR__ . '/' . $requestPath)) {
    return false; // Отдаем файл как есть
}

// Обработка корневого запроса
if (empty($requestPath) || $requestPath === '/') {
    if (file_exists(__DIR__ . '/index.html')) {
        require __DIR__ . '/index.html';
        return true;
    }
    require __DIR__ . '/api/index.php';
    return true;
}

// Если ничего не подошло, отдаем файл или 404
return false;

