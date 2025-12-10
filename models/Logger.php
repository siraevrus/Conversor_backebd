<?php

require_once __DIR__ . '/../config/database.php';

class Logger {
    private $conn;
    private $startTime;
    private $startMemory;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
    }

    /**
     * Логирование API запроса с расширенной информацией
     */
    public function logRequest($deviceId, $endpoint, $method, $requestData = [], $responseStatus = 200, $responseData = null) {
        try {
            // Получаем ID устройства из базы
            $deviceDbId = null;
            if ($deviceId) {
                $deviceQuery = "SELECT id FROM devices WHERE device_id = :device_id LIMIT 1";
                $stmt = $this->conn->prepare($deviceQuery);
                $stmt->execute([':device_id' => $deviceId]);
                $device = $stmt->fetch();
                $deviceDbId = $device ? $device['id'] : null;
            }

            // Вычисляем время выполнения
            $responseTime = round((microtime(true) - $this->startTime) * 1000);
            
            // Вычисляем использование памяти
            $memoryUsage = round((memory_get_usage() - $this->startMemory) / 1024);
            
            // Размер ответа
            $responseSize = $responseData ? strlen(json_encode($responseData)) : 0;

            // Получаем дополнительные данные
            $ipAddress = $this->getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $referer = $_SERVER['HTTP_REFERER'] ?? null;
            $apiVersion = 'v1';

            // Сериализуем параметры запроса (ограничиваем размер)
            $requestParams = json_encode($requestData);
            if (strlen($requestParams) > 5000) {
                $requestParams = substr($requestParams, 0, 5000) . '...';
            }

            $query = "INSERT INTO api_requests 
                (device_id, endpoint, method, ip_address, user_agent, 
                 response_status, response_time_ms, request_params, 
                 response_size_bytes, referer, api_version, memory_usage_kb, created_at) 
                VALUES 
                (:device_id, :endpoint, :method, :ip_address, :user_agent,
                 :response_status, :response_time_ms, :request_params,
                 :response_size_bytes, :referer, :api_version, :memory_usage_kb, NOW())";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':device_id' => $deviceDbId,
                ':endpoint' => $endpoint,
                ':method' => $method,
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
                ':response_status' => $responseStatus,
                ':response_time_ms' => $responseTime,
                ':request_params' => $requestParams,
                ':response_size_bytes' => $responseSize,
                ':referer' => $referer ? substr($referer, 0, 500) : null,
                ':api_version' => $apiVersion,
                ':memory_usage_kb' => $memoryUsage
            ]);

            return true;
        } catch(PDOException $e) {
            error_log("Ошибка логирования запроса: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Логирование конвертации валют
     */
    public function logConversion($deviceId, $amount, $fromCurrency, $toCurrency, $convertedAmount, $rate) {
        try {
            $deviceDbId = null;
            if ($deviceId) {
                $deviceQuery = "SELECT id FROM devices WHERE device_id = :device_id LIMIT 1";
                $stmt = $this->conn->prepare($deviceQuery);
                $stmt->execute([':device_id' => $deviceId]);
                $device = $stmt->fetch();
                $deviceDbId = $device ? $device['id'] : null;
            }

            $query = "INSERT INTO currency_conversions 
                (device_id, amount, from_currency, to_currency, converted_amount, rate, ip_address, created_at) 
                VALUES 
                (:device_id, :amount, :from_currency, :to_currency, :converted_amount, :rate, :ip_address, NOW())";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':device_id' => $deviceDbId,
                ':amount' => $amount,
                ':from_currency' => $fromCurrency,
                ':to_currency' => $toCurrency,
                ':converted_amount' => $convertedAmount,
                ':rate' => $rate,
                ':ip_address' => $this->getClientIp()
            ]);

            return true;
        } catch(PDOException $e) {
            error_log("Ошибка логирования конвертации: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Логирование обновления курсов
     */
    public function logRateUpdate($baseCurrency, $ratesCount, $success, $errorMessage = null, $executionTime = null, $apiResponseTime = null, $source = 'cron') {
        try {
            $query = "INSERT INTO rate_update_logs 
                (base_currency, rates_count, update_source, success, error_message, execution_time_ms, api_response_time_ms, created_at) 
                VALUES 
                (:base_currency, :rates_count, :update_source, :success, :error_message, :execution_time_ms, :api_response_time_ms, NOW())";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':base_currency' => $baseCurrency,
                ':rates_count' => $ratesCount,
                ':update_source' => $source,
                ':success' => $success ? 1 : 0,
                ':error_message' => $errorMessage,
                ':execution_time_ms' => $executionTime,
                ':api_response_time_ms' => $apiResponseTime
            ]);

            return true;
        } catch(PDOException $e) {
            error_log("Ошибка логирования обновления курсов: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Логирование ошибок
     */
    public function logError($deviceId, $endpoint, $errorType, $errorMessage, $stackTrace = null, $requestParams = null, $httpStatus = 500) {
        try {
            $deviceDbId = null;
            if ($deviceId) {
                $deviceQuery = "SELECT id FROM devices WHERE device_id = :device_id LIMIT 1";
                $stmt = $this->conn->prepare($deviceQuery);
                $stmt->execute([':device_id' => $deviceId]);
                $device = $stmt->fetch();
                $deviceDbId = $device ? $device['id'] : null;
            }

            $requestParamsJson = $requestParams ? json_encode($requestParams) : null;
            if ($requestParamsJson && strlen($requestParamsJson) > 5000) {
                $requestParamsJson = substr($requestParamsJson, 0, 5000) . '...';
            }

            $query = "INSERT INTO error_logs 
                (device_id, endpoint, error_type, error_message, stack_trace, request_params, 
                 ip_address, user_agent, http_status, created_at) 
                VALUES 
                (:device_id, :endpoint, :error_type, :error_message, :stack_trace, :request_params,
                 :ip_address, :user_agent, :http_status, NOW())";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':device_id' => $deviceDbId,
                ':endpoint' => $endpoint,
                ':error_type' => $errorType,
                ':error_message' => $errorMessage,
                ':stack_trace' => $stackTrace,
                ':request_params' => $requestParamsJson,
                ':ip_address' => $this->getClientIp(),
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':http_status' => $httpStatus
            ]);

            return true;
        } catch(PDOException $e) {
            error_log("Ошибка логирования ошибки: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Обновление статистики (агрегированные данные)
     */
    public function updateStatistics($endpoint, $method, $success, $responseTime, $responseSize, $deviceId = null) {
        try {
            $date = date('Y-m-d');
            
            // Проверяем существование записи
            $checkQuery = "SELECT id FROM api_statistics 
                          WHERE date = :date AND endpoint = :endpoint AND method = :method";
            $stmt = $this->conn->prepare($checkQuery);
            $stmt->execute([
                ':date' => $date,
                ':endpoint' => $endpoint,
                ':method' => $method
            ]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Обновляем существующую запись
                $updateQuery = "UPDATE api_statistics SET
                    total_requests = total_requests + 1,
                    successful_requests = successful_requests + :success_increment,
                    failed_requests = failed_requests + :fail_increment,
                    avg_response_time_ms = (avg_response_time_ms * (total_requests - 1) + :response_time) / total_requests,
                    total_response_size_bytes = total_response_size_bytes + :response_size
                    WHERE id = :id";
                
                $stmt = $this->conn->prepare($updateQuery);
                $stmt->execute([
                    ':success_increment' => $success ? 1 : 0,
                    ':fail_increment' => $success ? 0 : 1,
                    ':response_time' => $responseTime,
                    ':response_size' => $responseSize,
                    ':id' => $existing['id']
                ]);
            } else {
                // Создаем новую запись
                $insertQuery = "INSERT INTO api_statistics 
                    (date, endpoint, method, total_requests, successful_requests, failed_requests,
                     avg_response_time_ms, total_response_size_bytes, unique_devices) 
                    VALUES 
                    (:date, :endpoint, :method, 1, :successful, :failed,
                     :avg_time, :total_size, :unique_devices)";
                
                $stmt = $this->conn->prepare($insertQuery);
                $stmt->execute([
                    ':date' => $date,
                    ':endpoint' => $endpoint,
                    ':method' => $method,
                    ':successful' => $success ? 1 : 0,
                    ':failed' => $success ? 0 : 1,
                    ':avg_time' => $responseTime,
                    ':total_size' => $responseSize,
                    ':unique_devices' => $deviceId ? 1 : 0
                ]);
            }

            return true;
        } catch(PDOException $e) {
            error_log("Ошибка обновления статистики: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получение IP адреса клиента
     */
    private function getClientIp() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

