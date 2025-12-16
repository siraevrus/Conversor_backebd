#!/bin/bash

# Скрипт для проверки использования API приложениями
# Использование: ./scripts/check_api_usage.sh [remote|local]

MODE=${1:-remote}
SSH_USER="root"
SSH_HOST="81.163.31.224"
REMOTE_PATH="/var/www/conversor.onza.me/html"

# Цвета
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${BLUE}=== Проверка использования API приложениями ===${NC}\n"

if [ "$MODE" = "remote" ]; then
    echo "Проверка на удаленном сервере..."
    echo ""
    
    # Статистика за сегодня
    echo -e "${CYAN}1. Статистика за сегодня:${NC}"
    ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 "${SSH_USER}@${SSH_HOST}" "
        cd ${REMOTE_PATH}
        mysql -u currency_user -pcurrency_password currency_api -e '
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_requests,
                COUNT(DISTINCT device_id) as unique_devices,
                COUNT(DISTINCT ip_address) as unique_ips,
                COUNT(DISTINCT endpoint) as unique_endpoints
            FROM api_requests 
            WHERE DATE(created_at) = CURDATE()
            GROUP BY DATE(created_at);
        ' 2>/dev/null
    "
    
    echo ""
    echo -e "${CYAN}2. Топ 10 самых используемых endpoints:${NC}"
    ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 "${SSH_USER}@${SSH_HOST}" "
        cd ${REMOTE_PATH}
        mysql -u currency_user -pcurrency_password currency_api -e '
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
        ' 2>/dev/null
    "
    
    echo ""
    echo -e "${CYAN}3. Активные устройства за последние 24 часа:${NC}"
    ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 "${SSH_USER}@${SSH_HOST}" "
        cd ${REMOTE_PATH}
        mysql -u currency_user -pcurrency_password currency_api -e '
            SELECT 
                d.device_id,
                d.platform,
                d.app_version,
                COUNT(ar.id) as requests_count,
                MAX(ar.created_at) as last_request
            FROM devices d
            LEFT JOIN api_requests ar ON d.id = ar.device_id
            WHERE ar.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY d.id, d.device_id, d.platform, d.app_version
            ORDER BY requests_count DESC
            LIMIT 20;
        ' 2>/dev/null
    "
    
    echo ""
    echo -e "${CYAN}4. Последние 10 запросов:${NC}"
    ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 "${SSH_USER}@${SSH_HOST}" "
        cd ${REMOTE_PATH}
        mysql -u currency_user -pcurrency_password currency_api -e '
            SELECT 
                ar.created_at as time,
                ar.endpoint,
                ar.method,
                ar.ip_address,
                d.platform,
                d.app_version
            FROM api_requests ar
            LEFT JOIN devices d ON ar.device_id = d.id
            ORDER BY ar.created_at DESC
            LIMIT 10;
        ' 2>/dev/null
    "
    
    echo ""
    echo -e "${CYAN}5. Статистика по платформам:${NC}"
    ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 "${SSH_USER}@${SSH_HOST}" "
        cd ${REMOTE_PATH}
        mysql -u currency_user -pcurrency_password currency_api -e '
            SELECT 
                COALESCE(d.platform, \"Unknown\") as platform,
                COUNT(DISTINCT d.id) as devices_count,
                COUNT(ar.id) as requests_count
            FROM api_requests ar
            LEFT JOIN devices d ON ar.device_id = d.id
            WHERE ar.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY d.platform
            ORDER BY requests_count DESC;
        ' 2>/dev/null
    "
    
    echo ""
    echo -e "${CYAN}6. Статистика по версиям приложений:${NC}"
    ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 "${SSH_USER}@${SSH_HOST}" "
        cd ${REMOTE_PATH}
        mysql -u currency_user -pcurrency_password currency_api -e '
            SELECT 
                d.platform,
                d.app_version,
                COUNT(DISTINCT d.id) as devices_count,
                COUNT(ar.id) as requests_count
            FROM devices d
            LEFT JOIN api_requests ar ON d.id = ar.device_id
            WHERE ar.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY d.platform, d.app_version
            ORDER BY requests_count DESC
            LIMIT 10;
        ' 2>/dev/null
    "
    
    echo ""
    echo -e "${CYAN}7. Проверка использования конкретных endpoints:${NC}"
    ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 "${SSH_USER}@${SSH_HOST}" "
        cd ${REMOTE_PATH}
        mysql -u currency_user -pcurrency_password currency_api -e '
            SELECT 
                endpoint,
                COUNT(*) as count,
                COUNT(DISTINCT device_id) as devices,
                MIN(created_at) as first_use,
                MAX(created_at) as last_use
            FROM api_requests
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY endpoint
            ORDER BY count DESC;
        ' 2>/dev/null
    "
    
else
    echo "Локальная проверка..."
    cd "$(dirname "$0")/.." || exit 1
    
    php -r "
        require_once 'config/database.php';
        \$db = new Database();
        \$conn = \$db->getConnection();
        
        echo \"Статистика за сегодня:\n\";
        \$stmt = \$conn->query(\"
            SELECT 
                COUNT(*) as total,
                COUNT(DISTINCT device_id) as devices,
                COUNT(DISTINCT endpoint) as endpoints
            FROM api_requests 
            WHERE DATE(created_at) = CURDATE()
        \");
        \$result = \$stmt->fetch();
        echo \"Запросов: \" . \$result['total'] . \"\n\";
        echo \"Устройств: \" . \$result['devices'] . \"\n\";
        echo \"Endpoints: \" . \$result['endpoints'] . \"\n\";
    "
fi

echo ""
echo -e "${GREEN}=== Проверка завершена ===${NC}"
echo ""
echo -e "${YELLOW}Совет:${NC} Для детального мониторинга используйте админ-панель:"
echo "   https://conversor.onza.me/admin/"

