#!/bin/bash

# Скрипт для настройки production окружения на сервере

SSH_USER="root"
SSH_HOST="81.163.31.224"
REMOTE_PATH="/var/www/conversor.onza.me/html"

echo "🔒 Настройка production окружения..."

# Генерация SECRET_KEY
SECRET_KEY=$(openssl rand -hex 32)
echo "Сгенерирован SECRET_KEY: ${SECRET_KEY:0:16}..."

# Обновление .env файла на сервере
ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 "${SSH_USER}@${SSH_HOST}" << EOF
cd ${REMOTE_PATH}

# Обновление SECRET_KEY
if grep -q "^# SECRET_KEY=" .env; then
    sed -i "s|^# SECRET_KEY=.*|SECRET_KEY=${SECRET_KEY}|" .env
elif ! grep -q "^SECRET_KEY=" .env; then
    echo "SECRET_KEY=${SECRET_KEY}" >> .env
fi

# Убеждаемся что APP_ENV=production и APP_DEBUG=false
sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env
sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env

# Установка правильных прав на .env
chmod 600 .env
chown www-data:www-data .env

echo "✅ .env файл обновлен для production"
EOF

echo "✅ Production настройки применены!"

