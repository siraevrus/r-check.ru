<?php
/**
 * Простой тест отправки email
 */

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем переменные окружения
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, 'config.env');
$dotenv->load();

use ReproCRM\Utils\EmailNotifier;

echo "=== Простой тест отправки email ===\n\n";

// Создаем EmailNotifier
$emailNotifier = new EmailNotifier();

// Простое сообщение
$to = 'ruslansiraev@mailopost.ru';
$subject = 'Простой тест';
$message = 'Это простое тестовое сообщение.';

echo "Отправка на: $to\n";
echo "Тема: $subject\n\n";

try {
    $result = $emailNotifier->send($to, $subject, $message);
    
    if ($result) {
        echo "✅ УСПЕХ! Письмо отправлено!\n";
    } else {
        echo "❌ ОШИБКА! Письмо не отправлено.\n";
    }
} catch (Exception $e) {
    echo "❌ ИСКЛЮЧЕНИЕ: " . $e->getMessage() . "\n";
}

echo "\n=== Тест завершен ===\n";
