# API Документация - Currency API

## Базовый URL

```
http://localhost:8000/api
```

или в продакшене:

```
https://ваш-домен.com/api
```

## Общая информация

### Формат ответов

Все ответы API возвращаются в формате JSON с кодировкой UTF-8.

### Структура успешного ответа

```json
{
    "success": true,
    "data": { ... }
}
```

### Структура ответа с ошибкой

```json
{
    "success": false,
    "error": "Описание ошибки"
}
```

### HTTP статус коды

- `200 OK` - Успешный запрос
- `400 Bad Request` - Неверные параметры запроса
- `404 Not Found` - Ресурс не найден
- `500 Internal Server Error` - Внутренняя ошибка сервера

### Заголовки запросов

Для регистрации устройства можно использовать заголовок:

```
X-Device-ID: unique-device-id-12345
```

---

## Endpoints

### 1. Информация об API

Получить список всех доступных endpoints.

**Endpoint:** `GET /api/`

**Параметры:** Нет

**Пример запроса:**

```bash
curl http://localhost:8000/api/
```

**Пример ответа:**

```json
{
    "success": true,
    "message": "Currency API v1",
    "endpoints": {
        "GET /api/rates": "Получить все курсы валют",
        "GET /api/rates?base=USD&target=EUR": "Получить курс конкретной валюты",
        "GET /api/convert?amount=100&from=USD&to=EUR": "Конвертировать валюту",
        "POST /api/device/register": "Зарегистрировать устройство",
        "GET /api/device/info?device_id=xxx": "Получить информацию об устройстве",
        "POST /api/update": "Принудительное обновление курсов"
    }
}
```

---

### 2. Получение курсов валют

#### 2.1. Получить все курсы валют

Получить список всех доступных курсов валют относительно базовой валюты.

**Endpoint:** `GET /api/rates`

**Параметры запроса:**

| Параметр | Тип | Обязательный | Описание | По умолчанию |
|----------|-----|--------------|----------|--------------|
| `base` | string | Нет | Базовая валюта (3 буквенный код ISO) | USD |

**Пример запроса:**

```bash
curl "http://localhost:8000/api/rates"
```

```bash
curl "http://localhost:8000/api/rates?base=USD"
```

**Пример ответа:**

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
        },
        "RUB": {
            "rate": 75.5,
            "last_updated": "2024-01-15 12:00:00"
        }
    },
    "count": 166
}
```

**Поля ответа:**

- `success` (boolean) - Успешность запроса
- `base` (string) - Базовая валюта
- `rates` (object) - Объект с курсами валют, где ключ - код валюты
  - `rate` (float) - Курс обмена
  - `last_updated` (string) - Дата и время последнего обновления (формат: YYYY-MM-DD HH:MM:SS)
- `count` (integer) - Количество доступных курсов

---

#### 2.2. Получить курс конкретной валюты

Получить курс обмена между базовой и целевой валютой.

**Endpoint:** `GET /api/rates`

**Параметры запроса:**

| Параметр | Тип | Обязательный | Описание | По умолчанию |
|----------|-----|--------------|----------|--------------|
| `base` | string | Нет | Базовая валюта (3 буквенный код ISO) | USD |
| `target` | string | Да | Целевая валюта (3 буквенный код ISO) | - |

**Пример запроса:**

```bash
curl "http://localhost:8000/api/rates?base=USD&target=EUR"
```

**Пример ответа:**

```json
{
    "success": true,
    "base": "USD",
    "target": "EUR",
    "rate": 0.85,
    "last_updated": "2024-01-15 12:00:00"
}
```

**Поля ответа:**

- `success` (boolean) - Успешность запроса
- `base` (string) - Базовая валюта
- `target` (string) - Целевая валюта
- `rate` (float) - Курс обмена
- `last_updated` (string) - Дата и время последнего обновления

**Пример ответа при ошибке:**

```json
{
    "success": false,
    "error": "Курс не найден"
}
```

**HTTP статус:** 404

---

### 3. Конвертация валют

Конвертировать сумму из одной валюты в другую.

**Endpoint:** `GET /api/convert`

**Параметры запроса:**

| Параметр | Тип | Обязательный | Описание | По умолчанию |
|----------|-----|--------------|----------|--------------|
| `amount` | float | Да | Сумма для конвертации | - |
| `from` | string | Да | Исходная валюта (3 буквенный код ISO) | - |
| `to` | string | Да | Целевая валюта (3 буквенный код ISO) | - |
| `base` | string | Нет | Базовая валюта для расчета (3 буквенный код ISO) | USD |

**Пример запроса:**

```bash
curl "http://localhost:8000/api/convert?amount=100&from=USD&to=EUR"
```

```bash
curl "http://localhost:8000/api/convert?amount=1000&from=RUB&to=USD"
```

**Пример ответа:**

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

**Поля ответа:**

- `success` (boolean) - Успешность запроса
- `amount` (float) - Исходная сумма
- `from` (string) - Исходная валюта
- `to` (string) - Целевая валюта
- `converted_amount` (float) - Конвертированная сумма (округлено до 2 знаков)
- `rate` (float) - Использованный курс обмена (округлено до 8 знаков)
- `last_updated` (string) - Дата и время последнего обновления курса

**Пример ответа при ошибке:**

```json
{
    "success": false,
    "error": "Неверная сумма"
}
```

**HTTP статус:** 400

```json
{
    "success": false,
    "error": "Не удалось выполнить конвертацию. Проверьте наличие курсов для указанных валют."
}
```

**HTTP статус:** 404

**Особенности:**

- Если `from` и `to` одинаковые, возвращается исходная сумма с курсом 1.0
- Конвертация может выполняться через базовую валюту, если прямой курс недоступен
- Все суммы округляются до 2 знаков после запятой

---

### 4. Управление устройствами

#### 4.1. Регистрация устройства

Зарегистрировать новое устройство или обновить информацию о существующем устройстве.

**Endpoint:** `POST /api/device/register`

**Content-Type:** `application/json`

**Тело запроса (JSON):**

| Поле | Тип | Обязательный | Описание |
|------|-----|--------------|----------|
| `device_id` | string | Да | Уникальный идентификатор устройства |
| `device_name` | string | Нет | Название устройства (например, "iPhone 13") |
| `device_type` | string | Нет | Тип устройства (например, "mobile", "tablet", "desktop") |
| `platform` | string | Нет | Платформа (например, "iOS", "Android", "Web") |
| `app_version` | string | Нет | Версия приложения |

**Пример запроса:**

```bash
curl -X POST http://localhost:8000/api/device/register \
  -H "Content-Type: application/json" \
  -d '{
    "device_id": "unique-device-id-12345",
    "device_name": "iPhone 13",
    "device_type": "mobile",
    "platform": "iOS",
    "app_version": "1.0.0"
  }'
```

**Пример ответа (новое устройство):**

```json
{
    "success": true,
    "message": "Устройство зарегистрировано",
    "device_id": 1
}
```

**Пример ответа (обновление существующего):**

```json
{
    "success": true,
    "message": "Устройство обновлено",
    "device_id": 1
}
```

**Поля ответа:**

- `success` (boolean) - Успешность запроса
- `message` (string) - Сообщение о результате операции
- `device_id` (integer) - ID устройства в базе данных

**Пример ответа при ошибке:**

```json
{
    "success": false,
    "error": "device_id обязателен"
}
```

**HTTP статус:** 400

**Особенности:**

- Если устройство с таким `device_id` уже существует, информация обновляется
- Поле `last_active` автоматически обновляется при каждом запросе
- Все запросы с указанным `device_id` логируются в таблицу `api_requests`

---

#### 4.2. Получение информации об устройстве

Получить информацию о зарегистрированном устройстве.

**Endpoint:** `GET /api/device/info`

**Параметры запроса:**

| Параметр | Тип | Обязательный | Описание |
|----------|-----|--------------|----------|
| `device_id` | string | Да | Уникальный идентификатор устройства |

**Пример запроса:**

```bash
curl "http://localhost:8000/api/device/info?device_id=unique-device-id-12345"
```

**Пример ответа:**

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

**Поля ответа:**

- `success` (boolean) - Успешность запроса
- `device` (object) - Информация об устройстве
  - `id` (integer) - Внутренний ID устройства
  - `device_id` (string) - Уникальный идентификатор устройства
  - `device_name` (string) - Название устройства
  - `device_type` (string) - Тип устройства
  - `platform` (string) - Платформа
  - `app_version` (string) - Версия приложения
  - `last_active` (string) - Время последней активности
  - `created_at` (string) - Время регистрации
  - `updated_at` (string) - Время последнего обновления

**Пример ответа при ошибке:**

```json
{
    "success": false,
    "error": "device_id обязателен"
}
```

**HTTP статус:** 400

```json
{
    "success": false,
    "error": "Устройство не найдено"
}
```

**HTTP статус:** 404

---

### 5. Управление курсами валют

#### 5.1. Принудительное обновление курсов

Принудительно обновить курсы валют с внешнего API (не дожидаясь следующего cron).

**Endpoint:** `POST /api/update`

**Параметры:** Нет

**Пример запроса:**

```bash
curl -X POST http://localhost:8000/api/update
```

**Пример ответа:**

```json
{
    "success": true,
    "message": "Курсы валют успешно обновлены",
    "base_currency": "USD",
    "rates_count": 166,
    "updated_at": "2024-01-15 12:35:00"
}
```

**Поля ответа:**

- `success` (boolean) - Успешность запроса
- `message` (string) - Сообщение о результате
- `base_currency` (string) - Базовая валюта
- `rates_count` (integer) - Количество обновленных курсов
- `updated_at` (string) - Время обновления

**Пример ответа при ошибке:**

```json
{
    "success": false,
    "error": "Ошибка сохранения курсов в базу данных"
}
```

**HTTP статус:** 500

**Особенности:**

- Обычно курсы обновляются автоматически каждые 60 минут через cron
- Этот endpoint можно использовать для ручного обновления
- Обновление может занять несколько секунд

---

## Примеры использования

### JavaScript (Fetch API)

```javascript
// Получение всех курсов
async function getRates() {
    const response = await fetch('http://localhost:8000/api/rates');
    const data = await response.json();
    
    if (data.success) {
        console.log('Курсы валют:', data.rates);
    }
}

// Конвертация валют
async function convertCurrency(amount, from, to) {
    const url = `http://localhost:8000/api/convert?amount=${amount}&from=${from}&to=${to}`;
    const response = await fetch(url);
    const data = await response.json();
    
    if (data.success) {
        console.log(`${amount} ${from} = ${data.converted_amount} ${to}`);
        return data.converted_amount;
    }
}

// Регистрация устройства
async function registerDevice(deviceId, deviceInfo) {
    const response = await fetch('http://localhost:8000/api/device/register', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            device_id: deviceId,
            ...deviceInfo
        })
    });
    
    const data = await response.json();
    return data;
}
```

### PHP

```php
// Получение всех курсов
function getRates($base = 'USD') {
    $url = "http://localhost:8000/api/rates?base=$base";
    $response = file_get_contents($url);
    return json_decode($response, true);
}

// Конвертация валют
function convertCurrency($amount, $from, $to) {
    $url = "http://localhost:8000/api/convert?amount=$amount&from=$from&to=$to";
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    if ($data['success']) {
        return $data['converted_amount'];
    }
    return null;
}

// Регистрация устройства
function registerDevice($deviceId, $deviceInfo) {
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode(array_merge(['device_id' => $deviceId], $deviceInfo))
        ]
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents('http://localhost:8000/api/device/register', false, $context);
    return json_decode($response, true);
}
```

### Python

```python
import requests

# Получение всех курсов
def get_rates(base='USD'):
    url = f'http://localhost:8000/api/rates?base={base}'
    response = requests.get(url)
    return response.json()

# Конвертация валют
def convert_currency(amount, from_currency, to_currency):
    url = f'http://localhost:8000/api/convert?amount={amount}&from={from_currency}&to={to_currency}'
    response = requests.get(url)
    data = response.json()
    
    if data['success']:
        return data['converted_amount']
    return None

# Регистрация устройства
def register_device(device_id, device_info):
    url = 'http://localhost:8000/api/device/register'
    payload = {'device_id': device_id, **device_info}
    response = requests.post(url, json=payload)
    return response.json()
```

---

## Коды валют

API поддерживает все стандартные коды валют ISO 4217. Примеры:

- `USD` - Доллар США
- `EUR` - Евро
- `GBP` - Британский фунт
- `RUB` - Российский рубль
- `JPY` - Японская йена
- `CNY` - Китайский юань
- `AED` - Дирхам ОАЭ
- И другие (всего ~166 валют)

---

## Ограничения и рекомендации

### Rate Limiting

В текущей версии API не реализован rate limiting. Рекомендуется:

- Не делать более 100 запросов в минуту с одного IP/устройства
- Использовать кэширование на стороне клиента
- Обновлять курсы не чаще одного раза в минуту

### Кэширование

- Курсы валют обновляются каждые 60 минут
- Рекомендуется кэшировать ответы на стороне клиента на 5-10 минут
- Используйте поле `last_updated` для определения актуальности данных

### Безопасность

- В продакшене рекомендуется добавить аутентификацию для административных endpoints
- Используйте HTTPS для защиты данных
- Валидируйте все входные данные на стороне клиента

---

## Обработка ошибок

Все ошибки возвращаются в едином формате:

```json
{
    "success": false,
    "error": "Описание ошибки"
}
```

### Типичные ошибки:

1. **400 Bad Request** - Неверные параметры запроса
   - Отсутствуют обязательные параметры
   - Неверный формат данных

2. **404 Not Found** - Ресурс не найден
   - Курс валюты не найден
   - Устройство не найдено

3. **500 Internal Server Error** - Внутренняя ошибка сервера
   - Ошибка подключения к базе данных
   - Ошибка внешнего API

---

## Версионирование

Текущая версия API: **v1**

В будущем могут быть добавлены новые версии с префиксом `/api/v2/`, `/api/v3/` и т.д.

---

## Поддержка

При возникновении проблем:

1. Проверьте формат запроса согласно документации
2. Убедитесь, что используете правильный базовый URL
3. Проверьте логи сервера
4. Убедитесь, что база данных доступна и курсы обновлены

---

**Последнее обновление:** 2024-01-15

