<?php

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
    <title><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Система учета продаж') ?> - Авторизация Пользователь</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: white;
        }
    </style>
</head>
<body class="min-h-screen gradient-bg">
    <div class="flex items-center justify-center min-h-screen">
        <div class="bg-white rounded-2xl p-8 shadow-2xl max-w-md w-full mx-4">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    <?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Система учета продаж') ?>
                </h1>
                <p class="text-gray-600 text-sm">Авторизация пользователя</p>
            </div>

            <form id="user-login-form" class="space-y-6">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" required 
                           class="email-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Пароль</label>
                    <div class="relative">
                        <input type="password" name="password" id="password" required
                               class="password-input w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Сообщение об ошибке -->
                <div id="error-message" class="hidden p-4 rounded-lg bg-red-100 text-red-800 border-l-4 border-red-500">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                        <span id="error-text">Неверный логин или пароль</span>
                    </div>
                </div>
                
                <button type="submit" class="w-full bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 mb-8">
                    Войти
                </button>
            </form>

            <div class="text-center space-y-2 pt-6 border-t border-gray-200">
                <p class="text-sm text-gray-600">
                    Нет аккаунта? 
                    <a href="/user_register.php" class="font-medium text-blue-600 hover:text-blue-500">
                        Зарегистрироваться
                    </a>
                </p>
                <p class="text-sm">
                    <a href="/recovery.php" class="font-medium text-blue-600 hover:text-blue-500">
                        Восстановить пароль
                    </a>
                </p>
            </div>

            <div class="mt-8 text-center">
                <a href="/" class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Вернуться на главную
                </a>
            </div>

        </div>
    </div>

    <script>
        // Определяем базовый путь
        const getBasePath = () => {
            const path = window.location.pathname;
            const dir = path.substring(0, path.lastIndexOf('/'));
            return dir || '';
        };

        const basePath = getBasePath();
        
        // Функция для отображения ошибки
        function showError(errorMessage = 'Неверный логин или пароль') {
            const errorDiv = document.getElementById('error-message');
            const errorText = document.getElementById('error-text');
            const emailInput = document.querySelector('.email-input');
            const passwordInput = document.querySelector('.password-input');
            
            // Показываем сообщение об ошибке
            errorText.textContent = errorMessage;
            errorDiv.classList.remove('hidden');
            
            // Подчеркиваем красным поля ввода
            emailInput.classList.add('border-red-500', 'border-2');
            emailInput.classList.remove('border-gray-300');
            passwordInput.classList.add('border-red-500', 'border-2');
            passwordInput.classList.remove('border-gray-300');
        }
        
        // Функция для скрытия ошибки
        function hideError() {
            const errorDiv = document.getElementById('error-message');
            const emailInput = document.querySelector('.email-input');
            const passwordInput = document.querySelector('.password-input');
            
            errorDiv.classList.add('hidden');
            emailInput.classList.remove('border-red-500', 'border-2');
            emailInput.classList.add('border-gray-300');
            passwordInput.classList.remove('border-red-500', 'border-2');
            passwordInput.classList.add('border-gray-300');
        }
        
        document.getElementById('user-login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Скрываем ошибку при новой попытке входа
            hideError();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);

            // Показываем индикатор загрузки
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Вход...';

            try{
                console.log('Шаг 1: Авторизация через API...');
                console.log('Данные формы:', data);
                console.log('JSON данные:', JSON.stringify(data));
                
                // Используем правильный API endpoint
                const apiUrl = basePath + '/api/user_login.php';
                console.log('API URL:', apiUrl);
                
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                console.log('Статус ответа:', response.status);
                console.log('Заголовки ответа:', Object.fromEntries(response.headers.entries()));
                
                // Проверяем тип контента
                const contentType = response.headers.get('content-type');
                console.log('Content-Type ответа:', contentType);

                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Ответ не JSON:', text.substring(0, 500));
                    throw new Error('Сервер вернул неверный формат ответа');
                }

                let result;
                try {
                    result = await response.json();
                    console.log('Результат API:', result);
                } catch (jsonError) {
                    console.error('Ошибка парсинга JSON:', jsonError);
                    const text = await response.text();
                    console.error('Текст ответа:', text);
                    throw new Error('Сервер вернул неверный формат ответа');
                }
                
                if (result && result.success) {
                    console.log('Шаг 2: Создание PHP сессии...');

                    // Отправляем POST запрос для создания PHP сессии
                    const sessionResponse = await fetch(basePath + '/user_create_session.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            email: data.email,
                            token: result.data.token,
                            user: result.data.user
                        })
                    });

                    const sessionResult = await sessionResponse.json();
                    console.log('Результат создания сессии:', sessionResult);

                    if (sessionResult && sessionResult.success) {
                        console.log('Успех! PHP сессия создана.');
                        console.log('Session ID:', sessionResult.session_id);
                        console.log('Session Data:', sessionResult.session_data);
                        console.log('Перенаправление через 500ms...');

                        // Небольшая задержка для сохранения сессии
                        setTimeout(() => {
                            window.location.href = basePath + '/user_dashboard.php';
                        }, 500);
                    } else {
                        throw new Error('Ошибка создания сессии: ' + (sessionResult.error || 'неизвестная ошибка'));
                    }
                } else {
                    const errorMsg = result?.error?.message || result?.message || 'Неверный логин или пароль';
                    throw new Error(errorMsg);
                }
            } catch (error) {
                console.error('Ошибка авторизации:', error);
                showError('Неверный логин или пароль');
                
                // Восстанавливаем кнопку
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
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
    </script>
</body>
</html>
