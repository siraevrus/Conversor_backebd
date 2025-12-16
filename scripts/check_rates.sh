#!/bin/bash

# Скрипт для проверки актуальности курсов валют
# Использование: ./scripts/check_rates.sh [remote|local]

MODE=${1:-remote}
SSH_USER="root"
SSH_HOST="81.163.31.224"
REMOTE_PATH="/var/www/conversor.onza.me/html"

# Цвета
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}=== Проверка актуальности курсов валют ===${NC}\n"

if [ "$MODE" = "remote" ]; then
    echo "Проверка на удаленном сервере..."
    echo ""
    
    # Проверка времени последнего обновления
    echo -e "${BLUE}1. Время последнего обновления:${NC}"
    ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 "${SSH_USER}@${SSH_HOST}" "
        cd ${REMOTE_PATH}
        mysql -u currency_user -pcurrency_password currency_api -e '
            SELECT 
                MAX(last_updated) as last_update,
                TIMESTAMPDIFF(MINUTE, MAX(last_updated), NOW()) as minutes_ago,
                COUNT(*) as total_rates
            FROM exchange_rates;
        ' 2>/dev/null
    "
    
    echo ""
    echo -e "${BLUE}2. Примеры курсов (EUR, RUB, GBP):${NC}"
    ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 "${SSH_USER}@${SSH_HOST}" "
        cd ${REMOTE_PATH}
        mysql -u currency_user -pcurrency_password currency_api -e '
            SELECT 
                target_currency,
                rate,
                last_updated
            FROM exchange_rates 
            WHERE target_currency IN (\"EUR\", \"RUB\", \"GBP\")
            ORDER BY target_currency;
        ' 2>/dev/null
    "
    
    echo ""
    echo -e "${BLUE}3. Проверка через API:${NC}"
    echo "USD -> EUR:"
    curl -k -s "https://conversor.onza.me/api/rates?base=USD&target=EUR" | python3 -m json.tool 2>/dev/null | grep -E '"rate"|"last_updated"' || echo "Ошибка запроса"
    
    echo ""
    echo "USD -> RUB:"
    curl -k -s "https://conversor.onza.me/api/rates?base=USD&target=RUB" | python3 -m json.tool 2>/dev/null | grep -E '"rate"|"last_updated"' || echo "Ошибка запроса"
    
    echo ""
    echo -e "${BLUE}4. Статус cron задачи:${NC}"
    ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 "${SSH_USER}@${SSH_HOST}" "
        crontab -l | grep update_rates || echo 'Cron задача не найдена'
    "
    
    echo ""
    echo -e "${BLUE}5. Последние логи обновления:${NC}"
    ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 "${SSH_USER}@${SSH_HOST}" "
        tail -5 /var/log/conversor_update.log 2>/dev/null || echo 'Лог пуст или не существует'
    "
    
    echo ""
    echo -e "${BLUE}6. Логи обновлений из БД:${NC}"
    ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 "${SSH_USER}@${SSH_HOST}" "
        cd ${REMOTE_PATH}
        mysql -u currency_user -pcurrency_password currency_api -e '
            SELECT 
                base_currency,
                rates_count,
                success,
                created_at,
                execution_time_ms
            FROM rate_update_logs 
            ORDER BY created_at DESC 
            LIMIT 5;
        ' 2>/dev/null || echo 'Таблица rate_update_logs не найдена'
    "
    
else
    echo "Проверка локально..."
    cd "$(dirname "$0")/.." || exit 1
    
    php -r "
        require_once 'config/database.php';
        \$db = new Database();
        \$conn = \$db->getConnection();
        
        \$stmt = \$conn->query('SELECT MAX(last_updated) as last_update, COUNT(*) as total FROM exchange_rates');
        \$result = \$stmt->fetch();
        
        echo 'Последнее обновление: ' . \$result['last_update'] . PHP_EOL;
        echo 'Всего курсов: ' . \$result['total'] . PHP_EOL;
    "
fi

echo ""
echo -e "${GREEN}=== Проверка завершена ===${NC}"

