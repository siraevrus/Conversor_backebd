# Как проверить актуальность курсов валют

## Быстрая проверка через API

### 1. Проверка конкретного курса
```bash
curl https://conversor.onza.me/api/rates?base=USD&target=EUR
```

Ответ покажет:
- `rate` - текущий курс
- `last_updated` - время последнего обновления

### 2. Проверка всех курсов
```bash
curl https://conversor.onza.me/api/rates
```

## Проверка на сервере

### Использование готового скрипта
```bash
./scripts/check_rates.sh remote
```

Скрипт покажет:
- Время последнего обновления
- Примеры курсов (EUR, RUB, GBP)
- Статус через API
- Статус cron задачи
- Логи обновлений

### Ручная проверка через SSH

#### 1. Проверка времени последнего обновления
```bash
ssh root@81.163.31.224
cd /var/www/conversor.onza.me/html
mysql -u currency_user -pcurrency_password currency_api -e "
    SELECT 
        MAX(last_updated) as last_update,
        TIMESTAMPDIFF(MINUTE, MAX(last_updated), NOW()) as minutes_ago,
        COUNT(*) as total_rates
    FROM exchange_rates;
"
```

#### 2. Проверка конкретных курсов
```bash
mysql -u currency_user -pcurrency_password currency_api -e "
    SELECT target_currency, rate, last_updated 
    FROM exchange_rates 
    WHERE target_currency IN ('EUR', 'RUB', 'GBP')
    ORDER BY target_currency;
"
```

#### 3. Проверка логов обновлений
```bash
# Логи из базы данных
mysql -u currency_user -pcurrency_password currency_api -e "
    SELECT base_currency, rates_count, success, created_at, execution_time_ms
    FROM rate_update_logs 
    ORDER BY created_at DESC 
    LIMIT 5;
"

# Логи из файла (если настроено)
tail -20 /var/log/conversor_update.log
```

#### 4. Ручной запуск обновления
```bash
cd /var/www/conversor.onza.me/html
php scripts/update_rates.php
```

## Автоматическое обновление

Курсы обновляются автоматически каждый час через cron:
```bash
0 * * * * cd /var/www/conversor.onza.me/html && /usr/bin/php scripts/update_rates.php >> /var/log/conversor_update.log 2>&1
```

### Проверка статуса cron
```bash
ssh root@81.163.31.224
crontab -l | grep update_rates
```

## Что проверить

1. **Время обновления** - должно быть не старше 60 минут (интервал обновления)
2. **Количество курсов** - должно быть ~166 валют
3. **Статус обновления** - в таблице `rate_update_logs` должно быть `success=1`
4. **Работа API** - курсы должны возвращаться через API

## Примеры проверки

### Через веб-браузер
Откройте в браузере:
- `https://conversor.onza.me/api/rates?base=USD&target=EUR`
- `https://conversor.onza.me/api/rates?base=USD&target=RUB`

### Через командную строку
```bash
# Проверка EUR
curl -s https://conversor.onza.me/api/rates?base=USD&target=EUR | jq

# Проверка RUB  
curl -s https://conversor.onza.me/api/rates?base=USD&target=RUB | jq

# Проверка всех курсов
curl -s https://conversor.onza.me/api/rates | jq '.rates | length'
```

## Устранение проблем

### Если курсы не обновляются:

1. **Проверьте cron задачу:**
   ```bash
   ssh root@81.163.31.224
   crontab -l
   ```

2. **Запустите обновление вручную:**
   ```bash
   cd /var/www/conversor.onza.me/html
   php scripts/update_rates.php
   ```

3. **Проверьте логи ошибок:**
   ```bash
   tail -50 /var/log/conversor_update.log
   tail -50 /var/www/conversor.onza.me/html/logs/php_errors.log
   ```

4. **Проверьте подключение к внешнему API:**
   ```bash
   curl https://v6.exchangerate-api.com/v6/8cfd4a7237ec45affd505e47/latest/USD
   ```

5. **Проверьте подключение к БД:**
   ```bash
   mysql -u currency_user -pcurrency_password currency_api -e "SELECT 1;"
   ```

