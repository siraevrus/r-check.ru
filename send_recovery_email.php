<?php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, 'config.env');
$dotenv->load();

use ReproCRM\Utils\EmailNotifier;

$email = 'ruslan@siraev.ru';
$notifier = new EmailNotifier();

echo "📧 Отправка письма восстановления пароля...\n";
echo "Адрес: $email\n\n";

// Создаем ссылку восстановления (симуляция)
$token = bin2hex(random_bytes(32));
$recoveryLink = $_ENV['APP_URL'] . '/recovery.php?token=' . $token;

$subject = 'Восстановление пароля - Система учета продаж';
$message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #667eea; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .button { background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🔐 Восстановление пароля</h1>
        </div>
        <div class='content'>
            <p>Здравствуйте!</p>
            <p>Вы запросили восстановление пароля для вашего аккаунта в системе учета продаж.</p>
            
            <div class='warning'>
                <strong>⏰ Важно:</strong> Эта ссылка действительна 24 часа.
            </div>
            
            <p>Нажмите на кнопку ниже для установки нового пароля:</p>
            
            <a href='$recoveryLink' class='button'>🔑 Восстановить пароль</a>
            
            <p>Или скопируйте эту ссылку в адресную строку:</p>
            <p style='word-break: break-all; background: #f0f0f0; padding: 10px; border-radius: 3px;'>
                $recoveryLink
            </p>
            
            <p>Если вы не запрашивали восстановление пароля, просто проигнорируйте это письмо.</p>
            
        </div>
        <div class='footer'>
            <p>Это автоматическое письмо. Пожалуйста, не отвечайте на него.</p>
            <p>© " . date('Y') . " Система учета продаж</p>
        </div>
    </div>
</body>
</html>
";

try {
    $result = $notifier->send($email, $subject, $message, true);
    
    if ($result) {
        echo "\n✅ УСПЕХ! Письмо восстановления отправлено!\n\n";
        echo "📩 Адрес получателя: $email\n";
        echo "📧 Тема: $subject\n";
        echo "🔗 Ссылка восстановления: $recoveryLink\n\n";
        echo "💌 Письмо должно прийти в течение нескольких секунд.\n";
        echo "✉️  Проверьте папку входящих или спама.\n";
    } else {
        echo "❌ ОШИБКА! Не удалось отправить письмо.\n";
    }
} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n";
}
