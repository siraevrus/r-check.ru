<?php

session_start();

// Если уже залогинен - перенаправляем
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /promo_codes.php');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use ReproCRM\Config\Config;

// Загружаем конфигурацию
Config::load();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $message = 'Пожалуйста, введите email и пароль';
        $messageType = 'error';
    } else {
        try {
            // Подключаемся к базе данных
            $_ENV['DB_TYPE'] = 'sqlite';
            $dbPath = __DIR__ . '/database/reprocrm.db';
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Ищем администратора
            $stmt = $pdo->prepare("SELECT id, email, password_hash FROM admins WHERE email = ?");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                // Авторизация успешна (session_start уже вызван в начале файла)
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_logged_in'] = true;

                header('Location: /promo_codes.php');
                exit;
            } else {
                $message = 'Неверный логин или пароль';
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = 'Неверный логин или пароль';
            $messageType = 'error';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(Config::getAppName()) ?> - Авторизация администратора</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="min-h-screen gradient-bg">
    <div class="flex items-center justify-center min-h-screen">
        <div class="bg-white rounded-2xl p-8 shadow-2xl max-w-md w-full mx-4">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    <?= htmlspecialchars(Config::getAppName()) ?>
                </h1>
                <p class="text-gray-600">Авторизация администратора</p>
            </div>

            <form method="POST" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email адрес
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input type="email" id="email" name="email" required
                               class="admin-email-input w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent <?= $messageType === 'error' ? 'border-red-500 border-2' : '' ?>"
                               placeholder="admin@admin.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Пароль
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" id="password" name="password" required
                               class="admin-password-input w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent <?= $messageType === 'error' ? 'border-red-500 border-2' : '' ?>"
                               placeholder="Введите пароль">
                        <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                        </button>
                    </div>
                </div>
                
                <?php if ($messageType === 'error'): ?>
                <div class="p-4 rounded-lg bg-red-100 text-red-800 border-l-4 border-red-500">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                        <span>Неверный логин или пароль</span>
                    </div>
                </div>
                <?php endif; ?>

                <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-700 flex items-center justify-center transition-colors duration-200">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Войти в систему
                </button>
            </form>

            <div class="mt-8 text-center">
                <a href="/" class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Вернуться на главную
                </a>
            </div>

        </div>
    </div>

    <script>
        // Функциональность показа/скрытия пароля
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');

        if (togglePassword && passwordField) {
            togglePassword.addEventListener('click', function() {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);

                // Меняем иконку
                const icon = this.querySelector('i');
                if (type === 'text') {
                    icon.className = 'fas fa-eye-slash text-gray-600';
                } else {
                    icon.className = 'fas fa-eye text-gray-400 hover:text-gray-600';
                }
            });
        }
    </script>
</body>
</html>
