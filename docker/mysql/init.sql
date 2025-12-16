-- Инициализация базы данных
-- Этот файл выполняется при первом запуске контейнера MySQL

-- Создание базы данных (если не создана через переменные окружения)
CREATE DATABASE IF NOT EXISTS currency_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Использование базы данных
USE currency_api;

