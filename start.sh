#!/bin/bash

echo "🚀 Запуск системы учета продаж по промокодам..."
echo "=============================================="

# Проверяем, запущен ли Docker
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker не запущен. Пожалуйста, запустите Docker Desktop"
    exit 1
fi

# Создаем конфигурационный файл, если его нет
if [ ! -f "config.env" ]; then
    echo "📝 Создание конфигурационного файла..."
    cp config.env.example config.env
    echo "✅ Конфигурационный файл создан из примера"
    echo "⚠️  Не забудьте отредактировать config.env с вашими настройками!"
fi

# Запускаем контейнеры
echo "🐳 Запуск Docker контейнеров..."
docker-compose up -d

# Ждем запуска базы данных
echo "⏳ Ожидание запуска базы данных..."
sleep 10

# Проверяем статус контейнеров
echo "📊 Статус контейнеров:"
docker-compose ps

echo ""
echo "✅ Система запущена!"
echo ""
echo "🌐 Веб-интерфейс: http://localhost:8000"
echo "🗄️  phpMyAdmin: http://localhost:8080"
echo "📊 База данных: localhost:3306"
echo ""
echo "🔑 Данные для входа:"
echo "   Email: admin@reprosystem.ru"
echo "   Пароль: admin123"
echo ""
echo "📋 Для остановки системы выполните: docker-compose down"
echo "📋 Для просмотра логов: docker-compose logs -f"
