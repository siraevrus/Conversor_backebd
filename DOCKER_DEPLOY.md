# Docker Deployment Guide

Руководство по развертыванию Currency API с использованием Docker и Docker Compose.

## 📋 Содержание

1. [Требования](#требования)
2. [Быстрый старт](#быстрый-старт)
3. [Конфигурация](#конфигурация)
4. [Деплой на сервер](#деплой-на-сервер)
5. [Управление](#управление)
6. [Мониторинг](#мониторинг)
7. [Troubleshooting](#troubleshooting)

---

## Требования

- Docker >= 20.10
- Docker Compose >= 2.0
- Минимум 2GB RAM
- Минимум 5GB свободного места на диске

---

## Быстрый старт

### 1. Клонирование проекта

```bash
git clone <repository-url>
cd Conversor_backebd
```

### 2. Настройка переменных окружения

```bash
cp env.example .env
nano .env  # или используйте любой редактор
```

Отредактируйте следующие параметры в `.env`:

```env
# База данных
DB_PASSWORD=your_secure_password
DB_ROOT_PASSWORD=your_root_password

# Пароль админ панели (измените!)
ADMIN_PASSWORD=your_admin_password
```

### 3. Запуск проекта

```bash
# Сборка и запуск всех сервисов
docker-compose up -d

# Просмотр логов
docker-compose logs -f
```

### 4. Инициализация базы данных

```bash
# Запуск миграций
docker-compose exec app php database/migrations.php
docker-compose exec app php database/migrations_extended_logging.php

# Первоначальное обновление курсов
docker-compose exec app php scripts/update_rates.php
```

Или используйте скрипт деплоя:

```bash
chmod +x scripts/deploy.sh
./scripts/deploy.sh
```

### 5. Проверка работы

```bash
# Проверка API
curl http://localhost/api/

# Проверка курсов
curl http://localhost/api/rates

# Проверка конвертации
curl "http://localhost/api/convert?amount=100&from=USD&to=EUR"
```

---

## Конфигурация

### Структура Docker Compose

Проект состоит из следующих сервисов:

1. **app** - PHP-FPM приложение
2. **nginx** - Веб-сервер
3. **db** - MySQL база данных
4. **cron** - Автоматическое обновление курсов

### Переменные окружения

Основные переменные в `.env`:

| Переменная | Описание | По умолчанию |
|------------|----------|--------------|
| `DB_HOST` | Хост базы данных | `db` |
| `DB_NAME` | Имя базы данных | `currency_api` |
| `DB_USER` | Пользователь БД | `currency_user` |
| `DB_PASSWORD` | Пароль БД | `currency_password` |
| `DB_ROOT_PASSWORD` | Root пароль MySQL | `root_password` |
| `HTTP_PORT` | HTTP порт | `80` |
| `HTTPS_PORT` | HTTPS порт | `443` |

### Настройка Nginx

Конфигурация Nginx находится в `docker/nginx/default.conf`.

Для настройки SSL:

1. Поместите сертификаты в `docker/nginx/ssl/`:
   - `cert.pem` - сертификат
   - `key.pem` - приватный ключ

2. Раскомментируйте HTTPS секцию в `docker/nginx/default.conf`

3. Перезапустите контейнеры:
   ```bash
   docker-compose restart nginx
   ```

---

## Деплой на сервер

### Подготовка сервера

1. **Установка Docker и Docker Compose:**

```bash
# Ubuntu/Debian
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# Установка Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

2. **Клонирование проекта:**

```bash
git clone <repository-url> /var/www/currency-api
cd /var/www/currency-api
```

3. **Настройка переменных окружения:**

```bash
cp env.example .env
nano .env
```

### Автоматический деплой

Используйте скрипт деплоя:

```bash
chmod +x scripts/deploy.sh
./scripts/deploy.sh production
```

### Ручной деплой

```bash
# 1. Остановка существующих контейнеров
docker-compose down

# 2. Сборка образов
docker-compose build --no-cache

# 3. Запуск контейнеров
docker-compose up -d

# 4. Ожидание готовности БД
sleep 10

# 5. Запуск миграций
docker-compose exec app php database/migrations.php
docker-compose exec app php database/migrations_extended_logging.php

# 6. Первоначальное обновление курсов
docker-compose exec app php scripts/update_rates.php
```

### Настройка домена

1. **Настройка DNS:**

Добавьте A-запись, указывающую на IP вашего сервера:
```
A    api.yourdomain.com    YOUR_SERVER_IP
```

2. **Обновление Nginx конфигурации:**

Отредактируйте `docker/nginx/default.conf`:
```nginx
server {
    listen 80;
    server_name api.yourdomain.com;
    # ... остальная конфигурация
}
```

3. **Перезапуск Nginx:**

```bash
docker-compose restart nginx
```

---

## Управление

### Основные команды

```bash
# Запуск всех сервисов
docker-compose up -d

# Остановка всех сервисов
docker-compose down

# Перезапуск сервисов
docker-compose restart

# Просмотр статуса
docker-compose ps

# Просмотр логов
docker-compose logs -f

# Логи конкретного сервиса
docker-compose logs -f app
docker-compose logs -f nginx
docker-compose logs -f db
docker-compose logs -f cron
```

### Обновление приложения

```bash
# 1. Получение последних изменений
git pull

# 2. Пересборка образов
docker-compose build --no-cache

# 3. Перезапуск с новой конфигурацией
docker-compose up -d

# 4. Запуск миграций (если есть новые)
docker-compose exec app php database/migrations.php
```

### Резервное копирование базы данных

```bash
# Создание бэкапа
docker-compose exec db mysqldump -u root -p${DB_ROOT_PASSWORD} currency_api > backup_$(date +%Y%m%d_%H%M%S).sql

# Восстановление из бэкапа
docker-compose exec -T db mysql -u root -p${DB_ROOT_PASSWORD} currency_api < backup.sql
```

### Обновление курсов валют

```bash
# Ручное обновление
docker-compose exec app php scripts/update_rates.php

# Автоматическое обновление выполняется через cron контейнер каждый час
```

---

## Мониторинг

### Просмотр логов

```bash
# Все логи
docker-compose logs -f

# Логи приложения
docker-compose logs -f app

# Логи Nginx
docker-compose logs -f nginx

# Логи базы данных
docker-compose logs -f db

# Логи cron
docker-compose logs -f cron
```

### Мониторинг ресурсов

```bash
# Использование ресурсов контейнерами
docker stats

# Использование диска
docker system df
```

### Проверка здоровья сервисов

```bash
# Проверка API
curl http://localhost/api/

# Проверка базы данных
docker-compose exec db mysqladmin ping -h localhost -u root -p${DB_ROOT_PASSWORD}

# Проверка PHP-FPM
docker-compose exec app php -v
```

---

## Troubleshooting

### Проблема: Контейнеры не запускаются

**Решение:**
```bash
# Проверьте логи
docker-compose logs

# Проверьте конфигурацию
docker-compose config

# Убедитесь, что порты не заняты
netstat -tulpn | grep :80
```

### Проблема: Ошибка подключения к базе данных

**Решение:**
```bash
# Проверьте статус контейнера БД
docker-compose ps db

# Проверьте логи БД
docker-compose logs db

# Проверьте переменные окружения
docker-compose exec db env | grep DB_
```

### Проблема: 502 Bad Gateway

**Решение:**
```bash
# Проверьте статус PHP-FPM
docker-compose ps app

# Перезапустите PHP-FPM
docker-compose restart app

# Проверьте логи Nginx
docker-compose logs nginx
```

### Проблема: Курсы не обновляются

**Решение:**
```bash
# Проверьте логи cron
docker-compose logs cron

# Запустите обновление вручную
docker-compose exec app php scripts/update_rates.php

# Проверьте доступность внешнего API
docker-compose exec app curl https://v6.exchangerate-api.com/v6/8cfd4a7237ec45affd505e47/latest/USD
```

### Проблема: Недостаточно памяти

**Решение:**
```bash
# Очистка неиспользуемых ресурсов
docker system prune -a

# Очистка логов
docker-compose down
docker volume prune
```

### Проблема: Порт уже занят

**Решение:**

Измените порты в `.env`:
```env
HTTP_PORT=8080
HTTPS_PORT=8443
DB_PORT=3307
```

---

## Безопасность

### Рекомендации для продакшена

1. **Измените все пароли по умолчанию:**
   - `DB_PASSWORD`
   - `DB_ROOT_PASSWORD`
   - `ADMIN_PASSWORD`

2. **Настройте SSL/TLS:**
   - Получите сертификат (Let's Encrypt)
   - Настройте HTTPS в Nginx

3. **Ограничьте доступ:**
   - Настройте firewall
   - Используйте fail2ban
   - Ограничьте доступ к админ панели по IP

4. **Регулярные обновления:**
   - Обновляйте Docker образы
   - Обновляйте зависимости
   - Мониторьте уязвимости

5. **Резервное копирование:**
   - Настройте автоматические бэкапы БД
   - Храните бэкапы в безопасном месте

---

## Дополнительные ресурсы

- [Docker документация](https://docs.docker.com/)
- [Docker Compose документация](https://docs.docker.com/compose/)
- [Nginx документация](https://nginx.org/ru/docs/)
- [MySQL документация](https://dev.mysql.com/doc/)

---

## Поддержка

При возникновении проблем:
1. Проверьте логи: `docker-compose logs -f`
2. Проверьте статус контейнеров: `docker-compose ps`
3. Создайте issue в репозитории проекта

