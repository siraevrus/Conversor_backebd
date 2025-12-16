<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/ExchangeRate.php';
require_once __DIR__ . '/../models/Device.php';
require_once __DIR__ . '/../services/ExchangeRateService.php';

/**
 * Простой роутер для API
 */
class ApiRouter {
    private $deviceModel;
    private $exchangeRateModel;
    private $exchangeRateService;

    public function __construct() {
        $this->deviceModel = new Device();
        $this->exchangeRateModel = new ExchangeRate();
        $this->exchangeRateService = new ExchangeRateService();
    }

    /**
     * Получение текущего пути запроса
     */
    private function getPath() {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($path, PHP_URL_PATH);
        $path = str_replace('/api', '', $path);
        $path = trim($path, '/');
        return $path;
    }

    /**
     * Получение метода запроса
     */
    private function getMethod() {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Получение данных из запроса
     */
    private function getRequestData() {
        $data = [];
        if ($this->getMethod() === 'POST' || $this->getMethod() === 'PUT') {
            $rawData = file_get_contents('php://input');
            $data = json_decode($rawData, true) ?? [];
        }
        return array_merge($data, $_GET);
    }

    /**
     * Логирование запроса
     */
    private function logRequest($deviceId, $endpoint) {
        // Логируем все запросы, даже без device_id
        $this->deviceModel->logRequest(
            $deviceId, // может быть null
            $endpoint,
            $this->getMethod(),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );
    }

    /**
     * Отправка JSON ответа
     */
    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }

    /**
     * Обработка запросов
     */
    public function handle() {
        $path = $this->getPath();
        $method = $this->getMethod();
        $data = $this->getRequestData();
        $deviceId = $data['device_id'] ?? $_SERVER['HTTP_X_DEVICE_ID'] ?? null;

        // Логирование запроса (всегда, даже без device_id)
        $this->logRequest($deviceId, $path);

        // Роутинг
        switch ($path) {
            case 'rates':
            case 'rates/':
                $this->handleRates($data);
                break;

            case 'convert':
            case 'convert/':
                $this->handleConvert($data);
                break;

            case 'device/register':
            case 'device/register/':
                $this->handleDeviceRegister($data);
                break;

            case 'device/info':
            case 'device/info/':
                $this->handleDeviceInfo($data);
                break;

            case 'update':
            case 'update/':
                $this->handleUpdate($data);
                break;

            case '':
            case '/':
                $this->handleRoot();
                break;

            default:
                $this->sendResponse([
                    'success' => false,
                    'error' => 'Endpoint not found'
                ], 404);
        }
    }

    /**
     * Главная страница API
     */
    private function handleRoot() {
        $this->sendResponse([
            'success' => true,
            'message' => 'Currency API v1',
            'endpoints' => [
                'GET /api/rates' => 'Получить все курсы валют',
                'GET /api/rates?base=USD&target=EUR' => 'Получить курс конкретной валюты',
                'GET /api/convert?amount=100&from=USD&to=EUR' => 'Конвертировать валюту',
                'POST /api/device/register' => 'Зарегистрировать устройство',
                'GET /api/device/info?device_id=xxx' => 'Получить информацию об устройстве',
                'POST /api/update' => 'Принудительное обновление курсов'
            ]
        ]);
    }

    /**
     * Получение курсов валют
     */
    private function handleRates($data) {
        $baseCurrency = strtoupper($data['base'] ?? 'USD');
        $targetCurrency = !empty($data['target']) ? strtoupper($data['target']) : null;

        if ($targetCurrency) {
            // Получение конкретного курса
            $rate = $this->exchangeRateModel->getRate($baseCurrency, $targetCurrency);
            
            if ($rate) {
                $this->sendResponse([
                    'success' => true,
                    'base' => $baseCurrency,
                    'target' => $targetCurrency,
                    'rate' => (float)$rate['rate'],
                    'last_updated' => $rate['last_updated']
                ]);
            } else {
                $this->sendResponse([
                    'success' => false,
                    'error' => 'Курс не найден'
                ], 404);
            }
        } else {
            // Получение всех курсов
            $rates = $this->exchangeRateModel->getAllRates($baseCurrency);
            
            $ratesArray = [];
            foreach ($rates as $rate) {
                $ratesArray[$rate['target_currency']] = [
                    'rate' => (float)$rate['rate'],
                    'last_updated' => $rate['last_updated']
                ];
            }

            $this->sendResponse([
                'success' => true,
                'base' => $baseCurrency,
                'rates' => $ratesArray,
                'count' => count($ratesArray)
            ]);
        }
    }

    /**
     * Конвертация валют
     */
    private function handleConvert($data) {
        $amount = floatval($data['amount'] ?? 0);
        $from = strtoupper($data['from'] ?? 'USD');
        $to = strtoupper($data['to'] ?? 'EUR');
        $base = strtoupper($data['base'] ?? 'USD');

        if ($amount <= 0) {
            $this->sendResponse([
                'success' => false,
                'error' => 'Неверная сумма'
            ], 400);
        }

        $result = $this->exchangeRateModel->convert($amount, $from, $to, $base);

        if ($result) {
            $this->sendResponse([
                'success' => true,
                'amount' => $amount,
                'from' => $from,
                'to' => $to,
                'converted_amount' => round($result['amount'], 2),
                'rate' => round($result['rate'], 8),
                'last_updated' => $result['last_updated']
            ]);
        } else {
            $this->sendResponse([
                'success' => false,
                'error' => 'Не удалось выполнить конвертацию. Проверьте наличие курсов для указанных валют.'
            ], 404);
        }
    }

    /**
     * Регистрация устройства
     */
    private function handleDeviceRegister($data) {
        $deviceId = $data['device_id'] ?? null;

        if (!$deviceId) {
            $this->sendResponse([
                'success' => false,
                'error' => 'device_id обязателен'
            ], 400);
        }

        $result = $this->deviceModel->registerOrUpdate($deviceId, [
            'device_name' => $data['device_name'] ?? null,
            'device_type' => $data['device_type'] ?? null,
            'platform' => $data['platform'] ?? null,
            'app_version' => $data['app_version'] ?? null
        ]);

        $this->sendResponse($result, $result['success'] ? 200 : 500);
    }

    /**
     * Информация об устройстве
     */
    private function handleDeviceInfo($data) {
        $deviceId = $data['device_id'] ?? null;

        if (!$deviceId) {
            $this->sendResponse([
                'success' => false,
                'error' => 'device_id обязателен'
            ], 400);
        }

        $device = $this->deviceModel->getDevice($deviceId);

        if ($device) {
            $this->sendResponse([
                'success' => true,
                'device' => $device
            ]);
        } else {
            $this->sendResponse([
                'success' => false,
                'error' => 'Устройство не найдено'
            ], 404);
        }
    }

    /**
     * Принудительное обновление курсов
     */
    private function handleUpdate($data) {
        $result = $this->exchangeRateService->updateRates();
        $this->sendResponse($result, $result['success'] ? 200 : 500);
    }
}

// Запуск роутера
try {
    $router = new ApiRouter();
    $router->handle();
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

