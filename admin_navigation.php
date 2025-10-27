<?php
// Определяем текущую страницу для активного состояния меню
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Функция для определения активного класса
function getActiveClass($page) {
    global $currentPage;
    return $currentPage === $page ? 'bg-blue-100 text-blue-800' : 'text-gray-600 hover:text-blue-600';
}
?>

<nav class="bg-white shadow-sm border-b">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <h1 class="text-xl font-semibold text-gray-900">Система учета</h1>
            </div>
            <div class="flex items-center space-x-6">
                <a href="/promo_codes.php" class="px-3 py-2 rounded-md text-sm font-medium <?= getActiveClass('promo_codes') ?>">
                    Промокоды
                </a>
                <a href="/users_report.php" class="px-3 py-2 rounded-md text-sm font-medium <?= getActiveClass('users_report') ?>">
                    Отчет по пользователям
                </a>
                <a href="/file_upload.php" class="px-3 py-2 rounded-md text-sm font-medium <?= getActiveClass('file_upload') ?>">
                    Загрузка файлов
                </a>
                <a href="/employees.php" class="px-3 py-2 rounded-md text-sm font-medium <?= getActiveClass('employees') ?>">
                    Администраторы
                </a>
                <a href="/admin_logout.php" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-sign-out-alt mr-1"></i>Выход
                </a>
            </div>
        </div>
    </div>
</nav>
