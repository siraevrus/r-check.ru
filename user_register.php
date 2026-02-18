<?php

require_once __DIR__ . '/vendor/autoload.php';
use ReproCRM\Config\Database;
use ReproCRM\Utils\Validator;
use ReproCRM\Utils\PromoCodeNormalizer;
use ReproCRM\Models\PromoCode;

$_ENV['DB_TYPE'] = 'sqlite';
$db = Database::getInstance();
$pdo = $db;
$validator = new Validator();

$message = '';
$messageType = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $promoCode = trim($_POST['promo_code'] ?? '');
    $agreement = isset($_POST['agreement']) && $_POST['agreement'] === 'on';

    // Валидация данных
    if (empty($fullName)) {
        $errors[] = 'ФИО обязательно для заполнения';
    } elseif (!$validator->validateFullName($fullName)) {
        $errors[] = 'ФИО должно содержать только буквы, пробелы и дефисы';
    }

    if (empty($email)) {
        $errors[] = 'Email обязателен для заполнения';
    } elseif (!$validator->validateEmail($email)) {
        $errors[] = 'Некорректный формат email';
    } else {
        // Проверяем, не зарегистрирован ли уже пользователь с таким email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Пользователь с таким email уже зарегистрирован';
        }
    }

    if (empty($password)) {
        $errors[] = 'Пароль обязателен для заполнения';
    } elseif (!$validator->validatePassword($password)) {
        $errors[] = 'Пароль должен содержать минимум 6 символов';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Пароли не совпадают';
    }

    if (empty($phone)) {
        $errors[] = 'Телефон обязателен для заполнения';
    } elseif (!preg_match('/^\+7\s\(\d{3}\)\s\d{3}-\d{2}-\d{2}$/', $phone)) {
        $errors[] = 'Некорректный формат телефона (+7 (999) 123-45-67)';
    }

    if (empty($city)) {
        $errors[] = 'Город обязателен для заполнения';
    }

    if (!$agreement) {
        $errors[] = 'Необходимо принять условия пользовательского соглашения и политики конфиденциальности';
    }

    if (empty($promoCode)) {
        $errors[] = 'Промокод обязателен для заполнения';
    } else {
        $normalized = PromoCodeNormalizer::normalize($promoCode);
        if ($normalized !== '') {
            $promoCode = $normalized;
        }
        $promoCodeObj = PromoCode::findByCode($promoCode);
        if (!$promoCodeObj) {
            $digits = PromoCodeNormalizer::extractLastThreeDigits($promoCode);
            if ($digits !== null) {
                $hasHyphen = PromoCodeNormalizer::hasHyphenInNormalized($promoCode);
                $promoCodeObj = PromoCode::findByLastThreeDigits($digits, $hasHyphen);
            }
        }
        $promoCodeData = $promoCodeObj ? ['id' => $promoCodeObj->id, 'status' => $promoCodeObj->status] : null;

        if (!$promoCodeData) {
            $errors[] = 'Промокод не найден';
        } elseif ($promoCodeData['status'] !== 'unregistered') {
            $errors[] = 'Промокод уже используется или неактивен';
        }
    }

    // Если нет ошибок, регистрируем пользователя
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Хешируем пароль
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Создаем пользователя
            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, email, password_hash, phone, city, promo_code_id, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'active', datetime('now'), datetime('now'))
            ");
            $stmt->execute([
                $fullName,
                $email,
                $hashedPassword,
                $phone,
                $city,
                $promoCodeData['id']
            ]);

            $doctorId = $pdo->lastInsertId();

            // Обновляем статус промокода
            $stmt = $pdo->prepare("UPDATE promo_codes SET status = 'registered', updated_at = datetime('now') WHERE id = ?");
            $stmt->execute([$promoCodeData['id']]);

            $pdo->commit();

            $message = 'Регистрация успешно завершена! Теперь вы можете войти в систему.';
            $messageType = 'success';

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Ошибка при регистрации: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = implode(' ', $errors);
        $messageType = 'error';
    }
}

// Убираем сброс сообщений из GET параметров, чтобы не конфликтовали с POST
if (empty($message)) {
    $message = $_GET['message'] ?? '';
    $messageType = $_GET['type'] ?? '';
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - Система учета продаж</title>
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
            background: #10b981;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }
    </style>
</head>
<body class="min-h-screen gradient-bg">
    <div class="flex items-center justify-center min-h-screen">
        <div class="bg-white rounded-2xl p-8 shadow-2xl max-w-md w-full mx-4">
            <div class="text-center">
                <h2 class="text-3xl font-extrabold text-gray-900 mb-2">
                    Регистрация 
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Заполните форму для регистрации в системе
                </p>
            </div>

            <?php if ($message): ?>
            <div class="rounded-lg p-4 <?= $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
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

            <form class="mt-8 space-y-6" method="POST" novalidate>
                <div class="space-y-4">
                    <!-- ФИО -->
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">
                            ФИО <span class="text-red-500">*</span>
                        </label>
                        <input id="full_name" name="full_name" type="text" required
                               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                               class="form-input appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Иванов Иван Иванович">
                        <p class="error-message text-red-500 text-sm mt-1 hidden">Пожалуйста, заполните это поле</p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input id="email" name="email" type="email" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               class="form-input appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="user@example.com">
                        <p class="error-message text-red-500 text-sm mt-1 hidden">Пожалуйста, заполните это поле</p>
                    </div>

                    <!-- Пароль -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Пароль <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input id="password" name="password" type="password" required
                                   class="form-input appearance-none relative block w-full px-3 py-2 pr-10 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                                   placeholder="Минимум 6 символов">
                            <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                            </button>
                        </div>
                        <p class="error-message text-red-500 text-sm mt-1 hidden">Пожалуйста, заполните это поле</p>
                        <div class="mt-2 text-xs text-gray-500">
                            <p class="mb-1 font-medium">Требования к паролю:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li id="req-length" class="text-gray-400">Минимум 6 символов</li>
                                <li id="req-characters" class="text-gray-400">Только английские буквы, цифры и символы (!@#$%^&*()_+-=[]{}|;:,.<>?)</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Подтверждение пароля -->
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Подтверждение пароля <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input id="confirm_password" name="confirm_password" type="password" required
                                   class="form-input appearance-none relative block w-full px-3 py-2 pr-10 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                                   placeholder="Повторите пароль">
                            <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                            </button>
                        </div>
                        <p class="error-message text-red-500 text-sm mt-1 hidden">Пожалуйста, заполните это поле</p>
                    </div>

                    <!-- Телефон -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Телефон <span class="text-red-500">*</span>
                        </label>
                        <input id="phone" name="phone" type="tel" required
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                               class="form-input appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="+7 (999) 123-45-67">
                        <p class="error-message text-red-500 text-sm mt-1 hidden">Пожалуйста, заполните это поле</p>
                    </div>

                    <!-- Город -->
                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-700 mb-2">
                            Город <span class="text-red-500">*</span>
                        </label>
                        <input id="city" name="city" type="text" required
                               value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                               class="form-input appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Москва">
                        <p class="error-message text-red-500 text-sm mt-1 hidden">Пожалуйста, заполните это поле</p>
                    </div>

                    <!-- Промокод -->
                    <div>
                        <label for="promo_code" class="block text-sm font-medium text-gray-700 mb-2">
                            Промокод <span class="text-red-500">*</span>
                        </label>
                        <input id="promo_code" name="promo_code" type="text" required
                               value="<?= htmlspecialchars($_POST['promo_code'] ?? '') ?>"
                               class="form-input appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Введите предоставленный промокод">
                        <p class="error-message text-red-500 text-sm mt-1 hidden">Пожалуйста, заполните это поле</p>
                        <p class="text-xs text-gray-500 mt-1">Промокод предоставляется администратором системы</p>
                    </div>
                </div>

                <!-- Чек-бокс соглашения -->
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input id="agreement" name="agreement" type="checkbox" required
                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="agreement" class="text-gray-700">
                            Нажимая «Зарегистрироваться», вы принимаете условия 
                            <a href="/terms.html" class="text-blue-600 hover:text-blue-500">пользовательского соглашения</a> 
                            и 
                            <a href="/privacy.html" class="text-blue-600 hover:text-blue-500">политики конфиденциальности</a>
                        </label>
                    </div>
                </div>

                <div>
                    <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white btn-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-user-plus text-blue-500 group-hover:text-blue-400"></i>
                        </span>
                        Зарегистрироваться
                    </button>
                </div>

                <div class="text-center">
                    <p class="text-sm text-gray-600">
                        Уже есть аккаунт?
                        <a href="/user.php" class="font-medium text-blue-600 hover:text-blue-500">
                            Войти в систему
                        </a>
                    </p>
                </div>
            </form>

            <div class="text-center">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700">
                    <i class="fas fa-arrow-left mr-1"></i>Вернуться на главную
                </a>
            </div>
        </div>
    </div>

    <script>
        // Валидация формы в реальном времени
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const email = document.getElementById('email');
            const phone = document.getElementById('phone');
            const agreement = document.getElementById('agreement');
            const submitBtn = document.querySelector('button[type="submit"]');
            
            // Функция показа ошибки для поля
            function showFieldError(field) {
                const errorMsg = field.parentElement.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.classList.remove('hidden');
                    field.classList.add('border-red-500', 'border-2');
                    field.classList.remove('border-gray-300');
                }
            }
            
            // Функция скрытия ошибки для поля
            function hideFieldError(field) {
                const errorMsg = field.parentElement.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.classList.add('hidden');
                    field.classList.remove('border-red-500', 'border-2');
                    field.classList.add('border-gray-300');
                }
            }
            
            // Функция проверки согласия
            function checkAgreement() {
                if (!agreement.checked) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }

            // Инициализация состояния кнопки
            checkAgreement();

            // Обработчик изменения чек-бокса
            agreement.addEventListener('change', checkAgreement);

            // Функция валидации пароля
            function validatePassword() {
                const value = password.value;
                const requirements = {
                    length: value.length >= 6,
                    characters: /^[a-zA-Z0-9!@#$%^&*()_+\-=\[\]{}|;:,.<>?]+$/.test(value)
                };

                // Обновляем визуальные индикаторы
                updateRequirementIndicator('req-length', requirements.length);
                updateRequirementIndicator('req-characters', requirements.characters);

                // Проверяем, выполнены ли все требования
                const allRequirementsMet = Object.values(requirements).every(req => req);
                
                if (value && !allRequirementsMet) {
                    showFieldError(password);
                } else if (!value) {
                    showFieldError(password);
                } else {
                    hideFieldError(password);
                }

                // Если пароль изменился, перепроверяем подтверждение
                if (confirmPassword.value) {
                    validatePasswords();
                }
            }

            function updateRequirementIndicator(elementId, isValid) {
                const element = document.getElementById(elementId);
                if (element) {
                    if (isValid) {
                        element.className = 'text-green-500';
                        element.innerHTML = element.innerHTML.replace(/^/, '✓ ');
                    } else {
                        element.className = 'text-gray-400';
                        element.innerHTML = element.innerHTML.replace(/^✓ /, '');
                    }
                }
            }

            // Валидация паролей
            function validatePasswords() {
                if (password.value && confirmPassword.value) {
                    if (password.value !== confirmPassword.value) {
                        showFieldError(confirmPassword);
                    } else {
                        hideFieldError(confirmPassword);
                    }
                } else if (!confirmPassword.value) {
                    showFieldError(confirmPassword);
                }
            }

            password.addEventListener('input', validatePassword);
            password.addEventListener('focus', hideFieldError);
            confirmPassword.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('focus', function() { hideFieldError(confirmPassword); });

            // Валидация email
            email.addEventListener('input', function() {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (this.value && !emailRegex.test(this.value)) {
                    showFieldError(this);
                } else if (!this.value) {
                    showFieldError(this);
                } else {
                    hideFieldError(this);
                }
            });
            email.addEventListener('focus', function() { hideFieldError(this); });

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
            phone.addEventListener('focus', function() { hideFieldError(this); });

            // Форматирование ФИО
            const fullName = document.getElementById('full_name');
            fullName.addEventListener('input', function() {
                this.value = this.value.replace(/[^а-яёА-ЯЁ\s\-]/gi, '');
            });
            fullName.addEventListener('focus', function() { hideFieldError(this); });
            
            // Городу
            const city = document.getElementById('city');
            city.addEventListener('focus', function() { hideFieldError(this); });
            
            // Промокоду
            const promoCode = document.getElementById('promo_code');
            promoCode.addEventListener('focus', function() { hideFieldError(this); });
            
            // Обработчик отправки формы с валидацией
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const requiredFields = form.querySelectorAll('[required]');
                let isFormValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        showFieldError(field);
                        isFormValid = false;
                    } else {
                        hideFieldError(field);
                    }
                });
                
                // Проверяем согласие
                if (!agreement.checked) {
                    isFormValid = false;
                }
                
                if (isFormValid) {
                    form.submit();
                }
            });

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

            // Функциональность показа/скрытия подтверждения пароля
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const confirmPasswordField = document.getElementById('confirm_password');

            if (toggleConfirmPassword && confirmPasswordField) {
                toggleConfirmPassword.addEventListener('click', function() {
                    const type = confirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
                    confirmPasswordField.setAttribute('type', type);

                    // Меняем иконку
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
