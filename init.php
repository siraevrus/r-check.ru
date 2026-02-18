<?php

require_once __DIR__ . '/vendor/autoload.php';

use ReproCRM\Config\Config;
use ReproCRM\Config\Database;

// Загружаем конфигурацию
Config::load();

echo "Инициализация системы учета продаж по промокодам...\n\n";

try {
    // Проверяем подключение к базе данных
    $db = Database::getInstance();
    echo "✓ Подключение к базе данных установлено\n";
    
    // Проверяем существование таблиц
    $tables = ['admins', 'promo_codes', 'doctors', 'sales', 'promo_history', 'rate_limits', 'password_resets'];
    
    foreach ($tables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        
        if ($stmt->fetch()) {
            echo "✓ Таблица '{$table}' существует\n";
        } else {
            echo "✗ Таблица '{$table}' не найдена\n";
        }
    }
    
    // Проверяем наличие первого администратора
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM admins");
    $stmt->execute();
    $adminCount = $stmt->fetch()['count'];
    
    if ($adminCount > 0) {
        echo "✓ Найдено {$adminCount} администраторов\n";
    } else {
        echo "✗ Администраторы не найдены\n";
    }
    
    echo "\nИнициализация завершена!\n";
    echo "\nДоступ к системе:\n";
    echo "- URL: " . Config::getAppUrl() . "\n";
    echo "- Email администратора: admin@admin.com\n";
    echo "- Пароль администратора: admin123\n";
    echo "\nВАЖНО: Измените пароль администратора после первого входа!\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка инициализации: " . $e->getMessage() . "\n";
    echo "\nУбедитесь, что:\n";
    echo "1. MySQL сервер запущен\n";
    echo "2. База данных создана (см. database/schema.sql)\n";
    echo "3. Настройки подключения в config.env корректны\n";
}
