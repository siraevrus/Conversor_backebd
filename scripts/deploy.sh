#!/bin/bash

# Скрипт для деплоя Currency API на сервер
# Использование: ./scripts/deploy.sh [production|staging]

set -e

ENVIRONMENT=${1:-production}
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

echo "🚀 Начало деплоя Currency API (окружение: $ENVIRONMENT)"
echo "📁 Директория проекта: $PROJECT_DIR"
echo ""

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Функция для вывода сообщений
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

# Проверка наличия Docker и Docker Compose
if ! command -v docker &> /dev/null; then
    error "Docker не установлен. Установите Docker для продолжения."
fi

if ! command -v docker-compose &> /dev/null; then
    error "Docker Compose не установлен. Установите Docker Compose для продолжения."
fi

# Проверка наличия .env файла
if [ ! -f "$PROJECT_DIR/.env" ]; then
    warn ".env файл не найден. Создаю из env.example..."
    if [ -f "$PROJECT_DIR/env.example" ]; then
        cp "$PROJECT_DIR/env.example" "$PROJECT_DIR/.env"
        warn "Пожалуйста, отредактируйте .env файл перед продолжением!"
        exit 1
    else
        error "env.example файл не найден!"
    fi
fi

# Остановка существующих контейнеров
info "Остановка существующих контейнеров..."
cd "$PROJECT_DIR"
docker-compose down

# Сборка образов
info "Сборка Docker образов..."
docker-compose build --no-cache

# Запуск контейнеров
info "Запуск контейнеров..."
docker-compose up -d

# Ожидание готовности базы данных
info "Ожидание готовности базы данных..."
sleep 10

# Проверка подключения к базе данных
info "Проверка подключения к базе данных..."
max_attempts=30
attempt=0

while [ $attempt -lt $max_attempts ]; do
    if docker-compose exec -T db mysqladmin ping -h localhost --silent; then
        info "База данных готова!"
        break
    fi
    attempt=$((attempt + 1))
    echo -n "."
    sleep 1
done

if [ $attempt -eq $max_attempts ]; then
    error "База данных не отвечает после $max_attempts попыток"
fi

# Запуск миграций
info "Запуск миграций базы данных..."
docker-compose exec -T app php database/migrations.php || warn "Миграции уже выполнены или произошла ошибка"

# Запуск расширенных миграций
info "Запуск расширенных миграций..."
docker-compose exec -T app php database/migrations_extended_logging.php || warn "Расширенные миграции уже выполнены или произошла ошибка"

# Первоначальное обновление курсов
info "Первоначальное обновление курсов валют..."
docker-compose exec -T app php scripts/update_rates.php || warn "Не удалось обновить курсы (возможно, API недоступен)"

# Проверка статуса контейнеров
info "Проверка статуса контейнеров..."
docker-compose ps

# Вывод информации о доступе
echo ""
info "✅ Деплой завершен успешно!"
echo ""
echo "📡 Доступные адреса:"
echo "   - API: http://localhost/api/"
echo "   - Админ панель: http://localhost/admin/"
echo ""
echo "📋 Полезные команды:"
echo "   - Просмотр логов: docker-compose logs -f"
echo "   - Остановка: docker-compose down"
echo "   - Перезапуск: docker-compose restart"
echo "   - Обновление курсов: docker-compose exec app php scripts/update_rates.php"
echo ""

