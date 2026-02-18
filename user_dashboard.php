<?php

// Проверка авторизации пользователя
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userEmail = $_SESSION['user_email'] ?? '';
$userLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

// Для обхода авторизации (только для тестирования)
if (isset($_GET['bypass_auth']) && !empty($_GET['email'])) {
    $userEmail = $_GET['email'];
    $userLoggedIn = true;
}

// Поддержка прямого доступа по email (для тестирования)
if (isset($_GET['email']) && !empty($_GET['email']) && !$userLoggedIn) {
    $userEmail = $_GET['email'];
    $userLoggedIn = true;
}

if (!$userLoggedIn || empty($userEmail)) {
    header('Location: /user.php');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use ReproCRM\Config\Config;
Config::load();
$_ENV['DB_TYPE'] = 'sqlite';

// Подключаемся к базе данных
$dbPath = __DIR__ . '/database/reprocrm.db';
$pdo = new PDO("sqlite:" . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Получаем информацию о пользователе
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.city,
        u.created_at,
        pc.code as promo_code,
        pc.total_sales
    FROM users u
    LEFT JOIN promo_codes pc ON u.promo_code_id = pc.id
    WHERE u.email = ?
");
$stmt->execute([$userEmail]);
$user = $stmt->fetch();

if (!$user) {
    // Если пользователь не найден, перенаправляем на страницу входа
    header('Location: /user.php');
    exit;
}



// Получаем статистику продаж пользователя
$stmt = $pdo->prepare("
    SELECT 
        COUNT(s.id) as total_sales,
        COALESCE(SUM(s.quantity), 0) as total_quantity,
        COUNT(DISTINCT s.product_name) as unique_products
    FROM sales s
    JOIN promo_codes pc ON s.promo_code_id = pc.id
    WHERE pc.code = ?
");
$stmt->execute([$user['promo_code']]);
$salesStats = $stmt->fetch();

// Получаем данные о списаниях пользователя
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(d.amount), 0) as total_deducted
    FROM deductions d
    WHERE d.user_id = ?
");
$stmt->execute([$user['id']]);
$deductionStats = $stmt->fetch();

// Вычисляем остаток (общие продажи минус все списания)
$totalSales = isset($user['total_sales']) ? (int)$user['total_sales'] : 0;
$totalDeducted = isset($deductionStats['total_deducted']) ? (int)$deductionStats['total_deducted'] : 0;
$remainingBalance = $totalSales - $totalDeducted;

// Получаем продажи пользователя, сгруппированные по продуктам
$stmt = $pdo->prepare("
    SELECT
        s.product_name,
        SUM(s.quantity) as total_quantity,
        COUNT(s.id) as sales_count
    FROM sales s
    JOIN promo_codes pc ON s.promo_code_id = pc.id
    JOIN users u ON u.promo_code_id = pc.id
    WHERE u.id = ?
    GROUP BY s.product_name
    ORDER BY total_quantity DESC, s.product_name ASC
");
$stmt->execute([$user['id']]);
$salesData = $stmt->fetchAll();

// Формируем список продуктов
$products = [];
foreach ($salesData as $sale) {
    $products[] = [
        'name' => $sale['product_name'],
        'quantity' => $sale['total_quantity'],
        'sales_count' => $sale['sales_count']
    ];
}


?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дашборд пользователя </title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-gray-100">
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
        <!-- Статистика -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Общая сумма продаж -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-chart-line text-blue-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Общая сумма продаж</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= number_format($totalSales, 0, ',', ' ') ?> шт.</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Списано -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-minus-circle text-red-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Списано</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= number_format($totalDeducted, 0, ',', ' ') ?> шт.</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Остаток -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-wallet text-green-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Остаток</dt>
                                <dd class="text-lg font-medium <?= $remainingBalance >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= number_format($remainingBalance, 0, ',', ' ') ?> шт.
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Блок Реализация -->
        <div class="bg-white shadow rounded-lg mt-8">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                    Реализация
                </h2>

                <?php if (empty($salesData)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-shopping-cart text-4xl mb-4"></i>
                    <p>У вас пока нет реализации продуктов</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Период загрузки</th>
                                <?php foreach ($products as $product): ?>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <?= htmlspecialchars($product) ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($periods as $period): ?>
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                        <?php 
                                        if ($period === 'unknown') {
                                            echo '<span class="text-gray-400">Неизвестный период</span>';
                                        } else {
                                            // Находим информацию о периоде из $salesData
                                            $periodInfo = null;
                                            foreach ($salesData as $sale) {
                                                if ($sale['upload_date'] === $period) {
                                                    $periodInfo = $sale;
                                                    break;
                                                }
                                            }
                                            
                                            if ($periodInfo && !empty($periodInfo['period_from']) && !empty($periodInfo['period_to'])) {
                                                $dateFrom = date('d.m.Y', strtotime($periodInfo['period_from']));
                                                $dateTo = date('d.m.Y', strtotime($periodInfo['period_to']));
                                                echo $dateFrom . ' - ' . $dateTo;
                                            } else {
                                                echo date('d.m.Y H:i', strtotime($period));
                                            }
                                        }
                                        ?>
                                    </td>
                                    <?php foreach ($products as $product): ?>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 text-center font-mono">
                                            <?= isset($pivotData[$period][$product]) ? number_format($pivotData[$period][$product], 0, ',', ' ') : '-' ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</body>
</html>
