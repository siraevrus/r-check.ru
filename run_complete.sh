#!/bin/bash

echo "🚀 Полная настройка и запуск системы учета продаж по промокодам"
echo "============================================================="

# Добавляем XAMPP в PATH
export PATH="/Applications/XAMPP/bin:$PATH"

# Проверяем PHP
if ! command -v php &> /dev/null; then
    echo "❌ PHP не найден. Убедитесь, что XAMPP установлен."
    exit 1
fi

echo "✅ PHP найден: $(php --version | head -n1)"

# Устанавливаем зависимости
echo "📦 Установка зависимостей..."
if [ ! -f "composer.phar" ]; then
    curl -sS https://getcomposer.org/installer | php
fi

php composer.phar install --no-dev --optimize-autoloader

# Настраиваем конфигурацию для SQLite
echo "⚙️  Настройка конфигурации..."
if [ ! -f "config.env" ]; then
    cp config.env.example config.env
fi

# Обновляем настройки для SQLite
sed -i '' 's/DB_TYPE=mysql/DB_TYPE=sqlite/' config.env 2>/dev/null || echo "DB_TYPE=sqlite" >> config.env
sed -i '' 's/DB_HOST=.*/DB_HOST=/' config.env 2>/dev/null || true
sed -i '' 's/DB_USER=.*/DB_USER=/' config.env 2>/dev/null || true
sed -i '' 's/DB_PASS=.*/DB_PASS=/' config.env 2>/dev/null || true

# Создаем директорию для базы данных
mkdir -p database

# Инициализируем базу данных
echo "🗄️  Инициализация базы данных SQLite..."
php -r "
require_once 'vendor/autoload.php';
use ReproCRM\Config\Config;
Config::load();
try {
    \$db = ReproCRM\Config\Database::getInstance();
    echo '✅ База данных SQLite инициализирована\n';
} catch (Exception \$e) {
    echo '❌ Ошибка инициализации базы данных: ' . \$e->getMessage() . '\n';
    exit(1);
}
"

if [ $? -ne 0 ]; then
    echo "❌ Не удалось инициализировать базу данных"
    exit 1
fi

echo ""
echo "🎉 Система полностью настроена и готова к работе!"
echo ""
echo "🌐 Веб-интерфейс: http://localhost:8000"
echo ""
echo "🔑 Данные для входа:"
echo "   📧 Email: admin@reprosystem.ru"
echo "   🔒 Пароль: admin123"
echo ""
echo "👨‍⚕️ Тестовый врач:"
echo "   📧 Email: doctor@test.ru"
echo "   🔒 Пароль: admin123"
echo "   🎫 Промокод: TEST001"
echo ""
echo "📋 Для остановки нажмите Ctrl+C"
echo ""

# Запускаем сервер
php -S localhost:8000
