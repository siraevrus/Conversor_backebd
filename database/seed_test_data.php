<?php

/**
 * Скрипт для заполнения базы данных тестовыми данными
 * Запуск: php database/seed_test_data.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "🌱 Начало заполнения тестовыми данными...\n\n";

    // Тестовые устройства
    echo "📱 Создание тестовых устройств...\n";
    $devices = [
        ['device_id' => 'ios-device-001', 'device_name' => 'iPhone 13 Pro', 'device_type' => 'mobile', 'platform' => 'iOS', 'app_version' => '1.0.0'],
        ['device_id' => 'android-device-002', 'device_name' => 'Samsung Galaxy S21', 'device_type' => 'mobile', 'platform' => 'Android', 'app_version' => '1.0.1'],
        ['device_id' => 'ios-device-003', 'device_name' => 'iPhone 12', 'device_type' => 'mobile', 'platform' => 'iOS', 'app_version' => '1.0.0'],
        ['device_id' => 'android-device-004', 'device_name' => 'Google Pixel 6', 'device_type' => 'mobile', 'platform' => 'Android', 'app_version' => '1.0.2'],
        ['device_id' => 'web-device-005', 'device_name' => 'Chrome Browser', 'device_type' => 'desktop', 'platform' => 'Web', 'app_version' => '1.0.0'],
    ];

    $deviceIds = [];
    foreach ($devices as $device) {
        $query = "INSERT INTO devices (device_id, device_name, device_type, platform, app_version, last_active, created_at) 
                  VALUES (:device_id, :device_name, :device_type, :platform, :app_version, NOW(), DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 30) DAY))
                  ON DUPLICATE KEY UPDATE last_active = NOW()";
        $stmt = $db->prepare($query);
        $stmt->execute($device);
        $deviceIds[] = $device['device_id'];
        echo "  ✓ Устройство: {$device['device_id']}\n";
    }

    // Получаем ID устройств из базы
    $deviceDbIds = [];
    foreach ($deviceIds as $deviceId) {
        $query = "SELECT id FROM devices WHERE device_id = :device_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':device_id' => $deviceId]);
        $result = $stmt->fetch();
        if ($result) {
            $deviceDbIds[$deviceId] = $result['id'];
        }
    }

    // Тестовые endpoints
    $endpoints = [
        '/api/rates',
        '/api/rates',
        '/api/convert',
        '/api/device/register',
        '/api/device/info',
        '/api/convert',
        '/api/rates',
    ];

    $methods = ['GET', 'POST', 'GET', 'POST', 'GET', 'GET', 'GET'];
    $statuses = [200, 200, 200, 200, 200, 404, 500];
    $currencies = ['USD', 'EUR', 'RUB', 'GBP', 'JPY', 'CNY', 'AED', 'CAD', 'AUD', 'CHF'];

    echo "\n📊 Создание тестовых запросов API...\n";
    $requestParams = [
        ['base' => 'USD', 'target' => 'EUR'],
        ['base' => 'USD'],
        ['amount' => 100, 'from' => 'USD', 'to' => 'EUR'],
        ['device_id' => 'test-device', 'device_name' => 'Test'],
        ['device_id' => 'ios-device-001'],
        ['amount' => 50, 'from' => 'RUB', 'to' => 'USD'],
        ['base' => 'INVALID'],
    ];

    for ($i = 0; $i < 200; $i++) {
        $deviceId = $deviceIds[array_rand($deviceIds)];
        $deviceDbId = $deviceDbIds[$deviceId] ?? null;
        $endpoint = $endpoints[array_rand($endpoints)];
        $method = $methods[array_rand($methods)];
        $status = $statuses[array_rand($statuses)];
        $responseTime = rand(10, 500);
        $responseSize = rand(100, 5000);
        $params = json_encode($requestParams[array_rand($requestParams)]);
        $referer = rand(0, 10) > 7 ? 'https://example.com' : null;
        $memoryUsage = rand(100, 2000);

        // Создаем запросы за последние 7 дней
        $daysAgo = rand(0, 7);
        $hoursAgo = rand(0, 23);
        $minutesAgo = rand(0, 59);
        $createdAt = date('Y-m-d H:i:s', strtotime("-$daysAgo days -$hoursAgo hours -$minutesAgo minutes"));

        $query = "INSERT INTO api_requests 
            (device_id, endpoint, method, ip_address, user_agent, response_status, 
             response_time_ms, request_params, response_size_bytes, referer, 
             api_version, memory_usage_kb, created_at) 
            VALUES 
            (:device_id, :endpoint, :method, :ip_address, :user_agent, :response_status,
             :response_time_ms, :request_params, :response_size_bytes, :referer,
             :api_version, :memory_usage_kb, :created_at)";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':device_id' => $deviceDbId,
            ':endpoint' => $endpoint,
            ':method' => $method,
            ':ip_address' => '192.168.1.' . rand(1, 255),
            ':user_agent' => 'Mozilla/5.0 (Test Browser)',
            ':response_status' => $status,
            ':response_time_ms' => $responseTime,
            ':request_params' => $params,
            ':response_size_bytes' => $responseSize,
            ':referer' => $referer,
            ':api_version' => 'v1',
            ':memory_usage_kb' => $memoryUsage,
            ':created_at' => $createdAt
        ]);
    }
    echo "  ✓ Создано 200 тестовых запросов\n";

    // Тестовые конвертации
    echo "\n💱 Создание тестовых конвертаций...\n";
    $conversionPairs = [
        ['USD', 'EUR', 0.85],
        ['USD', 'RUB', 75.5],
        ['EUR', 'USD', 1.18],
        ['RUB', 'USD', 0.013],
        ['GBP', 'EUR', 1.15],
        ['USD', 'JPY', 110.5],
        ['EUR', 'RUB', 88.8],
    ];

    for ($i = 0; $i < 150; $i++) {
        $pair = $conversionPairs[array_rand($conversionPairs)];
        $deviceId = $deviceIds[array_rand($deviceIds)];
        $deviceDbId = $deviceDbIds[$deviceId] ?? null;
        $amount = rand(10, 10000);
        $convertedAmount = $amount * $pair[2];
        $rate = $pair[2];

        $daysAgo = rand(0, 7);
        $hoursAgo = rand(0, 23);
        $minutesAgo = rand(0, 59);
        $createdAt = date('Y-m-d H:i:s', strtotime("-$daysAgo days -$hoursAgo hours -$minutesAgo minutes"));

        $query = "INSERT INTO currency_conversions 
            (device_id, amount, from_currency, to_currency, converted_amount, rate, ip_address, created_at) 
            VALUES 
            (:device_id, :amount, :from_currency, :to_currency, :converted_amount, :rate, :ip_address, :created_at)";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':device_id' => $deviceDbId,
            ':amount' => $amount,
            ':from_currency' => $pair[0],
            ':to_currency' => $pair[1],
            ':converted_amount' => $convertedAmount,
            ':rate' => $rate,
            ':ip_address' => '192.168.1.' . rand(1, 255),
            ':created_at' => $createdAt
        ]);
    }
    echo "  ✓ Создано 150 тестовых конвертаций\n";

    // Тестовые ошибки
    echo "\n❌ Создание тестовых ошибок...\n";
    $errorTypes = ['PDOException', 'Exception', 'InvalidArgumentException', 'RuntimeException'];
    $errorMessages = [
        'Database connection failed',
        'Invalid currency code',
        'Rate not found',
        'Device not found',
        'Invalid request parameters',
        'Timeout error',
        'Memory limit exceeded',
    ];

    for ($i = 0; $i < 30; $i++) {
        $deviceId = $deviceIds[array_rand($deviceIds)];
        $deviceDbId = $deviceDbIds[$deviceId] ?? null;
        $endpoint = $endpoints[array_rand($endpoints)];
        $errorType = $errorTypes[array_rand($errorTypes)];
        $errorMessage = $errorMessages[array_rand($errorMessages)];
        $httpStatus = [400, 404, 500][array_rand([400, 404, 500])];

        $daysAgo = rand(0, 7);
        $hoursAgo = rand(0, 23);
        $createdAt = date('Y-m-d H:i:s', strtotime("-$daysAgo days -$hoursAgo hours"));

        $query = "INSERT INTO error_logs 
            (device_id, endpoint, error_type, error_message, stack_trace, request_params,
             ip_address, user_agent, http_status, created_at) 
            VALUES 
            (:device_id, :endpoint, :error_type, :error_message, :stack_trace, :request_params,
             :ip_address, :user_agent, :http_status, :created_at)";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':device_id' => $deviceDbId,
            ':endpoint' => $endpoint,
            ':error_type' => $errorType,
            ':error_message' => $errorMessage,
            ':stack_trace' => "Stack trace:\n#0 test.php(10): function()\n#1 test.php(20): other_function()",
            ':request_params' => json_encode(['test' => 'data']),
            ':ip_address' => '192.168.1.' . rand(1, 255),
            ':user_agent' => 'Mozilla/5.0 (Test Browser)',
            ':http_status' => $httpStatus,
            ':created_at' => $createdAt
        ]);
    }
    echo "  ✓ Создано 30 тестовых ошибок\n";

    // Тестовые обновления курсов
    echo "\n🔄 Создание истории обновлений курсов...\n";
    $sources = ['cron', 'manual', 'api'];
    
    for ($i = 0; $i < 20; $i++) {
        $success = rand(0, 10) > 1; // 90% успешных
        $ratesCount = $success ? rand(150, 170) : 0;
        $executionTime = $success ? rand(500, 3000) : null;
        $apiResponseTime = $success ? rand(200, 1500) : null;
        $errorMessage = $success ? null : 'API timeout error';
        $source = $sources[array_rand($sources)];

        $daysAgo = rand(0, 14);
        $hoursAgo = rand(0, 23);
        $createdAt = date('Y-m-d H:i:s', strtotime("-$daysAgo days -$hoursAgo hours"));

        $query = "INSERT INTO rate_update_logs 
            (base_currency, rates_count, update_source, success, error_message, 
             execution_time_ms, api_response_time_ms, created_at) 
            VALUES 
            (:base_currency, :rates_count, :update_source, :success, :error_message,
             :execution_time_ms, :api_response_time_ms, :created_at)";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':base_currency' => 'USD',
            ':rates_count' => $ratesCount,
            ':update_source' => $source,
            ':success' => $success ? 1 : 0,
            ':error_message' => $errorMessage,
            ':execution_time_ms' => $executionTime,
            ':api_response_time_ms' => $apiResponseTime,
            ':created_at' => $createdAt
        ]);
    }
    echo "  ✓ Создано 20 записей обновлений курсов\n";

    // Тестовая статистика
    echo "\n📈 Создание тестовой статистики...\n";
    $today = date('Y-m-d');
    $endpointsForStats = ['/api/rates', '/api/convert', '/api/device/register'];
    
    foreach ($endpointsForStats as $endpoint) {
        $totalRequests = rand(50, 200);
        $successfulRequests = (int)($totalRequests * 0.9);
        $failedRequests = $totalRequests - $successfulRequests;
        $avgResponseTime = rand(50, 300);
        $totalResponseSize = rand(100000, 1000000);
        $uniqueDevices = rand(3, 5);

        $query = "INSERT INTO api_statistics 
            (date, endpoint, method, total_requests, successful_requests, failed_requests,
             avg_response_time_ms, total_response_size_bytes, unique_devices, created_at) 
            VALUES 
            (:date, :endpoint, :method, :total_requests, :successful_requests, :failed_requests,
             :avg_response_time_ms, :total_response_size_bytes, :unique_devices, NOW())
            ON DUPLICATE KEY UPDATE
            total_requests = total_requests + VALUES(total_requests),
            successful_requests = successful_requests + VALUES(successful_requests),
            failed_requests = failed_requests + VALUES(failed_requests)";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':date' => $today,
            ':endpoint' => $endpoint,
            ':method' => 'GET',
            ':total_requests' => $totalRequests,
            ':successful_requests' => $successfulRequests,
            ':failed_requests' => $failedRequests,
            ':avg_response_time_ms' => $avgResponseTime,
            ':total_response_size_bytes' => $totalResponseSize,
            ':unique_devices' => $uniqueDevices
        ]);
    }
    echo "  ✓ Создана тестовая статистика\n";

    echo "\n✅ Тестовые данные успешно созданы!\n";
    echo "\n📊 Итого создано:\n";
    echo "  - Устройств: " . count($devices) . "\n";
    echo "  - Запросов API: 200\n";
    echo "  - Конвертаций: 150\n";
    echo "  - Ошибок: 30\n";
    echo "  - Обновлений курсов: 20\n";
    echo "  - Статистики: " . count($endpointsForStats) . " записей\n";
    echo "\n🌐 Откройте админ панель: http://localhost:8000/admin/\n";

} catch(PDOException $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

