<?php

// Проверка авторизации администратора
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin.php');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
use ReproCRM\Config\Database;

$_ENV['DB_TYPE'] = 'sqlite';
$db = Database::getInstance();
$pdo = $db;

// Получаем статистику за последние 30 дней
$stmt = $pdo->query("
    SELECT 
        DATE(s.created_at) as date,
        COUNT(s.id) as sales_count,
        SUM(s.quantity) as total_quantity
    FROM sales s
    WHERE s.created_at >= date('now', '-30 days')
    GROUP BY DATE(s.created_at)
    ORDER BY date DESC
");
$dailyStats = $stmt->fetchAll();

// Статистика по месяцам
$stmt = $pdo->query("
    SELECT 
        strftime('%Y-%m', s.created_at) as month,
        COUNT(s.id) as sales_count,
        SUM(s.quantity) as total_quantity,
        COUNT(DISTINCT s.promo_code_id) as unique_promo_codes
    FROM sales s
    WHERE s.created_at >= date('now', '-12 months')
    GROUP BY strftime('%Y-%m', s.created_at)
    ORDER BY month DESC
");
$monthlyStats = $stmt->fetchAll();

// Топ городов по продажам
$stmt = $pdo->query("
    SELECT 
        d.city,
        COUNT(s.id) as sales_count,
        SUM(s.quantity) as total_quantity,
        COUNT(DISTINCT d.id) as doctors_count
    FROM sales s
    JOIN promo_codes pc ON s.promo_code_id = pc.id
    JOIN doctors d ON d.promo_code_id = pc.id
    GROUP BY d.city
    ORDER BY sales_count DESC
    LIMIT 10
");
$cityStats = $stmt->fetchAll();

// Статистика по продуктам
$stmt = $pdo->query("
    SELECT 
        s.product_name,
        COUNT(s.id) as sales_count,
        SUM(s.quantity) as total_quantity,
        AVG(s.quantity) as avg_quantity
    FROM sales s
    GROUP BY s.product_name
    ORDER BY sales_count DESC
    LIMIT 10
");
$productStats = $stmt->fetchAll();

// Общая статистика
$stmt = $pdo->query("SELECT COUNT(*) as count FROM promo_codes");
$totalPromoCodes = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
$totalUsers = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM sales");
$totalSales = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(DISTINCT promo_code_id) as count FROM sales");
$activePromoCodes = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT SUM(quantity) as total FROM sales");
$totalQuantity = $stmt->fetch()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Аналитика - Система РЕПРО</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="min-h-screen bg-gray-100">
<?php include 'admin_navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Общая статистика -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-ticket-alt text-blue-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Всего промокодов</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $totalPromoCodes ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-chart-line text-green-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Активных промокодов</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $activePromoCodes ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-user-md text-purple-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Пользователей</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $totalUsers ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-shopping-cart text-orange-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Продаж</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $totalSales ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-box text-indigo-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Товаров продано</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $totalQuantity ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Графики -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- График продаж за последние 30 дней -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Продажи за последние 30 дней</h3>
                <canvas id="dailyChart" width="400" height="200"></canvas>
            </div>
            
            <!-- График продаж по месяцам -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Продажи по месяцам</h3>
                <canvas id="monthlyChart" width="400" height="200"></canvas>
            </div>
        </div>
        
        <!-- Таблицы статистики -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Топ городов -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Топ городов по продажам</h3>
                    <div class="overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Город</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Продажи</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Товары</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Врачи</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($cityStats as $city): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($city['city']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $city['sales_count'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $city['total_quantity'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $city['doctors_count'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Топ продукты -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Топ продукты</h3>
                    <div class="overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Продукт</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Продажи</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Товары</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Среднее</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($productStats as $product): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($product['product_name']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $product['sales_count'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $product['total_quantity'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= round($product['avg_quantity'], 1) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // График продаж за последние 30 дней
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyChart = new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: [<?php 
                    $labels = array_reverse(array_column($dailyStats, 'date'));
                    echo "'" . implode("','", $labels) . "'";
                ?>],
                datasets: [{
                    label: 'Количество продаж',
                    data: [<?php 
                        $sales = array_reverse(array_column($dailyStats, 'sales_count'));
                        echo implode(',', $sales);
                    ?>],
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1
                }, {
                    label: 'Количество товаров',
                    data: [<?php 
                        $quantities = array_reverse(array_column($dailyStats, 'total_quantity'));
                        echo implode(',', $quantities);
                    ?>],
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // График продаж по месяцам
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: [<?php 
                    $monthLabels = array_reverse(array_column($monthlyStats, 'month'));
                    echo "'" . implode("','", $monthLabels) . "'";
                ?>],
                datasets: [{
                    label: 'Продажи',
                    data: [<?php 
                        $monthSales = array_reverse(array_column($monthlyStats, 'sales_count'));
                        echo implode(',', $monthSales);
                    ?>],
                    backgroundColor: 'rgba(99, 102, 241, 0.8)',
                    borderColor: 'rgb(99, 102, 241)',
                    borderWidth: 1
                }, {
                    label: 'Активные промокоды',
                    data: [<?php 
                        $monthPromos = array_reverse(array_column($monthlyStats, 'unique_promo_codes'));
                        echo implode(',', $monthPromos);
                    ?>],
                    backgroundColor: 'rgba(245, 158, 11, 0.8)',
                    borderColor: 'rgb(245, 158, 11)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
