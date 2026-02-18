#!/bin/bash

echo "🚀 Запуск системы учета продаж по промокодам (локальная версия)"
echo "============================================================="

# Добавляем XAMPP в PATH
export PATH="/Applications/XAMPP/bin:$PATH"

# Проверяем PHP
if ! command -v php &> /dev/null; then
    echo "❌ PHP не найден. Убедитесь, что XAMPP установлен."
    exit 1
fi

echo "✅ PHP найден: $(php --version | head -n1)"

# Проверяем Composer
if [ ! -f "composer.phar" ]; then
    echo "📦 Установка Composer..."
    curl -sS https://getcomposer.org/installer | php
fi

# Устанавливаем зависимости
echo "📦 Установка зависимостей..."
php composer.phar install --no-dev --optimize-autoloader

# Создаем конфигурацию
if [ ! -f "config.env" ]; then
    echo "⚙️  Создание конфигурации..."
    cp config.env.example config.env
    
    # Обновляем настройки для локальной разработки
    sed -i '' 's/DB_HOST=localhost/DB_HOST=127.0.0.1/' config.env
    sed -i '' 's/DB_USER=root/DB_USER=root/' config.env
    sed -i '' 's/DB_PASS=/DB_PASS=/' config.env
    sed -i '' 's/APP_URL=http:\/\/localhost:8000/APP_URL=http:\/\/localhost:8000/' config.env
fi

echo "✅ Конфигурация готова"

# Запускаем встроенный PHP сервер
echo "🌐 Запуск веб-сервера на http://localhost:8000"
echo ""
echo "🔑 Данные для входа:"
echo "   Email: admin@reprosystem.ru"
echo "   Пароль: admin123"
echo ""
echo "⚠️  ВАЖНО: Для полной работы системы нужна база данных MySQL!"
echo "   Запустите XAMPP Control Panel и включите MySQL"
echo "   Или используйте: ./start.sh (Docker версия)"
echo ""
echo "📋 Для остановки нажмите Ctrl+C"
echo ""

# Запускаем сервер
php -S localhost:8000
