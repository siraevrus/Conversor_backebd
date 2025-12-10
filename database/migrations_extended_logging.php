<?php

/**
 * Расширенное логирование - дополнительные таблицы и поля
 * Запуск: php database/migrations_extended_logging.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "Добавление расширенного логирования...\n\n";

    // Добавление новых полей в таблицу api_requests
    $columns = [
        'response_status' => "ALTER TABLE api_requests ADD COLUMN response_status INT DEFAULT 200",
        'response_time_ms' => "ALTER TABLE api_requests ADD COLUMN response_time_ms INT",
        'request_params' => "ALTER TABLE api_requests ADD COLUMN request_params TEXT",
        'response_size_bytes' => "ALTER TABLE api_requests ADD COLUMN response_size_bytes INT",
        'referer' => "ALTER TABLE api_requests ADD COLUMN referer VARCHAR(500)",
        'api_version' => "ALTER TABLE api_requests ADD COLUMN api_version VARCHAR(10) DEFAULT 'v1'",
        'error_message' => "ALTER TABLE api_requests ADD COLUMN error_message TEXT",
        'memory_usage_kb' => "ALTER TABLE api_requests ADD COLUMN memory_usage_kb INT"
    ];

    $indexes = [
        'idx_response_status' => "CREATE INDEX idx_response_status ON api_requests(response_status)",
        'idx_response_time' => "CREATE INDEX idx_response_time ON api_requests(response_time_ms)",
        'idx_created_at_status' => "CREATE INDEX idx_created_at_status ON api_requests(created_at, response_status)"
    ];

    foreach ($columns as $columnName => $sql) {
        try {
            // Проверяем существование колонки
            $checkQuery = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS 
                          WHERE TABLE_SCHEMA = DATABASE() 
                          AND TABLE_NAME = 'api_requests' 
                          AND COLUMN_NAME = :column_name";
            $stmt = $db->prepare($checkQuery);
            $stmt->execute([':column_name' => $columnName]);
            $exists = $stmt->fetch()['cnt'] > 0;
            
            if (!$exists) {
                $db->exec($sql);
            }
        } catch(PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) {
                echo "⚠ Ошибка добавления колонки $columnName: " . $e->getMessage() . "\n";
            }
        }
    }

    foreach ($indexes as $indexName => $sql) {
        try {
            // Проверяем существование индекса
            $checkQuery = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.STATISTICS 
                          WHERE TABLE_SCHEMA = DATABASE() 
                          AND TABLE_NAME = 'api_requests' 
                          AND INDEX_NAME = :index_name";
            $stmt = $db->prepare($checkQuery);
            $stmt->execute([':index_name' => $indexName]);
            $exists = $stmt->fetch()['cnt'] > 0;
            
            if (!$exists) {
                $db->exec($sql);
            }
        } catch(PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) {
                echo "⚠ Ошибка добавления индекса $indexName: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "✓ Таблица api_requests расширена успешно.\n";

    // Таблица для логирования конвертаций валют (для аналитики)
    $sql_conversions = "CREATE TABLE IF NOT EXISTS currency_conversions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id INT,
        amount DECIMAL(20, 8) NOT NULL,
        from_currency VARCHAR(3) NOT NULL,
        to_currency VARCHAR(3) NOT NULL,
        converted_amount DECIMAL(20, 8) NOT NULL,
        rate DECIMAL(20, 8) NOT NULL,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_device_id (device_id),
        INDEX idx_from_currency (from_currency),
        INDEX idx_to_currency (to_currency),
        INDEX idx_created_at (created_at),
        INDEX idx_currencies (from_currency, to_currency),
        FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql_conversions);
    echo "✓ Таблица currency_conversions создана успешно.\n";

    // Таблица для логирования обновлений курсов
    $sql_rate_updates = "CREATE TABLE IF NOT EXISTS rate_update_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        base_currency VARCHAR(3) NOT NULL,
        rates_count INT NOT NULL,
        update_source VARCHAR(50) DEFAULT 'cron',
        success BOOLEAN NOT NULL DEFAULT TRUE,
        error_message TEXT,
        execution_time_ms INT,
        api_response_time_ms INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_base_currency (base_currency),
        INDEX idx_success (success),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql_rate_updates);
    echo "✓ Таблица rate_update_logs создана успешно.\n";

    // Таблица для логирования ошибок и исключений
    $sql_errors = "CREATE TABLE IF NOT EXISTS error_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id INT,
        endpoint VARCHAR(255),
        error_type VARCHAR(100),
        error_message TEXT NOT NULL,
        stack_trace TEXT,
        request_params TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        http_status INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_device_id (device_id),
        INDEX idx_error_type (error_type),
        INDEX idx_created_at (created_at),
        INDEX idx_http_status (http_status),
        FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql_errors);
    echo "✓ Таблица error_logs создана успешно.\n";

    // Таблица для статистики использования API (агрегированные данные)
    $sql_stats = "CREATE TABLE IF NOT EXISTS api_statistics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        endpoint VARCHAR(255) NOT NULL,
        method VARCHAR(10) NOT NULL,
        total_requests INT DEFAULT 0,
        successful_requests INT DEFAULT 0,
        failed_requests INT DEFAULT 0,
        avg_response_time_ms DECIMAL(10, 2),
        total_response_size_bytes BIGINT DEFAULT 0,
        unique_devices INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_date_endpoint_method (date, endpoint, method),
        INDEX idx_date (date),
        INDEX idx_endpoint (endpoint)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql_stats);
    echo "✓ Таблица api_statistics создана успешно.\n";

    echo "\n✅ Расширенное логирование настроено успешно!\n";

} catch(PDOException $e) {
    echo "❌ Ошибка миграции: " . $e->getMessage() . "\n";
    exit(1);
}

