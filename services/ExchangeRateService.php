<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/ExchangeRate.php';

class ExchangeRateService {
    private $apiUrl;
    private $exchangeRateModel;

    public function __construct() {
        $this->apiUrl = EXCHANGE_RATE_API_URL;
        $this->exchangeRateModel = new ExchangeRate();
    }

    /**
     * Получение курсов валют с внешнего API
     */
    public function fetchRatesFromAPI() {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new Exception("CURL Error: " . $error);
            }

            if ($httpCode !== 200) {
                throw new Exception("HTTP Error: " . $httpCode);
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON Error: " . json_last_error_msg());
            }

            if (!isset($data['result']) || $data['result'] !== 'success') {
                throw new Exception("API Error: " . ($data['error-type'] ?? 'Unknown error'));
            }

            return [
                'success' => true,
                'base_currency' => $data['base_code'] ?? 'USD',
                'rates' => $data['conversion_rates'] ?? [],
                'time_last_update_utc' => $data['time_last_update_utc'] ?? null
            ];

        } catch(Exception $e) {
            error_log("Ошибка получения курсов: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Обновление курсов валют
     */
    public function updateRates() {
        $apiData = $this->fetchRatesFromAPI();

        if (!$apiData['success']) {
            return $apiData;
        }

        $saved = $this->exchangeRateModel->saveRates(
            $apiData['base_currency'],
            $apiData['rates']
        );

        if ($saved) {
            return [
                'success' => true,
                'message' => 'Курсы валют успешно обновлены',
                'base_currency' => $apiData['base_currency'],
                'rates_count' => count($apiData['rates']),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Ошибка сохранения курсов в базу данных'
            ];
        }
    }

    /**
     * Проверка необходимости обновления
     */
    public function shouldUpdate() {
        return !$this->exchangeRateModel->isRatesUpToDate('USD', UPDATE_INTERVAL_MINUTES);
    }
}

