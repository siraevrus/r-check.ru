<?php
require_once __DIR__ . '/vendor/autoload.php';

use ReproCRM\Config\Config;
use ReproCRM\Utils\EmailNotifier;

// Загружаем конфигурацию из config.env
Config::load();

$testEmail = 'ruslan@siraev.ru';

echo "📧 Отправка тестового письма...\n";
echo "Адрес получателя: $testEmail\n";
echo "SMTP сервер: " . ($_ENV['MAIL_HOST'] ?? 'не установлен') . "\n";
echo "Порт: " . ($_ENV['MAIL_PORT'] ?? 'не установлен') . "\n";
echo "Шифрование: " . ($_ENV['MAIL_ENCRYPTION'] ?? 'не установлено') . "\n";
echo "Отправитель: " . ($_ENV['MAIL_FROM_ADDRESS'] ?? 'не установлен') . "\n";
echo "Пользователь: " . ($_ENV['MAIL_USERNAME'] ?? 'не установлен') . "\n\n";

$subject = 'Тестовое письмо - Система учета продаж';
$message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #667eea; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { padding: 20px; background: #f9f9f9; border-radius: 0 0 5px 5px; }
        .success-box { background: #d4edda; border: 2px solid #28a745; padding: 20px; text-align: center; margin: 20px 0; border-radius: 5px; }
        .info-box { background: #e7f3ff; border: 1px solid #2196F3; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>✅ Тестовое письмо</h1>
        </div>
        <div class='content'>
            <p>Здравствуйте!</p>
            
            <div class='success-box'>
                <h2 style='margin: 0; color: #28a745;'>✓ Отправка работает!</h2>
                <p style='margin: 10px 0 0 0;'>Это тестовое письмо подтверждает, что система отправки email настроена правильно.</p>
            </div>
            
            <div class='info-box'>
                <strong>📋 Информация о настройках:</strong>
                <ul style='margin: 10px 0; padding-left: 20px;'>
                    <li><strong>SMTP сервер:</strong> " . htmlspecialchars($_ENV['MAIL_HOST'] ?? 'не установлен') . "</li>
                    <li><strong>Порт:</strong> " . htmlspecialchars($_ENV['MAIL_PORT'] ?? 'не установлен') . "</li>
                    <li><strong>Шифрование:</strong> " . htmlspecialchars($_ENV['MAIL_ENCRYPTION'] ?? 'не установлено') . "</li>
                    <li><strong>Отправитель:</strong> " . htmlspecialchars($_ENV['MAIL_FROM_ADDRESS'] ?? 'не установлен') . "</li>
                    <li><strong>Время отправки:</strong> " . date('d.m.Y H:i:s') . "</li>
                </ul>
            </div>
            
            <p>Если вы получили это письмо, значит:</p>
            <ol>
                <li>✅ Настройки SMTP корректны</li>
                <li>✅ PHPMailer работает правильно</li>
                <li>✅ Письма доставляются получателям</li>
            </ol>
            
            <p>Система готова к отправке уведомлений пользователям!</p>
        </div>
        <div class='footer'>
            <p>Это автоматическое тестовое письмо.</p>
            <p>© " . date('Y') . " Система учета продаж | <a href='" . ($_ENV['APP_URL'] ?? 'http://r-check.ru') . "'>Вернуться на сайт</a></p>
        </div>
    </div>
</body>
</html>
";

// Пробуем разные варианты подключения
$configs = [
    ['port' => 465, 'encryption' => 'ssl', 'name' => 'SSL на порту 465'],
    ['port' => 587, 'encryption' => 'tls', 'name' => 'TLS на порту 587'],
];

foreach ($configs as $config) {
    echo "\n🔄 Пробуем: {$config['name']}...\n";
    
    $testNotifier = new EmailNotifier([
        'smtp_host' => $_ENV['MAIL_HOST'] ?? 'smtp.msndr.net',
        'smtp_port' => $config['port'],
        'smtp_secure' => $config['encryption'],
        'smtp_username' => $_ENV['MAIL_USERNAME'] ?? '',
        'smtp_password' => $_ENV['MAIL_PASSWORD'] ?? '',
        'from_email' => $_ENV['MAIL_FROM_ADDRESS'] ?? '',
        'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Система учета продаж',
        'smtp_debug' => 2,
    ]);
    
    try {
        $result = $testNotifier->send($testEmail, $subject, $message, true);
        if ($result) {
            echo "\n✅ УСПЕХ! Тестовое письмо отправлено с использованием {$config['name']}!\n\n";
            echo "📩 Адрес получателя: $testEmail\n";
            echo "📧 Тема: $subject\n";
            echo "⏰ Время отправки: " . date('d.m.Y H:i:s') . "\n\n";
            echo "💌 Письмо должно прийти в течение нескольких секунд.\n";
            echo "✉️  Проверьте папку входящих или спама на адресе $testEmail\n";
            exit(0);
        }
    } catch (Exception $e) {
        echo "❌ Не удалось с {$config['name']}: " . $e->getMessage() . "\n";
    }
}

echo "\n❌ Все варианты подключения не сработали.\n";
echo "Возможно, проблема в учетных данных (логин/пароль).\n";
echo "Проверьте:\n";
echo "  1. Правильность пароля в config.env\n";
echo "  2. Что пароль приложения актуален (если используется двухфакторная аутентификация)\n";
echo "  3. Что аккаунт не заблокирован\n";
