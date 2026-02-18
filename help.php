<?php

// Проверка авторизации администратора
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Справка - Система РЕПРО</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-gray-100">
<?php include 'admin_navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Быстрый старт -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-rocket text-blue-600 mr-3"></i>Быстрый старт
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Данные для входа:</h3>
                        <div class="space-y-2">
                            <div class="p-3 bg-blue-50 rounded-lg">
                                <p class="font-medium text-blue-900">Администратор:</p>
                                <p class="text-blue-700">Email: admin@admin.com</p>
                                <p class="text-blue-700">Пароль: admin123 (сброшены)</p>
                            </div>
                            <div class="p-3 bg-green-50 rounded-lg">
                                <p class="font-medium text-green-900">Врач:</p>
                                <p class="text-green-700">Email: doctor@test.ru</p>
                                <p class="text-green-700">Пароль: admin123</p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Основные функции:</h3>
                        <ul class="space-y-2">
                            <li class="flex items-center">
                                <i class="fas fa-ticket-alt text-blue-600 mr-2"></i>
                                <a href="/promo_codes.php" class="text-blue-600 hover:underline">Управление промокодами</a>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-user-md text-green-600 mr-2"></i>
                                <a href="/users_report.php" class="text-green-600 hover:underline">Отчет по пользователям</a>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-upload text-purple-600 mr-2"></i>
                                <a href="/file_upload.php" class="text-purple-600 hover:underline">Загрузка файлов</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Частые вопросы -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-question-circle text-green-600 mr-3"></i>Частые вопросы
                </h2>
                
                <div class="space-y-4">
                    <div class="border-l-4 border-blue-500 pl-4">
                        <h3 class="font-semibold text-gray-900">Как добавить промокод?</h3>
                        <p class="text-gray-600 mt-1">Перейдите в раздел "Промокоды", введите 7-символьный код и нажмите "Добавить".</p>
                    </div>
                    
                    <div class="border-l-4 border-green-500 pl-4">
                        <h3 class="font-semibold text-gray-900">Как загрузить файл с продажами?</h3>
                        <p class="text-gray-600 mt-1">Используйте раздел "Загрузка файлов". Поддерживаются форматы CSV, XLS, XLSX.</p>
                    </div>
                    
                    <div class="border-l-4 border-purple-500 pl-4">
                        <h3 class="font-semibold text-gray-900">Как экспортировать данные?</h3>
                        <p class="text-gray-600 mt-1">На страницах отчетов нажмите кнопку "Экспорт в Excel" для скачивания файла.</p>
                    </div>
                    
                    <div class="border-l-4 border-orange-500 pl-4">
                        <h3 class="font-semibold text-gray-900">Не могу удалить промокод</h3>
                        <p class="text-gray-600 mt-1">Промокод нельзя удалить, если к нему привязан врач или есть записи о продажах.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Формат файлов -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-file-upload text-purple-600 mr-3"></i>Формат файлов для загрузки
                </h2>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Обязательные колонки:</h3>
                        <ul class="space-y-2">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-600 mr-2"></i>
                                <span class="font-medium">Промокод</span> - 7 символов
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-600 mr-2"></i>
                                <span class="font-medium">Продукт/Товар</span> - название
                            </li>
                        </ul>
                        
                        <h3 class="text-lg font-semibold text-gray-900 mb-3 mt-4">Дополнительные колонки:</h3>
                        <ul class="space-y-2">
                            <li class="flex items-center">
                                <i class="fas fa-plus text-blue-600 mr-2"></i>
                                <span>Количество</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-plus text-blue-600 mr-2"></i>
                                <span>Дата продажи</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-plus text-blue-600 mr-2"></i>
                                <span>Email врача</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-plus text-blue-600 mr-2"></i>
                                <span>Имя врача</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-plus text-blue-600 mr-2"></i>
                                <span>Город</span>
                            </li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Пример CSV файла:</h3>
                        <div class="bg-gray-100 p-4 rounded-lg overflow-x-auto">
                            <pre class="text-sm text-gray-700">Промокод,Продукт,Количество,Дата,Email врача,Имя врача,Город
TEST001,Продукт А,5,2024-10-01,user@test.ru,Иванов И.И.,Москва
TEST002,Продукт Б,3,2024-10-02,user@test.ru,Петров П.П.,СПб
TEST003,Продукт В,2,2024-10-03,user@test.ru,Сидоров С.С.,Казань</pre>
                        </div>
                        
                        <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>Совет:</strong> Первая строка должна содержать заголовки. Система автоматически определит колонки по названиям.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</body>
</html>
