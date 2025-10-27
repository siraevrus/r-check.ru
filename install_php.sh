#!/bin/bash

echo "🐘 Установка PHP для системы учета продаж по промокодам"
echo "====================================================="

# Функция для проверки установки PHP
check_php() {
    if command -v php &> /dev/null; then
        echo "✅ PHP найден: $(php --version | head -n1)"
        return 0
    else
        echo "❌ PHP не найден"
        return 1
    fi
}

# Проверяем, есть ли уже PHP
if check_php; then
    echo "🎉 PHP уже установлен!"
    exit 0
fi

echo "🔍 Поиск альтернативных способов установки PHP..."

# Способ 1: Проверяем XAMPP
if [ -f "/Applications/XAMPP/bin/php" ]; then
    echo "📦 Найден XAMPP, добавляем PHP в PATH..."
    echo 'export PATH="/Applications/XAMPP/bin:$PATH"' >> ~/.zshrc
    source ~/.zshrc
    if check_php; then
        echo "✅ PHP из XAMPP готов к использованию!"
        exit 0
    fi
fi

# Способ 2: Проверяем MAMP
if [ -f "/Applications/MAMP/bin/php/php8.2.0/bin/php" ]; then
    echo "📦 Найден MAMP, добавляем PHP в PATH..."
    echo 'export PATH="/Applications/MAMP/bin/php/php8.2.0/bin:$PATH"' >> ~/.zshrc
    source ~/.zshrc
    if check_php; then
        echo "✅ PHP из MAMP готов к использованию!"
        exit 0
    fi
fi

# Способ 3: Установка через Homebrew (с исправлением прав)
echo "🍺 Попытка установки через Homebrew..."

# Пробуем исправить права доступа
echo "🔧 Исправление прав доступа к Homebrew..."
sudo chown -R $(whoami) /opt/homebrew 2>/dev/null || echo "⚠️  Не удалось исправить права доступа"

# Пробуем установить PHP
if brew install php 2>/dev/null; then
    echo "✅ PHP установлен через Homebrew!"
    if check_php; then
        echo "🎉 PHP готов к использованию!"
        exit 0
    fi
else
    echo "❌ Не удалось установить PHP через Homebrew"
fi

# Способ 4: Скачивание готового бинарника PHP
echo "📥 Попытка скачивания готового бинарника PHP..."

# Создаем директорию для PHP
mkdir -p ~/php-bin
cd ~/php-bin

# Скачиваем PHP (пример для macOS ARM64)
echo "📥 Скачивание PHP для macOS..."
curl -L -o php.tar.gz "https://github.com/php/php-src/archive/php-8.2.0.tar.gz" 2>/dev/null || {
    echo "❌ Не удалось скачать PHP"
    echo ""
    echo "🔧 Ручная установка:"
    echo "1. Скачайте PHP с https://www.php.net/downloads.php"
    echo "2. Установите пакет"
    echo "3. Добавьте PHP в PATH"
    echo ""
    echo "Или используйте Docker:"
    echo "docker run -it --rm -v \$(pwd):/app -w /app php:8.2-cli php -S localhost:8000"
    exit 1
}

echo "✅ PHP скачан, но требует компиляции"
echo ""
echo "🚀 Рекомендуемые способы установки PHP:"
echo ""
echo "1. 🍺 Homebrew (рекомендуется):"
echo "   sudo chown -R \$(whoami) /opt/homebrew"
echo "   brew install php"
echo ""
echo "2. 📦 XAMPP:"
echo "   Скачайте с https://www.apachefriends.org/"
echo "   Установите пакет"
echo ""
echo "3. 🐳 Docker (если PHP не устанавливается):"
echo "   ./start.sh  # Запуск через Docker"
echo ""
echo "4. 📥 Официальный сайт:"
echo "   Скачайте с https://www.php.net/downloads.php"
echo "   Установите пакет"
echo ""
echo "После установки PHP выполните:"
echo "   composer install"
echo "   php -S localhost:8000"
