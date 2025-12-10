<?php

require_once __DIR__ . '/../config/database.php';

class ExchangeRate {
    private $conn;
    private $table_name = "exchange_rates";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Сохранение курсов валют в базу данных
     */
    public function saveRates($baseCurrency, $rates) {
        try {
            $this->conn->beginTransaction();

            $lastUpdated = date('Y-m-d H:i:s');

            // Удаляем старые курсы для базовой валюты
            $deleteQuery = "DELETE FROM " . $this->table_name . " WHERE base_currency = :base_currency";
            $stmt = $this->conn->prepare($deleteQuery);
            $stmt->execute([':base_currency' => $baseCurrency]);

            // Вставляем новые курсы
            $insertQuery = "INSERT INTO " . $this->table_name . " 
                (base_currency, target_currency, rate, last_updated) 
                VALUES (:base_currency, :target_currency, :rate, :last_updated)";

            $stmt = $this->conn->prepare($insertQuery);

            foreach ($rates as $currency => $rate) {
                $stmt->execute([
                    ':base_currency' => $baseCurrency,
                    ':target_currency' => $currency,
                    ':rate' => $rate,
                    ':last_updated' => $lastUpdated
                ]);
            }

            $this->conn->commit();
            return true;

        } catch(PDOException $e) {
            $this->conn->rollBack();
            error_log("Ошибка сохранения курсов: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получение курса валюты
     */
    public function getRate($baseCurrency, $targetCurrency) {
        $query = "SELECT rate, last_updated FROM " . $this->table_name . " 
                  WHERE base_currency = :base_currency AND target_currency = :target_currency 
                  ORDER BY last_updated DESC LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':base_currency' => $baseCurrency,
            ':target_currency' => $targetCurrency
        ]);

        return $stmt->fetch();
    }

    /**
     * Получение всех курсов для базовой валюты
     */
    public function getAllRates($baseCurrency = 'USD') {
        $query = "SELECT target_currency, rate, last_updated FROM " . $this->table_name . " 
                  WHERE base_currency = :base_currency 
                  ORDER BY target_currency ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':base_currency' => $baseCurrency]);

        return $stmt->fetchAll();
    }

    /**
     * Конвертация валют
     */
    public function convert($amount, $fromCurrency, $toCurrency, $baseCurrency = 'USD') {
        // Если обе валюты одинаковые
        if ($fromCurrency === $toCurrency) {
            return [
                'amount' => $amount,
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'rate' => 1.0
            ];
        }

        // Если базовая валюта совпадает с исходной
        if ($fromCurrency === $baseCurrency) {
            $rateData = $this->getRate($baseCurrency, $toCurrency);
            if ($rateData) {
                return [
                    'amount' => $amount * $rateData['rate'],
                    'from' => $fromCurrency,
                    'to' => $toCurrency,
                    'rate' => $rateData['rate'],
                    'last_updated' => $rateData['last_updated']
                ];
            }
        }

        // Если базовая валюта совпадает с целевой
        if ($toCurrency === $baseCurrency) {
            $rateData = $this->getRate($baseCurrency, $fromCurrency);
            if ($rateData) {
                $inverseRate = 1 / $rateData['rate'];
                return [
                    'amount' => $amount * $inverseRate,
                    'from' => $fromCurrency,
                    'to' => $toCurrency,
                    'rate' => $inverseRate,
                    'last_updated' => $rateData['last_updated']
                ];
            }
        }

        // Конвертация через базовую валюту
        $fromRateData = $this->getRate($baseCurrency, $fromCurrency);
        $toRateData = $this->getRate($baseCurrency, $toCurrency);

        if ($fromRateData && $toRateData) {
            $fromRate = $fromRateData['rate'];
            $toRate = $toRateData['rate'];
            $conversionRate = $toRate / $fromRate;

            return [
                'amount' => $amount * $conversionRate,
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'rate' => $conversionRate,
                'last_updated' => max($fromRateData['last_updated'], $toRateData['last_updated'])
            ];
        }

        return null;
    }

    /**
     * Проверка актуальности курсов
     */
    public function isRatesUpToDate($baseCurrency = 'USD', $maxAgeMinutes = 60) {
        $query = "SELECT MAX(last_updated) as last_update FROM " . $this->table_name . " 
                  WHERE base_currency = :base_currency";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':base_currency' => $baseCurrency]);
        $result = $stmt->fetch();

        if (!$result || !$result['last_update']) {
            return false;
        }

        $lastUpdate = new DateTime($result['last_update']);
        $now = new DateTime();
        $diff = $now->diff($lastUpdate);
        $minutesDiff = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

        return $minutesDiff < $maxAgeMinutes;
    }
}

