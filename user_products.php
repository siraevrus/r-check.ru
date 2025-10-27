<?php

// Проверка авторизации пользователя через PHP сессию
session_start();

$userEmail = $_SESSION['user_email'] ?? '';
$userLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

// Для обхода авторизации (только для тестирования)
if (isset($_GET['bypass_auth']) && !empty($_GET['email'])) {
    $userEmail = $_GET['email'];
    $userLoggedIn = true;
}

if (!$userLoggedIn || empty($userEmail)) {
    header('Location: /user.php');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
use ReproCRM\Config\Database;
use ReproCRM\Security\JWT;

$_ENV['DB_TYPE'] = 'sqlite';
$db = Database::getInstance();
$pdo = $db;

// Получаем информацию о враче
try {
    $stmt = $pdo->prepare("SELECT id, full_name, email, promo_code_id FROM users WHERE email = ?");
    $stmt->execute([$userEmail]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: /user_panel.php');
        exit;
    }

    // Получаем промокод пользователя
    if ($user['promo_code_id']) {
        $stmt = $pdo->prepare("SELECT code FROM promo_codes WHERE id = ?");
        $stmt->execute([$user['promo_code_id']]);
        $promoCode = $stmt->fetch()['code'];
    } else {
        $promoCode = 'Не назначен';
    }

    // Получаем статистику продаж врача
    $stmt = $pdo->prepare("
        SELECT
            COUNT(s.id) as total_sales,
            COALESCE(SUM(s.quantity), 0) as total_quantity
        FROM sales s
        JOIN promo_codes pc ON s.promo_code_id = pc.id
        WHERE pc.id = ?
    ");
    $stmt->execute([$user['promo_code_id']]);
    $stats = $stmt->fetch();

} catch (Exception $e) {
    $error = 'Ошибка при загрузке данных: ' . $e->getMessage();
}

// Получаем доступные продукты для продажи
try {
    $stmt = $pdo->query("
        SELECT DISTINCT product_name, COUNT(*) as sales_count, SUM(quantity) as total_quantity
        FROM sales
        GROUP BY product_name
        ORDER BY total_quantity DESC
    ");
    $products = $stmt->fetchAll();
} catch (Exception $e) {
    $products = [];
    $error = 'Ошибка при загрузке продуктов: ' . $e->getMessage();
}

$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? '';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Продукты для продажи - Система учета продаж</title>
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

        <!-- Информация о враче -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center mb-6">
                    <div class="flex-shrink-0">
                        <div class="w-16 h-16 bg-blue-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-user-md text-white text-2xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h1 class="text-2xl font-bold text-gray-900">
                            <?= htmlspecialchars($user['full_name']) ?>
                        </h1>
                        <p class="text-gray-600">
                            Email: <?= htmlspecialchars($user['email']) ?> | Промокод: <?= htmlspecialchars($promoCode) ?>
                        </p>
                        <p class="text-sm text-gray-500 mt-1">
                            Статистика: Продаж: <?= $stats['total_sales'] ?> / Товаров: <?= $stats['total_quantity'] ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Таблица продуктов -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg leading-6 font-medium text-gray-900">
                        Доступные продукты для продажи
                    </h2>
                </div>

                <?php if (empty($products)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-box-open text-4xl mb-4"></i>
                    <p>Продукты не найдены</p>
                    <p class="text-sm mt-2">В системе пока нет данных о продуктах для продажи</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Наименование продукта</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Количество продаж</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Общее количество</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($products as $product): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($product['product_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($product['sales_count']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                    <?= htmlspecialchars($product['total_quantity']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Добавляем токен в заголовки для API запросов
        document.addEventListener('DOMContentLoaded', function() {
            const token = localStorage.getItem('doctor_token');
            if (token) {
                // Добавляем токен в заголовки для всех AJAX запросов
                const originalFetch = window.fetch;
                window.fetch = function(...args) {
                    const [url, options = {}] = args;
                    if (url.startsWith('/api/') || url.includes('/api_simple.php')) {
                        options.headers = {
                            ...options.headers,
                            'Authorization': 'Bearer ' + token
                        };
                    }
                    return originalFetch(...args);
                };
            }
        });
    </script>
</body>
</html>
