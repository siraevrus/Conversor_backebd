<?php

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Создание таблицы для курсов валют
    $sql_rates = "CREATE TABLE IF NOT EXISTS exchange_rates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        base_currency VARCHAR(3) NOT NULL DEFAULT 'USD',
        target_currency VARCHAR(3) NOT NULL,
        rate DECIMAL(20, 8) NOT NULL,
        last_updated DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_base_currency (base_currency),
        INDEX idx_target_currency (target_currency),
        INDEX idx_last_updated (last_updated),
        UNIQUE KEY unique_rate (base_currency, target_currency)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql_rates);
    echo "Таблица exchange_rates создана успешно.\n";

    // Создание таблицы для устройств
    $sql_devices = "CREATE TABLE IF NOT EXISTS devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id VARCHAR(255) NOT NULL UNIQUE,
        device_name VARCHAR(255),
        device_type VARCHAR(50),
        platform VARCHAR(50),
        app_version VARCHAR(50),
        last_active DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_device_id (device_id),
        INDEX idx_last_active (last_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql_devices);
    echo "Таблица devices создана успешно.\n";

    // Создание таблицы для истории запросов (опционально)
    $sql_history = "CREATE TABLE IF NOT EXISTS api_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id INT,
        endpoint VARCHAR(255) NOT NULL,
        method VARCHAR(10) NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_device_id (device_id),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql_history);
    echo "Таблица api_requests создана успешно.\n";

    echo "\nМиграция завершена успешно!\n";

} catch(PDOException $e) {
    echo "Ошибка миграции: " . $e->getMessage() . "\n";
    exit(1);
}

