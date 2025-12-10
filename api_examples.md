# Примеры использования API

## Базовый URL

```
http://ваш-домен/api
```

## 1. Получение всех курсов валют

### Запрос

```bash
GET /api/rates
```

### cURL

```bash
curl http://localhost/api/rates
```

### PHP

```php
$url = 'http://localhost/api/rates';
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['success']) {
    foreach ($data['rates'] as $currency => $rateInfo) {
        echo "$currency: " . $rateInfo['rate'] . "\n";
    }
}
```

### JavaScript (Fetch)

```javascript
fetch('http://localhost/api/rates')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Курсы валют:', data.rates);
        }
    });
```

### Ответ

```json
{
    "success": true,
    "base": "USD",
    "rates": {
        "AED": {
            "rate": 3.6725,
            "last_updated": "2024-01-15 12:00:00"
        },
        "EUR": {
            "rate": 0.85,
            "last_updated": "2024-01-15 12:00:00"
        }
    },
    "count": 162
}
```

## 2. Получение курса конкретной валюты

### Запрос

```bash
GET /api/rates?base=USD&target=EUR
```

### cURL

```bash
curl "http://localhost/api/rates?base=USD&target=EUR"
```

### PHP

```php
$base = 'USD';
$target = 'EUR';
$url = "http://localhost/api/rates?base=$base&target=$target";
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['success']) {
    echo "Курс $base/$target: " . $data['rate'] . "\n";
}
```

### Ответ

```json
{
    "success": true,
    "base": "USD",
    "target": "EUR",
    "rate": 0.85,
    "last_updated": "2024-01-15 12:00:00"
}
```

## 3. Конвертация валют

### Запрос

```bash
GET /api/convert?amount=100&from=USD&to=EUR
```

### cURL

```bash
curl "http://localhost/api/convert?amount=100&from=USD&to=EUR"
```

### PHP

```php
$amount = 100;
$from = 'USD';
$to = 'EUR';
$url = "http://localhost/api/convert?amount=$amount&from=$from&to=$to";
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['success']) {
    echo "$amount $from = " . $data['converted_amount'] . " $to\n";
    echo "Курс: " . $data['rate'] . "\n";
}
```

### JavaScript (Fetch)

```javascript
const amount = 100;
const from = 'USD';
const to = 'EUR';

fetch(`http://localhost/api/convert?amount=${amount}&from=${from}&to=${to}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log(`${amount} ${from} = ${data.converted_amount} ${to}`);
            console.log('Курс:', data.rate);
        }
    });
```

### Ответ

```json
{
    "success": true,
    "amount": 100,
    "from": "USD",
    "to": "EUR",
    "converted_amount": 85.00,
    "rate": 0.85,
    "last_updated": "2024-01-15 12:00:00"
}
```

## 4. Регистрация устройства

### Запрос

```bash
POST /api/device/register
Content-Type: application/json

{
    "device_id": "unique-device-id-12345",
    "device_name": "iPhone 13",
    "device_type": "mobile",
    "platform": "iOS",
    "app_version": "1.0.0"
}
```

### cURL

```bash
curl -X POST http://localhost/api/device/register \
  -H "Content-Type: application/json" \
  -d '{
    "device_id": "unique-device-id-12345",
    "device_name": "iPhone 13",
    "device_type": "mobile",
    "platform": "iOS",
    "app_version": "1.0.0"
  }'
```

### PHP

```php
$data = [
    'device_id' => 'unique-device-id-12345',
    'device_name' => 'iPhone 13',
    'device_type' => 'mobile',
    'platform' => 'iOS',
    'app_version' => '1.0.0'
];

$options = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($data)
    ]
];

$context = stream_context_create($options);
$response = file_get_contents('http://localhost/api/device/register', false, $context);
$result = json_decode($response, true);

if ($result['success']) {
    echo "Устройство зарегистрировано: " . $result['device_id'] . "\n";
}
```

### JavaScript (Fetch)

```javascript
fetch('http://localhost/api/device/register', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        device_id: 'unique-device-id-12345',
        device_name: 'iPhone 13',
        device_type: 'mobile',
        platform: 'iOS',
        app_version: '1.0.0'
    })
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Устройство зарегистрировано:', data.device_id);
    }
});
```

### Ответ

```json
{
    "success": true,
    "message": "Устройство зарегистрировано",
    "device_id": 1
}
```

## 5. Получение информации об устройстве

### Запрос

```bash
GET /api/device/info?device_id=unique-device-id-12345
```

### cURL

```bash
curl "http://localhost/api/device/info?device_id=unique-device-id-12345"
```

### Ответ

```json
{
    "success": true,
    "device": {
        "id": 1,
        "device_id": "unique-device-id-12345",
        "device_name": "iPhone 13",
        "device_type": "mobile",
        "platform": "iOS",
        "app_version": "1.0.0",
        "last_active": "2024-01-15 12:30:00",
        "created_at": "2024-01-15 10:00:00",
        "updated_at": "2024-01-15 12:30:00"
    }
}
```

## 6. Принудительное обновление курсов

### Запрос

```bash
POST /api/update
```

### cURL

```bash
curl -X POST http://localhost/api/update
```

### Ответ

```json
{
    "success": true,
    "message": "Курсы валют успешно обновлены",
    "base_currency": "USD",
    "rates_count": 162,
    "updated_at": "2024-01-15 12:35:00"
}
```

## Использование device_id в заголовках

Вместо передачи `device_id` в параметрах запроса, можно использовать заголовок:

```bash
curl -H "X-Device-ID: unique-device-id-12345" \
     http://localhost/api/rates
```

Это автоматически зарегистрирует устройство при первом запросе и залогирует все последующие запросы.

## Обработка ошибок

Все endpoints возвращают JSON с полем `success`. При ошибке `success` будет `false`, а поле `error` содержит описание ошибки:

```json
{
    "success": false,
    "error": "Курс не найден"
}
```

HTTP статус коды:
- `200` - Успешный запрос
- `400` - Неверные параметры запроса
- `404` - Ресурс не найден
- `500` - Внутренняя ошибка сервера

