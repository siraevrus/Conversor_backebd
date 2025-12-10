# Инструкция по установке

## Шаг 1: Настройка базы данных

1. Создайте базу данных MySQL:

```sql
CREATE DATABASE currency_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Отредактируйте файл `config/database.php`:

```php
private $host = "localhost";        // Адрес сервера БД
private $db_name = "currency_api";  // Имя базы данных
private $username = "root";          // Пользователь БД
private $password = "ваш_пароль";    // Пароль БД
```

## Шаг 2: Запуск миграций

Выполните команду для создания таблиц:

```bash
php database/migrations.php
```

Вы должны увидеть сообщения:
- Таблица exchange_rates создана успешно.
- Таблица devices создана успешно.
- Таблица api_requests создана успешно.

## Шаг 3: Первое обновление курсов

Выполните скрипт обновления вручную для первоначальной загрузки курсов:

```bash
php scripts/update_rates.php
```

Или используйте API endpoint:

```bash
curl -X POST http://ваш-домен/api/update
```

## Шаг 4: Настройка автоматического обновления (Cron)

### Linux/Mac

Откройте crontab:

```bash
crontab -e
```

Добавьте строку (замените путь на актуальный):

```
0 * * * * /usr/bin/php /полный/путь/к/проекту/currency_api/scripts/update_rates.php >> /var/log/currency_api.log 2>&1
```

Это обновит курсы каждый час в начале часа (00:00, 01:00, 02:00 и т.д.).

### Альтернативные варианты cron

Обновление каждые 60 минут:
```
*/60 * * * * /usr/bin/php /путь/scripts/update_rates.php >> /var/log/currency_api.log 2>&1
```

Обновление каждые 30 минут:
```
*/30 * * * * /usr/bin/php /путь/scripts/update_rates.php >> /var/log/currency_api.log 2>&1
```

### Windows (Task Scheduler)

1. Откройте Планировщик заданий
2. Создайте новое задание
3. Установите триггер: повторять каждые 60 минут
4. Действие: запустить программу `php.exe`
5. Аргументы: `C:\путь\к\проекту\currency_api\scripts\update_rates.php`

## Шаг 5: Настройка веб-сервера

### Apache

Убедитесь, что модуль `mod_rewrite` включен:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

Файл `.htaccess` уже настроен в проекте.

### Nginx

Добавьте в конфигурацию вашего сайта:

```nginx
server {
    listen 80;
    server_name ваш-домен.com;
    root /путь/к/проекту/currency_api;
    index index.php;

    location / {
        try_files $uri $uri/ /api/index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## Шаг 6: Проверка работы

### Проверка API

```bash
# Проверка главной страницы API
curl http://ваш-домен/api/

# Проверка получения курсов
curl http://ваш-домен/api/rates

# Проверка конвертации
curl "http://ваш-домен/api/convert?amount=100&from=USD&to=EUR"
```

### Проверка базы данных

```sql
-- Проверка курсов
SELECT * FROM exchange_rates LIMIT 10;

-- Проверка устройств
SELECT * FROM devices;

-- Проверка запросов
SELECT * FROM api_requests ORDER BY created_at DESC LIMIT 10;
```

## Решение проблем

### Ошибка подключения к БД

- Проверьте параметры в `config/database.php`
- Убедитесь, что MySQL сервер запущен
- Проверьте права доступа пользователя БД

### Курсы не обновляются

- Проверьте логи cron: `tail -f /var/log/currency_api.log`
- Убедитесь, что скрипт имеет права на выполнение: `chmod +x scripts/update_rates.php`
- Проверьте доступность внешнего API: `curl https://v6.exchangerate-api.com/v6/8cfd4a7237ec45affd505e47/latest/USD`

### API возвращает 404

- Проверьте настройки `.htaccess` (Apache)
- Проверьте конфигурацию Nginx
- Убедитесь, что файл `api/index.php` существует и доступен

### Ошибки PHP

- Проверьте версию PHP: `php -v` (должна быть >= 7.4)
- Убедитесь, что установлены расширения: `php -m | grep -E 'pdo|curl|json'`
- Проверьте права доступа к файлам и папкам

