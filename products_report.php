<?php

// Проверка авторизации администратора
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin.php');
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

// Получаем данные о товарах и количестве продаж
$stmt = $pdo->query("
    SELECT 
        product_name,
        COUNT(*) as sales_count,
        COALESCE(SUM(quantity), 0) as total_quantity
    FROM sales
    GROUP BY product_name
    ORDER BY total_quantity DESC
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Общая статистика
$totalProducts = count($products);
$totalSales = 0;
foreach ($products as $product) {
    $totalSales += $product['total_quantity'];
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(Config::getAppName()) ?> - Отчет по товарам</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-gray-100">
<?php include 'admin_navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Статистика -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-box text-blue-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Всего товаров</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= number_format($totalProducts, 0, ',', ' ') ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-shopping-cart text-green-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Количество проданных товаров</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= number_format($totalSales, 0, ',', ' ') ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Таблица товаров -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        <i class="fas fa-box mr-2"></i>Отчет по товарам (<?= $totalProducts ?>)
                    </h3>
                </div>
                
                <?php if (empty($products)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-box-open text-gray-400 text-5xl mb-4"></i>
                        <p class="text-gray-500 text-lg">Товары не найдены</p>
                        <p class="text-gray-400 text-sm mt-2">В системе пока нет записей о продажах товаров</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Наименование товара
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Количество проданных товаров
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($products as $product): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($product['product_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-semibold">
                                        <?= number_format($product['total_quantity'], 0, ',', ' ') ?>
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
</body>
</html>
