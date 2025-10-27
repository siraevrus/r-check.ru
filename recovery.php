<?php

require_once __DIR__ . '/vendor/autoload.php';
use ReproCRM\Config\Database;
use ReproCRM\Utils\Validator;
use ReproCRM\Utils\EmailNotifier;

$_ENV['DB_TYPE'] = 'sqlite';
$db = Database::getInstance();
$pdo = $db;
$validator = new Validator();
$emailNotifier = new EmailNotifier();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    // Валидация email
    if (empty($email)) {
        $message = 'Email обязателен для заполнения';
        $messageType = 'error';
    } elseif (!$validator->validateEmail($email)) {
        $message = 'Некорректный формат email';
        $messageType = 'error';
    } else {
        // Проверяем, существует ли пользователь с таким email
        $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            try {
                // Генерируем новый пароль
                $newPassword = bin2hex(random_bytes(6)); // 12-символьный пароль
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Обновляем пароль в базе данных
                $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = datetime('now') WHERE id = ?");
                $updateStmt->execute([$passwordHash, $user['id']]);
                
                // Отправляем новый пароль на email
                $subject = 'Ваш новый пароль - Система учета продаж';
                $fullName = $user['full_name'] ?? 'Пользователь';
                
                $emailBody = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #667eea; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { padding: 20px; background: #f9f9f9; border-radius: 0 0 5px 5px; }
        .password-box { background: white; border: 2px solid #667eea; padding: 20px; text-align: center; margin: 20px 0; border-radius: 5px; }
        .password-box h2 { color: #667eea; margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; }
        .password-box .password { font-size: 28px; font-weight: bold; color: #333; font-family: monospace; letter-spacing: 2px; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🔐 Восстановление пароля</h1>
        </div>
        <div class='content'>
            <p>Здравствуйте, <strong>$fullName</strong>!</p>
            
            <p>Ваш пароль был успешно восстановлен. Ниже находится ваш новый пароль:</p>
            
            <div class='password-box'>
                <h2>Ваш новый пароль</h2>
                <div class='password'>$newPassword</div>
            </div>
            
            <div class='warning'>
                <strong>⚠️ Важно:</strong>
                <ul>
                    <li>Это ваш новый пароль для входа в систему</li>
                    <li>Сохраните его в безопасном месте</li>
                    <li>После входа рекомендуем изменить пароль на более безопасный</li>
                    <li>Никому не передавайте этот пароль</li>
                </ul>
            </div>
            
            <p><strong>Как использовать новый пароль:</strong></p>
            <ol>
                <li>Перейдите на <a href='" . ($_ENV['APP_URL'] ?? 'http://localhost:8000') . "/user.php'>страницу входа</a></li>
                <li>Введите ваш email: <strong>$email</strong></li>
                <li>Введите новый пароль (скопируйте выше)</li>
                <li>Нажмите кнопку 'Войти'</li>
            </ol>
            
            <p>После входа в систему, пожалуйста, измените пароль на более удобный в разделе профиля.</p>
        </div>
        <div class='footer'>
            <p>Это автоматическое письмо. Пожалуйста, не отвечайте на него.</p>
            <p>© " . date('Y') . " Система учета продаж | <a href='" . ($_ENV['APP_URL'] ?? 'http://localhost:8000') . "'>Вернуться на сайт</a></p>
        </div>
    </div>
</body>
</html>
                ";
                
                // Отправляем письмо
                $emailResult = $emailNotifier->send($email, $subject, $emailBody, true);
                
                if ($emailResult) {
                    $message = 'Новый пароль был отправлен на указанный email. Проверьте почту и используйте полученный пароль для входа.';
                    $messageType = 'success';
                    error_log("Password recovery successful for email: " . $email . " (User ID: " . $user['id'] . ")");
                } else {
                    $message = 'Новый пароль был сгенерирован, но произошла ошибка при отправке письма. Пожалуйста, свяжитесь с администратором.';
                    $messageType = 'warning';
                    error_log("Password reset for user but email sending failed: " . $email);
                }
                
            } catch (Exception $e) {
                $message = 'Ошибка при восстановлении пароля: ' . $e->getMessage();
                $messageType = 'error';
                error_log("Password recovery error: " . $e->getMessage());
            }
        } else {
            // Email не найден - показываем успешное сообщение для безопасности
            // (не раскрываем информацию о наличии email в системе)
            $message = 'Если пользователь с указанным email существует, на него будет отправлено письмо с новым паролем.';
            $messageType = 'success';
            error_log("Password recovery attempted for non-existent email: " . $email);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Восстановление пароля - Система учета продаж</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: white;
        }
        .form-input {
            transition: all 0.3s ease;
        }
        .form-input:focus {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
    </style>
</head>
<body class="min-h-screen gradient-bg">
    <div class="flex items-center justify-center min-h-screen">
        <div class="bg-white rounded-2xl p-8 shadow-2xl max-w-md w-full mx-4">
            <div class="text-center mb-8">
                <div class="mx-auto h-16 w-16 bg-blue-600 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-key text-white text-2xl"></i>
                </div>
                <h2 class="text-3xl font-extrabold text-gray-900 mb-2">
                    Восстановление пароля
                </h2>
                <p class="text-sm text-gray-600">
                    Введите email, на который зарегистрирован ваш аккаунт
                </p>
            </div>

            <?php if ($message): ?>
            <div class="rounded-lg p-4 mb-6 <?= $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
                <div class="flex items-center">
                    <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3"></i>
                    <div>
                        <h3 class="text-sm font-medium">
                            <?= $messageType === 'success' ? 'Успешно!' : 'Ошибка' ?>
                        </h3>
                        <div class="mt-1 text-sm">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input id="email" name="email" type="email" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           class="form-input appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                           placeholder="user@example.com">
                </div>

                <div>
                    <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white btn-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-paper-plane text-blue-500 group-hover:text-blue-400"></i>
                        </span>
                        Восстановить пароль
                    </button>
                </div>
            </form>

            <div class="text-center mt-6 space-y-2">
                <p class="text-sm text-gray-600">
                    Вспомнили пароль?
                    <a href="/user.php" class="font-medium text-blue-600 hover:text-blue-500">
                        Войти в систему
                    </a>
                </p>
                <p class="text-sm">
                    <a href="/" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-arrow-left mr-1"></i>Вернуться на главную
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Валидация email в реальном времени
        document.addEventListener('DOMContentLoaded', function() {
            const email = document.getElementById('email');

            email.addEventListener('input', function() {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (this.value && !emailRegex.test(this.value)) {
                    this.setCustomValidity('Некорректный формат email');
                } else {
                    this.setCustomValidity('');
                }
            });
        });
    </script>
</body>
</html>
