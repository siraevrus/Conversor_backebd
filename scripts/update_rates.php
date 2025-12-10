#!/usr/bin/env php
<?php

/**
 * Скрипт для обновления курсов валют
 * Запускается по cron каждые 60 минут
 * 
 * Пример настройки cron:
 * 0 * * * * /usr/bin/php /path/to/currency_api/scripts/update_rates.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../services/ExchangeRateService.php';
require_once __DIR__ . '/../models/Logger.php';

try {
    $service = new ExchangeRateService();
    $logger = new Logger();
    
    $startTime = microtime(true);
    echo "[" . date('Y-m-d H:i:s') . "] Начало обновления курсов валют...\n";
    
    $apiStartTime = microtime(true);
    $result = $service->updateRates();
    $apiResponseTime = round((microtime(true) - $apiStartTime) * 1000);
    $executionTime = round((microtime(true) - $startTime) * 1000);
    
    if ($result['success']) {
        echo "[" . date('Y-m-d H:i:s') . "] Курсы успешно обновлены.\n";
        echo "Базовая валюта: " . $result['base_currency'] . "\n";
        echo "Количество курсов: " . $result['rates_count'] . "\n";
        echo "Время обновления: " . $result['updated_at'] . "\n";
        echo "Время выполнения: {$executionTime}ms\n";
        
        // Логирование успешного обновления
        $logger->logRateUpdate(
            $result['base_currency'],
            $result['rates_count'],
            true,
            null,
            $executionTime,
            $apiResponseTime,
            'cron'
        );
        
        exit(0);
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Ошибка обновления: " . $result['error'] . "\n";
        
        // Логирование ошибки обновления
        $logger->logRateUpdate(
            'USD',
            0,
            false,
            $result['error'],
            $executionTime,
            $apiResponseTime,
            'cron'
        );
        
        exit(1);
    }
    
} catch(Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Критическая ошибка: " . $e->getMessage() . "\n";
    
    // Логирование критической ошибки
    try {
        $logger = new Logger();
        $logger->logRateUpdate(
            'USD',
            0,
            false,
            $e->getMessage(),
            null,
            null,
            'cron'
        );
    } catch(Exception $logError) {
        // Игнорируем ошибки логирования
    }
    
    exit(1);
}

