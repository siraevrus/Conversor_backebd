<?php

/**
 * Пример интеграции расширенного логирования в API
 * 
 * Этот файл показывает, как использовать Logger в вашем API роутере
 */

require_once __DIR__ . '/models/Logger.php';

// Пример использования в API роутере

class ApiRouterWithLogging {
    private $logger;

    public function __construct() {
        $this->logger = new Logger();
    }

    /**
     * Пример обработки запроса с логированием
     */
    public function handleRequest($endpoint, $method, $deviceId, $requestData) {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        try {
            // Обработка запроса
            $response = $this->processRequest($endpoint, $method, $requestData);
            $responseStatus = 200;
            
            // Логирование успешного запроса
            $this->logger->logRequest(
                $deviceId,
                $endpoint,
                $method,
                $requestData,
                $responseStatus,
                $response
            );
            
            // Обновление статистики
            $responseTime = round((microtime(true) - $startTime) * 1000);
            $responseSize = strlen(json_encode($response));
            $this->logger->updateStatistics(
                $endpoint,
                $method,
                true,
                $responseTime,
                $responseSize,
                $deviceId
            );
            
            return $response;
            
        } catch(Exception $e) {
            $responseStatus = 500;
            
            // Логирование ошибки
            $this->logger->logError(
                $deviceId,
                $endpoint,
                get_class($e),
                $e->getMessage(),
                $e->getTraceAsString(),
                $requestData,
                $responseStatus
            );
            
            // Логирование неуспешного запроса
            $this->logger->logRequest(
                $deviceId,
                $endpoint,
                $method,
                $requestData,
                $responseStatus,
                ['error' => $e->getMessage()]
            );
            
            // Обновление статистики
            $responseTime = round((microtime(true) - $startTime) * 1000);
            $this->logger->updateStatistics(
                $endpoint,
                $method,
                false,
                $responseTime,
                0,
                $deviceId
            );
            
            throw $e;
        }
    }

    /**
     * Пример логирования конвертации валют
     */
    public function handleConvert($amount, $from, $to, $deviceId) {
        // Выполнение конвертации
        $result = $this->convertCurrency($amount, $from, $to);
        
        if ($result['success']) {
            // Логирование конвертации для аналитики
            $this->logger->logConversion(
                $deviceId,
                $amount,
                $from,
                $to,
                $result['converted_amount'],
                $result['rate']
            );
        }
        
        return $result;
    }

    private function processRequest($endpoint, $method, $requestData) {
        // Ваша логика обработки запроса
        return ['success' => true, 'data' => []];
    }

    private function convertCurrency($amount, $from, $to) {
        // Ваша логика конвертации
        return [
            'success' => true,
            'converted_amount' => 85.00,
            'rate' => 0.85
        ];
    }
}

/**
 * Пример использования в существующем коде:
 * 
 * 1. В начале обработки запроса создайте Logger:
 *    $logger = new Logger();
 * 
 * 2. После успешной обработки залогируйте запрос:
 *    $logger->logRequest($deviceId, $endpoint, $method, $requestData, 200, $response);
 * 
 * 3. При конвертации валют логируйте отдельно:
 *    $logger->logConversion($deviceId, $amount, $from, $to, $converted, $rate);
 * 
 * 4. При ошибках логируйте в error_logs:
 *    $logger->logError($deviceId, $endpoint, 'Exception', $e->getMessage(), ...);
 * 
 * 5. Обновляйте статистику:
 *    $logger->updateStatistics($endpoint, $method, $success, $responseTime, $responseSize, $deviceId);
 */

