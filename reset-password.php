<?php

require_once __DIR__ . '/vendor/autoload.php';
use ReproCRM\Config\Database;
use ReproCRM\Utils\Response;

$_ENV['DB_TYPE'] = 'sqlite';
$db = Database::getInstance();
$pdo = $db;

$token = $_GET['token'] ?? '';
$message = '';
$messageType = '';
$isValidToken = false;

// Проверяем токен при загрузке страницы
if (!empty($token)) {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("
        SELECT * FROM password_resets 
        WHERE token = ? AND expires_at > ? AND used_at IS NULL
    ");
    $stmt->execute([$token, $now]);
    $resetRecord = $stmt->fetch();
    
    if ($resetRecord) {
        $isValidToken = true;
    } else {
        $message = 'Неверный или истекший токен восстановления пароля.';
        $messageType = 'error';
    }
}

// Обработка формы сброса пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isValidToken) {
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    if (empty($password)) {
        $message = 'Пароль обязателен для заполнения';
        $messageType = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Пароль должен содержать минимум 6 символов';
        $messageType = 'error';
    } elseif ($password !== $passwordConfirm) {
        $message = 'Пароли не совпадают';
        $messageType = 'error';
    } else {
        // Проверяем токен еще раз
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            SELECT * FROM password_resets 
            WHERE token = ? AND expires_at > ? AND used_at IS NULL
        ");
        $stmt->execute([$token, $now]);
        $resetRecord = $stmt->fetch();
        
        if (!$resetRecord) {
            $message = 'Токен истек или уже был использован';
            $messageType = 'error';
        } else {
            $email = $resetRecord['email'];
            
            // Ищем пользователя
            $user = null;
            
            // Проверяем в таблице users (врачи)
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $userData = $stmt->fetch();
            
            if ($userData) {
                $user = new \ReproCRM\Models\User($userData);
            } else {
                // Проверяем в таблице admins
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
                $stmt->execute([$email]);
                $adminData = $stmt->fetch();
                
                if ($adminData) {
                    $user = new \ReproCRM\Models\Admin($adminData);
                }
            }
            
            if (!$user) {
                $message = 'Пользователь не найден';
                $messageType = 'error';
            } else {
                try {
                    // Обновляем пароль
                    $user->setPassword($password);
                    
                    if (!$user->save()) {
                        $message = 'Ошибка при обновлении пароля';
                        $messageType = 'error';
                    } else {
                        // Помечаем токен как использованный
                        $now = date('Y-m-d H:i:s');
                        $stmt = $pdo->prepare("UPDATE password_resets SET used_at = ? WHERE token = ?");
                        $stmt->execute([$now, $token]);
                        
                        $message = 'Пароль успешно изменен! Теперь вы можете войти в систему.';
                        $messageType = 'success';
                        $isValidToken = false; // Скрываем форму после успешного сброса
                    }
                } catch (Exception $e) {
                    error_log("Password reset error: " . $e->getMessage());
                    $message = 'Ошибка при обновлении пароля: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сброс пароля - Система учета продаж</title>
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
                    Сброс пароля
                </h2>
                <p class="text-sm text-gray-600">
                    <?= $isValidToken ? 'Введите новый пароль для вашего аккаунта' : 'Ссылка для сброса пароля' ?>
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

            <?php if ($isValidToken): ?>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Новый пароль <span class="text-red-500">*</span>
                    </label>
                    <input id="password" name="password" type="password" required minlength="6"
                           class="form-input appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                           placeholder="Минимум 6 символов">
                </div>

                <div>
                    <label for="password_confirm" class="block text-sm font-medium text-gray-700 mb-2">
                        Подтвердите пароль <span class="text-red-500">*</span>
                    </label>
                    <input id="password_confirm" name="password_confirm" type="password" required minlength="6"
                           class="form-input appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                           placeholder="Повторите пароль">
                </div>

                <div>
                    <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white btn-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-save text-blue-500 group-hover:text-blue-400"></i>
                        </span>
                        Изменить пароль
                    </button>
                </div>
            </form>
            <?php elseif (empty($token)): ?>
            <div class="text-center">
                <p class="text-gray-600 mb-4">Токен не указан. Используйте ссылку из письма для сброса пароля.</p>
                <a href="/recovery.php" class="text-blue-600 hover:text-blue-500">
                    Запросить новую ссылку для сброса пароля
                </a>
            </div>
            <?php endif; ?>

            <div class="text-center mt-6 space-y-2">
                <p class="text-sm text-gray-600">
                    <a href="/user.php" class="font-medium text-blue-600 hover:text-blue-500">
                        <i class="fas fa-arrow-left mr-1"></i>Вернуться к входу
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Валидация паролей в реальном времени
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const passwordConfirm = document.getElementById('password_confirm');
            
            if (password && passwordConfirm) {
                function validatePasswords() {
                    if (password.value && password.value.length < 6) {
                        password.setCustomValidity('Пароль должен содержать минимум 6 символов');
                    } else if (passwordConfirm.value && password.value !== passwordConfirm.value) {
                        passwordConfirm.setCustomValidity('Пароли не совпадают');
                    } else {
                        password.setCustomValidity('');
                        passwordConfirm.setCustomValidity('');
                    }
                }
                
                password.addEventListener('input', validatePasswords);
                passwordConfirm.addEventListener('input', validatePasswords);
            }
        });
    </script>
</body>
</html>

