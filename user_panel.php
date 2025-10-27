<?php

// Проверка авторизации пользователя
session_start();
$userEmail = $_SESSION['user_email'] ?? '';
$userLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

if (!$userLoggedIn) {
    header('Location: /user.php');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use ReproCRM\Config\Config;

// Загружаем конфигурацию
Config::load();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(Config::getAppName()) ?> - Панель </title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .hover-scale {
            transition: transform 0.2s ease-in-out;
        }
        .hover-scale:hover {
            transform: scale(1.05);
        }
        .tab-active {
            background: rgba(255, 255, 255, 0.2);
            border-bottom: 3px solid #667eea;
        }
    </style>
</head>
<body class="min-h-screen gradient-bg">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="w-full max-w-lg">
            <div class="glass-effect rounded-2xl p-8 shadow-2xl">
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-white mb-2">
                        <?= htmlspecialchars(Config::getAppName()) ?>
                    </h1>
                </div>

                <!-- Табы для переключения между входом и регистрацией -->
                <div class="flex mb-6 bg-white bg-opacity-20 rounded-lg p-1">
                    <button id="login-tab" class="flex-1 py-2 px-4 rounded-md text-white font-medium tab-active">
                        <i class="fas fa-sign-in-alt mr-2"></i>Вход
                    </button>
                    <button id="register-tab" class="flex-1 py-2 px-4 rounded-md text-white font-medium">
                        <i class="fas fa-user-plus mr-2"></i>Регистрация
                    </button>
                </div>

                <!-- Форма входа -->
                <div id="login-form" class="login-form">
                    <form id="doctor-login-form">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-200 mb-2">Email</label>
                            <input type="email" name="email" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white bg-opacity-90">
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-200 mb-2">Пароль</label>
                            <input type="password" name="password" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white bg-opacity-90">
                        </div>
                        <button type="submit" class="w-full bg-green-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-green-700 hover-scale">
                            <i class="fas fa-sign-in-alt mr-2"></i>Войти в систему
                        </button>
                    </form>
                </div>

                <!-- Форма регистрации -->
                <div id="register-form" class="register-form hidden">
                    <form method="POST" action="/doctor_products.php">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-200 mb-2">ФИО <span class="text-red-400">*</span></label>
                            <input type="text" name="full_name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white bg-opacity-90"
                                   placeholder="Иванов Иван Иванович">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-200 mb-2">Email <span class="text-red-400">*</span></label>
                            <input type="email" name="email" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white bg-opacity-90"
                                   placeholder="user@example.com">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-200 mb-2">Пароль <span class="text-red-400">*</span></label>
                            <input type="password" name="password" id="password" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white bg-opacity-90"
                                   placeholder="Минимум 6 символов">
                            <div class="mt-2 text-xs text-gray-300">
                                <p class="mb-1">Требования к паролю:</p>
                                <ul class="list-disc list-inside space-y-1">
                                    <li id="req-length" class="text-gray-400">Минимум 6 символов</li>
                                    <li id="req-characters" class="text-gray-400">Только английские буквы, цифры и символы (!@#$%^&*()_+-=[]{}|;:,.<>?)</li>
                                </ul>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-200 mb-2">Подтверждение пароля <span class="text-red-400">*</span></label>
                            <input type="password" name="confirm_password" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white bg-opacity-90"
                                   placeholder="Повторите пароль">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-200 mb-2">Телефон <span class="text-red-400">*</span></label>
                            <input type="tel" name="phone" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white bg-opacity-90"
                                   placeholder="+7 (999) 123-45-67">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-200 mb-2">Город <span class="text-red-400">*</span></label>
                            <input type="text" name="city" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white bg-opacity-90"
                                   placeholder="Москва">
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-200 mb-2">Промокод <span class="text-red-400">*</span></label>
                            <input type="text" name="promo_code" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white bg-opacity-90"
                                   placeholder="Введите полученный промокод">
                        </div>
                        <button type="submit" class="w-full bg-purple-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-purple-700 hover-scale">
                            <i class="fas fa-user-plus mr-2"></i>Зарегистрироваться
                        </button>
                    </form>
                </div>

                <div class="text-center mt-6">
                    <a href="/" class="text-gray-300 hover:text-white text-sm transition-colors">
                        <i class="fas fa-arrow-left mr-1"></i>Вернуться на главную
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Переключение между табами
        document.getElementById('login-tab').addEventListener('click', function() {
            document.getElementById('login-tab').classList.add('tab-active');
            document.getElementById('register-tab').classList.remove('tab-active');
            document.getElementById('login-form').classList.remove('hidden');
            document.getElementById('register-form').classList.add('hidden');
        });

        document.getElementById('register-tab').addEventListener('click', function() {
            document.getElementById('register-tab').classList.add('tab-active');
            document.getElementById('login-tab').classList.remove('tab-active');
            document.getElementById('register-form').classList.remove('hidden');
            document.getElementById('login-form').classList.add('hidden');
        });

        // Обработка формы входа
        document.getElementById('doctor-login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);

            try {
                const response = await fetch('/api/simple.php/doctor-login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    localStorage.setItem('doctor_token', result.data.token);
                    window.location.href = '/user_dashboard.php?email=' + encodeURIComponent(data.email);
                } else {
                    alert('Ошибка входа: ' + result.error.message);
                }
            } catch (error) {
                alert('Ошибка: ' + error.message);
            }
        });

        // Валидация формы в реальном времени для регистрации
        document.addEventListener('DOMContentLoaded', function() {
            const registerForm = document.querySelector('#register-form form');
            if (registerForm) {
                const password = registerForm.querySelector('input[name="password"]');
                const confirmPassword = registerForm.querySelector('input[name="confirm_password"]');

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
                        password.setCustomValidity('Пароль не соответствует требованиям');
                    } else {
                        password.setCustomValidity('');
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
                            element.className = 'text-green-400';
                            element.innerHTML = element.innerHTML.replace(/^/, '✓ ');
                        } else {
                            element.className = 'text-gray-400';
                            element.innerHTML = element.innerHTML.replace(/^✓ /, '');
                        }
                    }
                }

                function validatePasswords() {
                    if (password.value && confirmPassword.value) {
                        if (password.value !== confirmPassword.value) {
                            confirmPassword.setCustomValidity('Пароли не совпадают');
                        } else {
                            confirmPassword.setCustomValidity('');
                        }
                    }
                }

                password.addEventListener('input', validatePassword);
                confirmPassword.addEventListener('input', validatePasswords);

                // Форматирование телефона
                const phone = registerForm.querySelector('input[name="phone"]');
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
                const fullName = registerForm.querySelector('input[name="full_name"]');
                fullName.addEventListener('input', function() {
                    this.value = this.value.replace(/[^а-яёА-ЯЁ\s\-]/gi, '');
                });
            }
        });
    </script>
</body>
</html>
