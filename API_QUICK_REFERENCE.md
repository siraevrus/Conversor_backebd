# Currency API - Краткая справка

**Базовый URL:** `http://localhost:8000/api`

---

## Основные Endpoints

### 📊 Курсы валют

```bash
# Все курсы
GET /api/rates

# Конкретный курс
GET /api/rates?base=USD&target=EUR
```

### 💱 Конвертация

```bash
GET /api/convert?amount=100&from=USD&to=EUR
```

### 📱 Устройства

```bash
# Регистрация
POST /api/device/register
Content-Type: application/json
{
  "device_id": "unique-id",
  "platform": "iOS"
}

# Информация
GET /api/device/info?device_id=unique-id
```

### 🔄 Обновление курсов

```bash
POST /api/update
```

---

## Примеры

### JavaScript

```javascript
// Курсы
const rates = await fetch('/api/rates').then(r => r.json());

// Конвертация
const result = await fetch('/api/convert?amount=100&from=USD&to=EUR')
  .then(r => r.json());
```

### cURL

```bash
# Курсы
curl http://localhost:8000/api/rates

# Конвертация
curl "http://localhost:8000/api/convert?amount=100&from=USD&to=EUR"
```

---

## Формат ответов

**Успех:**
```json
{
  "success": true,
  "data": { ... }
}
```

**Ошибка:**
```json
{
  "success": false,
  "error": "Описание ошибки"
}
```

---

## Статус коды

- `200` - Успех
- `400` - Неверные параметры
- `404` - Не найдено
- `500` - Ошибка сервера

---

**Полная документация:** [API_DOCUMENTATION_COMPLETE.md](API_DOCUMENTATION_COMPLETE.md)

