# Мониторинг использования API

## Способы проверки использования API приложениями

### 1. Через админ-панель (Веб-интерфейс)

**URL:** `https://conversor.onza.me/admin/api_monitor.php`

**Что показывает:**
- Статистика за сегодня (запросы, устройства, IP, endpoints)
- Топ используемых endpoints
- Статистика по платформам (iOS, Android)
- Активные устройства за последние 24 часа
- Последние запросы в реальном времени
- Автообновление каждые 30 секунд

**Как использовать:**
1. Войдите в админ-панель: `https://conversor.onza.me/admin/`
2. Перейдите в раздел "📡 Мониторинг API"
3. Или используйте прямую ссылку из дашборда

### 2. Через командную строку (Скрипт)

**Скрипт:** `./scripts/check_api_usage.sh`

**Использование:**
```bash
# Проверка на production сервере
./scripts/check_api_usage.sh remote

# Локальная проверка
./scripts/check_api_usage.sh local
```

**Что показывает:**
- Статистика за сегодня
- Топ 10 endpoints
- Активные устройства
- Последние 10 запросов
- Статистика по платформам
- Статистика по версиям приложений
- Использование конкретных endpoints за неделю

### 3. Через базу данных напрямую

**Подключение:**
```bash
ssh root@81.163.31.224
mysql -u currency_user -pcurrency_password currency_api
```

**Полезные запросы:**

#### Статистика за сегодня
```sql
SELECT 
    COUNT(*) as total_requests,
    COUNT(DISTINCT device_id) as unique_devices,
    COUNT(DISTINCT endpoint) as unique_endpoints
FROM api_requests 
WHERE DATE(created_at) = CURDATE();
```

#### Топ используемых endpoints
```sql
SELECT 
    endpoint,
    method,
    COUNT(*) as requests_count,
    COUNT(DISTINCT device_id) as devices_count
FROM api_requests 
WHERE DATE(created_at) = CURDATE()
GROUP BY endpoint, method
ORDER BY requests_count DESC
LIMIT 10;
```

#### Активные устройства
```sql
SELECT 
    d.device_id,
    d.platform,
    d.app_version,
    COUNT(ar.id) as requests_count,
    MAX(ar.created_at) as last_request
FROM devices d
LEFT JOIN api_requests ar ON d.id = ar.device_id
WHERE ar.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY d.id
ORDER BY requests_count DESC;
```

#### Проверка использования конкретного endpoint
```sql
SELECT 
    endpoint,
    COUNT(*) as count,
    COUNT(DISTINCT device_id) as devices,
    MIN(created_at) as first_use,
    MAX(created_at) as last_use
FROM api_requests
WHERE endpoint = '/api/rates'
AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY endpoint;
```

#### Статистика по платформам
```sql
SELECT 
    COALESCE(d.platform, 'Unknown') as platform,
    COUNT(DISTINCT d.id) as devices_count,
    COUNT(ar.id) as requests_count
FROM api_requests ar
LEFT JOIN devices d ON ar.device_id = d.id
WHERE ar.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY d.platform
ORDER BY requests_count DESC;
```

### 4. Через логи Nginx

**Просмотр логов:**
```bash
ssh root@81.163.31.224
tail -f /var/log/nginx/conversor.onza.me/access_https.log | grep "/api/"
```

**Анализ логов:**
```bash
# Подсчет запросов к API
grep "/api/" /var/log/nginx/conversor.onza.me/access_https.log | wc -l

# Топ IP адресов
grep "/api/" /var/log/nginx/conversor.onza.me/access_https.log | awk '{print $1}' | sort | uniq -c | sort -rn | head -10

# Топ endpoints
grep "/api/" /var/log/nginx/conversor.onza.me/access_https.log | awk '{print $7}' | sort | uniq -c | sort -rn | head -10
```

## Что проверять

### ✅ Признаки использования API:

1. **Запросы в таблице `api_requests`**
   - Если есть записи - API используется
   - Проверьте время последних запросов

2. **Зарегистрированные устройства**
   - Таблица `devices` содержит устройства
   - Поле `last_active` показывает активность

3. **Статистика по endpoints**
   - Какие endpoints используются чаще всего
   - Какие методы (GET/POST) используются

4. **Платформы и версии**
   - Какие платформы используют API (iOS/Android)
   - Какие версии приложений активны

### 🔍 Как определить, что приложение использует новое API:

1. **Проверьте User-Agent**
   ```sql
   SELECT DISTINCT user_agent 
   FROM api_requests 
   WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);
   ```

2. **Проверьте device_id**
   - Новые приложения регистрируют устройства
   - Проверьте таблицу `devices`

3. **Проверьте endpoints**
   - Новые приложения используют `/api/rates`, `/api/convert`
   - Старые могут использовать другие пути

4. **Проверьте версии приложений**
   ```sql
   SELECT app_version, COUNT(*) as count
   FROM devices
   WHERE last_active >= DATE_SUB(NOW(), INTERVAL 7 DAY)
   GROUP BY app_version
   ORDER BY count DESC;
   ```

## Автоматический мониторинг

### Настройка алертов

Создайте скрипт для проверки активности:

```bash
#!/bin/bash
# Проверка активности API за последний час
ACTIVE=$(mysql -u currency_user -pcurrency_password currency_api -se "
    SELECT COUNT(*) 
    FROM api_requests 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
")

if [ "$ACTIVE" -eq 0 ]; then
    echo "⚠️  Нет активности API за последний час!"
    # Отправить уведомление
fi
```

### Регулярные проверки

Добавьте в cron:
```bash
# Проверка каждые 5 минут
*/5 * * * * /path/to/check_api_activity.sh
```

## Рекомендации

1. **Регулярно проверяйте статистику** - минимум раз в день
2. **Мониторьте активные устройства** - отслеживайте новые регистрации
3. **Анализируйте использование endpoints** - оптимизируйте популярные
4. **Проверяйте версии приложений** - убедитесь, что используют актуальное API
5. **Следите за ошибками** - проверяйте таблицу `error_logs`

