<?php

require_once __DIR__ . '/../config/database.php';

class Device {
    private $conn;
    private $table_name = "devices";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Регистрация или обновление устройства
     */
    public function registerOrUpdate($deviceId, $deviceData = []) {
        try {
            $deviceName = $deviceData['device_name'] ?? null;
            $deviceType = $deviceData['device_type'] ?? null;
            $platform = $deviceData['platform'] ?? null;
            $appVersion = $deviceData['app_version'] ?? null;
            $lastActive = date('Y-m-d H:i:s');

            // Проверяем, существует ли устройство
            $checkQuery = "SELECT id FROM " . $this->table_name . " WHERE device_id = :device_id";
            $stmt = $this->conn->prepare($checkQuery);
            $stmt->execute([':device_id' => $deviceId]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Обновляем существующее устройство
                $updateQuery = "UPDATE " . $this->table_name . " 
                    SET device_name = :device_name,
                        device_type = :device_type,
                        platform = :platform,
                        app_version = :app_version,
                        last_active = :last_active
                    WHERE device_id = :device_id";

                $stmt = $this->conn->prepare($updateQuery);
                $stmt->execute([
                    ':device_id' => $deviceId,
                    ':device_name' => $deviceName,
                    ':device_type' => $deviceType,
                    ':platform' => $platform,
                    ':app_version' => $appVersion,
                    ':last_active' => $lastActive
                ]);

                return [
                    'success' => true,
                    'message' => 'Устройство обновлено',
                    'device_id' => $existing['id']
                ];
            } else {
                // Создаем новое устройство
                $insertQuery = "INSERT INTO " . $this->table_name . " 
                    (device_id, device_name, device_type, platform, app_version, last_active) 
                    VALUES (:device_id, :device_name, :device_type, :platform, :app_version, :last_active)";

                $stmt = $this->conn->prepare($insertQuery);
                $stmt->execute([
                    ':device_id' => $deviceId,
                    ':device_name' => $deviceName,
                    ':device_type' => $deviceType,
                    ':platform' => $platform,
                    ':app_version' => $appVersion,
                    ':last_active' => $lastActive
                ]);

                return [
                    'success' => true,
                    'message' => 'Устройство зарегистрировано',
                    'device_id' => $this->conn->lastInsertId()
                ];
            }

        } catch(PDOException $e) {
            error_log("Ошибка регистрации устройства: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка регистрации устройства'
            ];
        }
    }

    /**
     * Получение информации об устройстве
     */
    public function getDevice($deviceId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE device_id = :device_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':device_id' => $deviceId]);

        return $stmt->fetch();
    }

    /**
     * Получение всех устройств
     */
    public function getAllDevices($limit = 100, $offset = 0) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  ORDER BY last_active DESC 
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Логирование API запроса
     */
    public function logRequest($deviceId, $endpoint, $method, $ipAddress = null, $userAgent = null) {
        try {
            // Получаем ID устройства
            $device = $this->getDevice($deviceId);
            $deviceDbId = $device ? $device['id'] : null;

            $query = "INSERT INTO api_requests 
                (device_id, endpoint, method, ip_address, user_agent) 
                VALUES (:device_id, :endpoint, :method, :ip_address, :user_agent)";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':device_id' => $deviceDbId,
                ':endpoint' => $endpoint,
                ':method' => $method,
                ':ip_address' => $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            return true;
        } catch(PDOException $e) {
            error_log("Ошибка логирования запроса: " . $e->getMessage());
            return false;
        }
    }
}

