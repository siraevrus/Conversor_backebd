#!/bin/bash

# Скрипт для деплоя Currency API на удаленный сервер
# Использование: ./scripts/deploy_remote.sh

set -e

# Конфигурация
SSH_USER="root"
SSH_HOST="81.163.31.224"
REMOTE_PATH="/var/www/conversor.onza.me/html"
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Функции для вывода сообщений
info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# Проверка наличия rsync
if ! command -v rsync &> /dev/null; then
    error "rsync не установлен. Установите rsync для продолжения."
fi

# SSH опции для надежности
SSH_OPTS="-o StrictHostKeyChecking=no -o ConnectTimeout=30 -o ServerAliveInterval=60 -o ServerAliveCountMax=3"

# Проверка SSH подключения
step "Проверка SSH подключения к серверу..."
if ! ssh $SSH_OPTS -o BatchMode=yes "${SSH_USER}@${SSH_HOST}" echo "SSH подключение успешно" &> /dev/null; then
    warn "Не удалось подключиться по SSH без пароля. Потребуется ввод пароля."
fi

# Создание директории на сервере, если её нет
step "Создание директории на сервере..."
ssh $SSH_OPTS "${SSH_USER}@${SSH_HOST}" "mkdir -p ${REMOTE_PATH}"

# Создание резервной копии на сервере (если директория не пуста)
step "Создание резервной копии (если необходимо)..."
ssh $SSH_OPTS "${SSH_USER}@${SSH_HOST}" "
    if [ -d '${REMOTE_PATH}' ] && [ \"\$(ls -A ${REMOTE_PATH} 2>/dev/null)\" ]; then
        BACKUP_DIR=\"${REMOTE_PATH}_backup_\$(date +%Y%m%d_%H%M%S)\"
        echo 'Создание резервной копии в: '\$BACKUP_DIR
        cp -r ${REMOTE_PATH} \$BACKUP_DIR
        echo 'Резервная копия создана'
    fi
"

# Синхронизация файлов
step "Синхронизация файлов проекта на сервер..."
info "Исключаемые файлы/папки:"
info "  - .git/"
info "  - vendor/"
info "  - .env"
info "  - *.log"
info "  - logs/"
info "  - .idea/, .vscode/"
info "  - node_modules/ (если есть)"
info "  - .DS_Store"

# Функция для синхронизации с повторными попытками
sync_with_retry() {
    local max_attempts=3
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        info "Попытка синхронизации $attempt из $max_attempts..."
        
        if rsync -avz --progress \
            --timeout=300 \
            --partial \
            --partial-dir=.rsync-partial \
            -e "ssh $SSH_OPTS" \
            --exclude='.git/' \
            --exclude='vendor/' \
            --exclude='composer.lock' \
            --exclude='.env' \
            --exclude='*.log' \
            --exclude='logs/' \
            --exclude='.idea/' \
            --exclude='.vscode/' \
            --exclude='node_modules/' \
            --exclude='.DS_Store' \
            --exclude='Thumbs.db' \
            --exclude='tmp/' \
            --exclude='temp/' \
            --exclude='*.swp' \
            --exclude='*.swo' \
            --exclude='*~' \
            --exclude='currency_api.zip' \
            "${PROJECT_DIR}/" "${SSH_USER}@${SSH_HOST}:${REMOTE_PATH}/"; then
            info "Файлы успешно синхронизированы!"
            return 0
        else
            warn "Попытка $attempt не удалась"
            if [ $attempt -lt $max_attempts ]; then
                info "Ожидание 5 секунд перед повторной попыткой..."
                sleep 5
            fi
            attempt=$((attempt + 1))
        fi
    done
    
    error "Не удалось синхронизировать файлы после $max_attempts попыток"
}

sync_with_retry

# Проверка наличия .env на сервере
step "Проверка конфигурации окружения..."
if ! ssh $SSH_OPTS "${SSH_USER}@${SSH_HOST}" "test -f ${REMOTE_PATH}/.env"; then
    warn ".env файл не найден на сервере. Создаю из env.example..."
    ssh $SSH_OPTS "${SSH_USER}@${SSH_HOST}" "
        cd ${REMOTE_PATH}
        if [ -f env.example ]; then
            cp env.example .env
            echo '.env файл создан из env.example'
            echo 'ВАЖНО: Отредактируйте .env файл на сервере с правильными настройками!'
        else
            echo 'ОШИБКА: env.example не найден!'
        fi
    "
else
    info ".env файл уже существует на сервере"
fi

# Установка зависимостей Composer (если composer установлен на сервере)
step "Проверка и установка зависимостей Composer..."
ssh $SSH_OPTS "${SSH_USER}@${SSH_HOST}" "
    cd ${REMOTE_PATH}
    if command -v composer &> /dev/null; then
        echo 'Установка зависимостей Composer...'
        composer install --no-dev --optimize-autoloader --no-interaction
        echo 'Зависимости установлены'
    else
        echo 'Composer не установлен на сервере. Пропускаю установку зависимостей.'
        echo 'Установите Composer или используйте Docker для запуска проекта.'
    fi
"

# Установка прав доступа
step "Установка прав доступа..."
ssh $SSH_OPTS "${SSH_USER}@${SSH_HOST}" "
    cd ${REMOTE_PATH}
    # Установка прав для веб-сервера (обычно www-data или nginx)
    if id -u www-data &>/dev/null; then
        chown -R www-data:www-data .
        chmod -R 755 .
        chmod -R 775 logs/ 2>/dev/null || true
        echo 'Права установлены для www-data'
    elif id -u nginx &>/dev/null; then
        chown -R nginx:nginx .
        chmod -R 755 .
        chmod -R 775 logs/ 2>/dev/null || true
        echo 'Права установлены для nginx'
    else
        echo 'Пользователь веб-сервера не найден. Установите права вручную.'
    fi
"

# Информация о следующих шагах
echo ""
info "✅ Деплой завершен успешно!"
echo ""
echo "📋 Следующие шаги на сервере:"
echo ""
echo "1. Отредактируйте файл .env с правильными настройками:"
echo "   ssh ${SSH_USER}@${SSH_HOST}"
echo "   nano ${REMOTE_PATH}/.env"
echo ""
echo "2. Если используете Docker, запустите:"
echo "   cd ${REMOTE_PATH}"
echo "   docker-compose up -d"
echo ""
echo "3. Если используете обычный PHP, убедитесь что:"
echo "   - PHP >= 7.4 установлен"
echo "   - MySQL/MariaDB настроена"
echo "   - Nginx/Apache настроен"
echo "   - Запущены миграции: php database/migrations.php"
echo ""
echo "4. Проверьте логи при необходимости:"
echo "   tail -f ${REMOTE_PATH}/logs/*.log"
echo ""

