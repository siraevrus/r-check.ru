<?php

// Включаем отображение ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Получаем параметры фильтрации
$filterCity = $_GET['city'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$sortBy = $_GET['sort'] ?? 'sales_count';
$sortOrder = $_GET['order'] ?? 'desc';

// Валидация параметров сортировки
$allowedSorts = ['sales_count', 'total_quantity', 'total_sales', 'full_name', 'city', 'created_at'];
$sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'total_sales';
$sortOrder = in_array($sortOrder, ['asc', 'desc']) ? $sortOrder : 'desc';

// Получаем список городов для фильтра
$citiesStmt = $pdo->query("SELECT DISTINCT city FROM users WHERE city IS NOT NULL AND city != '' ORDER BY city");
$cities = $citiesStmt->fetchAll(PDO::FETCH_COLUMN);

// Формируем SQL запрос с фильтрами
$whereConditions = [];
$params = [];

if (!empty($filterCity)) {
    $whereConditions[] = "u.city = ?";
    $params[] = $filterCity;
}

if (!empty($filterStatus)) {
    if ($filterStatus === 'registered') {
        $whereConditions[] = "u.promo_code_id IS NOT NULL";
    } elseif ($filterStatus === 'unregistered') {
        $whereConditions[] = "u.promo_code_id IS NULL";
    }
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$sql = "
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.city,
        u.created_at,
        pc.code as promo_code,
        COUNT(s.id) as sales_count,
        COALESCE(SUM(s.quantity), 0) as total_quantity,
        COALESCE(pc.total_sales, 0) as total_sales,
        CASE 
            WHEN u.promo_code_id IS NOT NULL THEN 'registered'
            ELSE 'unregistered'
        END as registration_status
    FROM users u
    LEFT JOIN promo_codes pc ON u.promo_code_id = pc.id
    LEFT JOIN sales s ON s.promo_code_id = pc.id
    {$whereClause}
    GROUP BY u.id, u.full_name, u.email, u.city, u.created_at, pc.code, pc.total_sales
    ORDER BY {$sortBy} {$sortOrder}
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("SQL Error in users_report.php: " . $e->getMessage());
    error_log("SQL Query: " . $sql);
    error_log("Parameters: " . print_r($params, true));
    die("Ошибка выполнения запроса: " . $e->getMessage());
}

// Статистика
try {
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total_users
        FROM users
    ");
    $stats = $statsStmt->fetch();
} catch (PDOException $e) {
    error_log("SQL Error in stats query: " . $e->getMessage());
    $stats = ['total_users' => 0];
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отчет по пользователям </title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-gray-100">
<?php include 'admin_navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Статистика -->
        <div class="grid grid-cols-1 md:grid-cols-1 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-user-md text-blue-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Всего пользователей</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $stats['total_users'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- Фильтры -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Фильтры и сортировка</h3>
                
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Город</label>
                        <select name="city" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Все города</option>
                            <?php foreach ($cities as $city): ?>
                                <option value="<?= htmlspecialchars($city) ?>" <?= $filterCity === $city ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($city) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Статус</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Все статусы</option>
                            <option value="registered" <?= $filterStatus === 'registered' ? 'selected' : '' ?>>Зарегистрированные</option>
                            <option value="unregistered" <?= $filterStatus === 'unregistered' ? 'selected' : '' ?>>Незарегистрированные</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Сортировка</label>
                        <select name="sort" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="sales_count" <?= $sortBy === 'sales_count' ? 'selected' : '' ?>>По количеству продаж</option>
                            <option value="full_name" <?= $sortBy === 'full_name' ? 'selected' : '' ?>>По имени</option>
                            <option value="city" <?= $sortBy === 'city' ? 'selected' : '' ?>>По городу</option>
                            <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>По дате регистрации</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Порядок</label>
                        <select name="order" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="desc" <?= $sortOrder === 'desc' ? 'selected' : '' ?>>По убыванию</option>
                            <option value="asc" <?= $sortOrder === 'asc' ? 'selected' : '' ?>>По возрастанию</option>
                        </select>
                    </div>
                    
                    <div class="md:col-span-4">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-filter mr-2"></i>Применить фильтры
                        </button>
                        <a href="/users_report.php" class="ml-2 bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700">
                            <i class="fas fa-times mr-2"></i>Сбросить
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Список пользователей -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Список пользователей (<?= count($users) ?>)
                    </h3>
                    <div>
                        <a href="/export_excel.php?type=doctors" 
                           class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm">
                            <i class="fas fa-file-excel mr-2"></i>Выгрузить отчет
                        </a>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Пользователь</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Город</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Промокод</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Продажи</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата регистрации</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <a href="/user_profile.php?id=<?= $user['id'] ?>" class="text-blue-600 hover:text-blue-800 hover:underline">
                                        <?= htmlspecialchars($user['full_name']) ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($user['email']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($user['city']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                    <?= htmlspecialchars($user['promo_code'] ?? '-') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $user['registration_status'] === 'registered' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                        <?= $user['registration_status'] === 'registered' ? 'Зарегистрирован' : 'Не зарегистрирован' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-semibold">
                                    <?= number_format($user['total_sales'], 0, ',', ' ') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('d.m.Y', strtotime($user['created_at'])) ?>
                                </td>
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
