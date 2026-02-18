<?php

// Проверка авторизации пользователя
session_start();
$userEmail = $_SESSION['user_email'] ?? '';
$userLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

if (!$userLoggedIn || empty($userEmail)) {
    header('Location: /user.php');
    exit;
}


require_once __DIR__ . '/vendor/autoload.php';
use ReproCRM\Config\Database;
use ReproCRM\Models\User;
use ReproCRM\Utils\Validator;

$_ENV['DB_TYPE'] = 'sqlite';
$db = Database::getInstance();
$pdo = $db;
$validator = new Validator();

$message = '';
$messageType = '';

// Получаем данные пользователя
try {
    $user = User::findByEmail($userEmail);

    if (!$user) {
        header('Location: /user.php');
        exit;
    }

    // Проверяем, что объект загружен правильно
    if (!$user->id || !$user->email) {
        throw new Exception('Не удалось загрузить данные пользователя');
    }

    // Получаем промокод пользователя
    if ($user->promo_code_id) {
        $stmt = $pdo->prepare("SELECT code FROM promo_codes WHERE id = ?");
        $stmt->execute([$user->promo_code_id]);
        $promoCode = $stmt->fetch()['code'];
    } else {
        $promoCode = 'Не назначен';
    }

} catch (Exception $e) {
    $error = 'Ошибка при загрузке данных: ' . $e->getMessage();
    $user = null;
}

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!$user) {
        $message = 'Ошибка: данные пользователя не загружены';
        $messageType = 'error';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmNewPassword = $_POST['confirm_new_password'] ?? '';

        $errors = [];

        // Валидация данных
        if (empty($fullName)) {
            $errors[] = 'ФИО обязательно для заполнения';
        } elseif (!$validator->validateFullName($fullName)) {
            $errors[] = 'ФИО должно содержать только буквы, пробелы и дефисы';
        }

        if (empty($city)) {
            $errors[] = 'Город обязателен для заполнения';
        }

        if (empty($phone)) {
            $errors[] = 'Телефон обязателен для заполнения';
        } elseif (!preg_match('/^\+7\s\(\d{3}\)\s\d{3}-\d{2}-\d{2}$/', $phone)) {
            $errors[] = 'Некорректный формат телефона (+7 (999) 123-45-67)';
        }

        // Валидация пароля если пользователь пытается его изменить
        if (!empty($newPassword) || !empty($confirmNewPassword) || !empty($currentPassword)) {
            if (empty($currentPassword)) {
                $errors[] = 'Введите текущий пароль для изменения пароля';
            } elseif (!$user->verifyPassword($currentPassword)) {
                $errors[] = 'Текущий пароль введен неверно';
            }

            if (empty($newPassword)) {
                $errors[] = 'Новый пароль обязателен';
            } elseif (!$validator->validatePassword($newPassword)) {
                $errors[] = 'Новый пароль должен содержать минимум 6 символов';
            }

            if (empty($confirmNewPassword)) {
                $errors[] = 'Подтверждение нового пароля обязательно';
            } elseif ($newPassword !== $confirmNewPassword) {
                $errors[] = 'Новый пароль и подтверждение не совпадают';
            }
        }

        if (empty($errors)) {
            try {
                // Обновляем пароль если он был изменен
                if (!empty($newPassword) && !empty($confirmNewPassword) && !empty($currentPassword)) {
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET full_name = ?, city = ?, phone = ?, password_hash = ?, updated_at = datetime('now')
                        WHERE id = ?
                    ");
                    $stmt->execute([$fullName, $city, $phone, $passwordHash, $user->id]);
                    $message = 'Профиль и пароль успешно обновлены';
                } else {
                    // Обновляем только профиль без пароля
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET full_name = ?, city = ?, phone = ?, updated_at = datetime('now')
                        WHERE id = ?
                    ");
                    $stmt->execute([$fullName, $city, $phone, $user->id]);
                    $message = 'Профиль успешно обновлен';
                }

                $messageType = 'success';

                // Обновляем данные для отображения
                $user->full_name = $fullName;
                $user->city = $city;
                $user->phone = $phone;

            } catch (Exception $e) {
                $message = 'Ошибка при обновлении профиля: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = implode(' ', $errors);
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
    <title>Редактирование профиля - Система учета продаж</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-gray-100">
    <!-- Навигация -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">Система учета</h1>
                </div>
                <div class="flex items-center space-x-6">
                    <a href="/user_dashboard.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-blue-600">
                        Cтатистика
                    </a>
                    <a href="/user_profile_edit.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-blue-600">
                        Профиль
                    </a>
                    <a href="/user_logout.php" class="text-gray-500 hover:text-gray-700">
                        Выход
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Уведомления -->
        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-100 text-green-800 border-green-400' : 'bg-red-100 text-red-800 border-red-400' ?> border-l-4">
            <div class="flex items-center">
                <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Информация о враче и форма редактирования -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">
                        Профиль
                    </h1>
                    <p class="text-gray-600">
                        Email: <?= htmlspecialchars($user->email ?? '') ?> | Промокод: <?= htmlspecialchars($promoCode) ?>
                    </p>
                </div>

                <?php if (!$user): ?>
                <div class="text-center py-8 text-red-500">
                    <p>Ошибка: данные пользователя не загружены</p>
                </div>
                <?php else: ?>
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- ФИО -->
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">
                                ФИО <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="full_name" name="full_name" required
                                   value="<?= htmlspecialchars($user->full_name ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Иванов Иван Иванович">
                        </div>

                        <!-- Email (только для чтения) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" value="<?= htmlspecialchars($user->email ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500"
                                   readonly>
                            <p class="text-xs text-gray-500 mt-1">Email нельзя изменить</p>
                        </div>

                        <!-- Город -->
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700 mb-2">
                                Город <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="city" name="city" required
                                   value="<?= htmlspecialchars($user->city ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Москва">
                        </div>

                        <!-- Телефон -->
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                Телефон <span class="text-red-500">*</span>
                            </label>
                            <input type="tel" id="phone" name="phone" required
                                   value="<?= htmlspecialchars($user->phone ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="+7 (999) 123-45-67">
                        </div>
                    </div>

                    <!-- Раздел изменения пароля -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Изменение пароля</h3>
                        <p class="text-sm text-gray-600 mb-4">Оставьте поля пустыми, если не хотите менять пароль</p>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Текущий пароль -->
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">
                                    Текущий пароль
                                </label>
                                <div class="relative">
                                    <input type="password" id="current_password" name="current_password"
                                           class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           placeholder="Введите текущий пароль">
                                    <button type="button" id="toggleCurrentPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                        <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Новый пароль -->
                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                                    Новый пароль
                                </label>
                                <div class="relative">
                                    <input type="password" id="new_password" name="new_password"
                                           class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           placeholder="Минимум 6 символов">
                                    <button type="button" id="toggleNewPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                        <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Подтверждение нового пароля -->
                            <div>
                                <label for="confirm_new_password" class="block text-sm font-medium text-gray-700 mb-2">
                                    Подтвердить новый пароль
                                </label>
                                <div class="relative">
                                    <input type="password" id="confirm_new_password" name="confirm_new_password"
                                           class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           placeholder="Повторите новый пароль">
                                    <button type="button" id="toggleConfirmNewPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                        <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Кнопки действий -->
                    <div class="flex space-x-4 pt-4 border-t border-gray-200">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                            <i class="fas fa-save mr-2"></i>
                            Сохранить изменения
                        </button>

                        <a href="/user_products.php?email=<?= urlencode($user->email) ?>" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 inline-flex items-center">
                            <i class="fas fa-times mr-2"></i>
                            Отмена
                        </a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Валидация формы в реальном времени
        document.addEventListener('DOMContentLoaded', function() {
            const phone = document.getElementById('phone');
            const fullName = document.getElementById('full_name');

            // Форматирование телефона
            phone.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                if (value.startsWith('8')) {
                    value = '7' + value.slice(1);
                }
                if (value.startsWith('7') && value.length > 1) {
                    value = '+7 (' + value.slice(1, 4) + ') ' + value.slice(4, 7) + '-' + value.slice(7, 9) + '-' + value.slice(9, 11);
                }
                this.value = value;
            });

            // Форматирование ФИО
            fullName.addEventListener('input', function() {
                this.value = this.value.replace(/[^а-яёА-ЯЁ\s\-]/gi, '');
            });

            // Функциональность показа/скрытия текущего пароля
            const toggleCurrentPassword = document.getElementById('toggleCurrentPassword');
            const currentPasswordField = document.getElementById('current_password');

            if (toggleCurrentPassword && currentPasswordField) {
                toggleCurrentPassword.addEventListener('click', function() {
                    const type = currentPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
                    currentPasswordField.setAttribute('type', type);

                    const icon = this.querySelector('i');
                    if (type === 'text') {
                        icon.className = 'fas fa-eye-slash text-gray-600';
                    } else {
                        icon.className = 'fas fa-eye text-gray-400 hover:text-gray-600';
                    }
                });
            }

            // Функциональность показа/скрытия нового пароля
            const toggleNewPassword = document.getElementById('toggleNewPassword');
            const newPasswordField = document.getElementById('new_password');

            if (toggleNewPassword && newPasswordField) {
                toggleNewPassword.addEventListener('click', function() {
                    const type = newPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
                    newPasswordField.setAttribute('type', type);

                    const icon = this.querySelector('i');
                    if (type === 'text') {
                        icon.className = 'fas fa-eye-slash text-gray-600';
                    } else {
                        icon.className = 'fas fa-eye text-gray-400 hover:text-gray-600';
                    }
                });
            }

            // Функциональность показа/скрытия подтверждения нового пароля
            const toggleConfirmNewPassword = document.getElementById('toggleConfirmNewPassword');
            const confirmNewPasswordField = document.getElementById('confirm_new_password');

            if (toggleConfirmNewPassword && confirmNewPasswordField) {
                toggleConfirmNewPassword.addEventListener('click', function() {
                    const type = confirmNewPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
                    confirmNewPasswordField.setAttribute('type', type);

                    const icon = this.querySelector('i');
                    if (type === 'text') {
                        icon.className = 'fas fa-eye-slash text-gray-600';
                    } else {
                        icon.className = 'fas fa-eye text-gray-400 hover:text-gray-600';
                    }
                });
            }
        });
    </script>
</body>
</html>
