<?php

// Проверка авторизации администратора
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin.php');
    exit;
}

// Простая версия дашборда администратора
require_once __DIR__ . '/vendor/autoload.php';

use ReproCRM\Config\Config;
Config::load();
$_ENV['DB_TYPE'] = 'sqlite';

// Подключаемся к базе данных
$dbPath = __DIR__ . '/database/reprocrm.db';
$pdo = new PDO("sqlite:" . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Получаем статистику
$stmt = $pdo->query("SELECT COUNT(*) as count FROM promo_codes");
$totalPromoCodes = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
$registeredUsers = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM sales");
$totalSales = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM promo_codes WHERE status = 'unregistered'");
$unregisteredCodes = $stmt->fetch()['count'];

// Топ врачей
$stmt = $pdo->query("
    SELECT 
        d.full_name,
        d.city,
        pc.code as promo_code,
        COUNT(s.id) as sales_count
    FROM users d
    LEFT JOIN promo_codes pc ON d.promo_code_id = pc.id
    LEFT JOIN sales s ON s.promo_code_id = pc.id
    GROUP BY d.id, d.full_name, d.city, pc.code
    ORDER BY sales_count DESC
    LIMIT 10
");
$topDoctors = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Система РЕПРО - Админка</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-gray-100">
<?php include 'admin_navigation.php'; ?>


    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Статистика -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
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
                            <i class="fas fa-user-md text-green-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Зарегистрированных пользователей</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $registeredUsers ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-shopping-cart text-purple-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Всего продаж</dt>
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
                            <i class="fas fa-exclamation-circle text-orange-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Незарегистрированных</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $unregisteredCodes ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Навигация -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Управление системой</h3>
                <div class="grid grid-cols-2 gap-4">
                    <a href="/file_upload.php" class="bg-purple-600 text-white px-4 py-3 rounded-lg hover:bg-purple-700 text-center transition-all duration-200 hover:transform hover:scale-105">
                        <i class="fas fa-upload text-xl mb-2 block"></i>
                        <span class="text-sm font-medium">Загрузка файлов</span>
                    </a>
                    <a href="/backup_restore.php" class="bg-orange-600 text-white px-4 py-3 rounded-lg hover:bg-orange-700 text-center transition-all duration-200 hover:transform hover:scale-105">
                        <i class="fas fa-database text-xl mb-2 block"></i>
                        <span class="text-sm font-medium">Резервирование</span>
                    </a>
                </div>
                
            </div>
        </div>
        
        <!-- Топ врачей -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Топ пользователей по продажам</h3>
                <div class="overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Пользователь</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Город</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Промокод</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Продажи</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($topDoctors as $doctor): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($doctor['full_name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($doctor['city']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono"><?= htmlspecialchars($doctor['promo_code']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $doctor['sales_count'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
