FROM php:8.1-fpm-alpine

# Установка системных зависимостей
RUN apk add --no-cache \
    curl \
    git \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    mysql-client \
    bash \
    tzdata

# Установка PHP расширений
RUN docker-php-ext-install \
    pdo_mysql \
    mysqli \
    zip \
    opcache

# Установка и настройка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Установка часового пояса
ENV TZ=Europe/Moscow
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Создание рабочей директории
WORKDIR /var/www/html

# Копирование файлов проекта
COPY . .

# Установка прав доступа
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Настройка PHP
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" || \
    mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Настройка PHP-FPM
RUN sed -i 's/listen = 127.0.0.1:9000/listen = 9000/' /usr/local/etc/php-fpm.d/www.conf

# Создание директории для логов
RUN mkdir -p /var/log/php-fpm && \
    chown -R www-data:www-data /var/log/php-fpm

# Копирование entrypoint скрипта
COPY scripts/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Установка cron
RUN apk add --no-cache dcron

# Открытие порта
EXPOSE 9000

# Entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]

# Запуск PHP-FPM
CMD ["php-fpm"]

