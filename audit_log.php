<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/loading.php';

use ReproCRM\Config\Database;
use ReproCRM\Utils\AuditLogger;

$_ENV['DB_TYPE'] = 'sqlite';
$db = Database::getInstance();
$pdo = $db;
$auditLogger = new AuditLogger($pdo);

// Получаем фильтры
$filters = [
    'user_type' => $_GET['user_type'] ?? '',
    'action' => $_GET['action'] ?? '',
    'user_email' => $_GET['user_email'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

// Получаем логи
$logs = $auditLogger->getLogs($filters, $limit, $offset);

// Получаем статистику
$stats = $auditLogger->getStats(30);

// Получаем общее количество записей для пагинации
$countSql = "SELECT COUNT(*) FROM audit_log";
$whereConditions = [];
$params = [];

foreach ($filters as $key => $value) {
    if (!empty($value)) {
        switch ($key) {
            case 'user_type':
            case 'action':
                $whereConditions[] = "{$key} = ?";
                $params[] = $value;
                break;
            case 'user_email':
                $whereConditions[] = "user_email LIKE ?";
                $params[] = "%{$value}%";
                break;
            case 'date_from':
                $whereConditions[] = "created_at >= ?";
                $params[] = $value;
                break;
            case 'date_to':
                $whereConditions[] = "created_at <= ?";
                $params[] = $value;
                break;
        }
    }
}

if (!empty($whereConditions)) {
    $countSql .= " WHERE " . implode(' AND ', $whereConditions);
}

$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

function getActionIcon($action) {
    $icons = [
        'login_success' => 'fas fa-sign-in-alt text-green-600',
        'login_failed' => 'fas fa-times-circle text-red-600',
        'logout' => 'fas fa-sign-out-alt text-blue-600',
        'create_promo' => 'fas fa-plus-circle text-green-600',
        'delete_promo' => 'fas fa-trash text-red-600',
        'file_upload' => 'fas fa-upload text-purple-600',
        'backup_created' => 'fas fa-download text-blue-600',
        'backup_restored' => 'fas fa-upload text-orange-600',
        'bulk_action' => 'fas fa-tasks text-indigo-600'
    ];
    
    return $icons[$action] ?? 'fas fa-info-circle text-gray-600';
}

function getActionText($action) {
    $texts = [
        'login_success' => 'Успешный вход',
        'login_failed' => 'Неудачный вход',
        'logout' => 'Выход из системы',
        'create_promo' => 'Создание промокода',
        'delete_promo' => 'Удаление промокода',
        'file_upload' => 'Загрузка файла',
        'backup_created' => 'Создание резервной копии',
        'backup_restored' => 'Восстановление из резервной копии',
        'bulk_action' => 'Массовая операция'
    ];
    
    return $texts[$action] ?? $action;
}

function getUserTypeBadge($userType) {
    if ($userType === 'admin') {
        return '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Администратор</span>';
    } else {
        return '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Врач</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Журнал аудита - Система РЕПРО</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-gray-100">
    <?= NotificationSystem::render() ?>
    <?= LoadingSystem::render() ?>
    
<?php include 'admin_navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Статистика -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Статистика действий (последние 30 дней)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php foreach ($stats as $stat): ?>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="flex items-center">
                                <i class="<?= getActionIcon($stat['action']) ?> text-2xl mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900"><?= getActionText($stat['action']) ?></p>
                                    <p class="text-lg font-bold text-gray-600"><?= $stat['count'] ?></p>
                                    <p class="text-xs text-gray-500"><?= getUserTypeBadge($stat['user_type']) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Фильтры -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Фильтры</h3>
                
                <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Тип пользователя</label>
                        <select name="user_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Все</option>
                            <option value="admin" <?= $filters['user_type'] === 'admin' ? 'selected' : '' ?>>Администратор</option>
                            <option value="doctor" <?= $filters['user_type'] === 'doctor' ? 'selected' : '' ?>>Врач</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Действие</label>
                        <select name="action" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Все</option>
                            <option value="login_success" <?= $filters['action'] === 'login_success' ? 'selected' : '' ?>>Успешный вход</option>
                            <option value="login_failed" <?= $filters['action'] === 'login_failed' ? 'selected' : '' ?>>Неудачный вход</option>
                            <option value="logout" <?= $filters['action'] === 'logout' ? 'selected' : '' ?>>Выход</option>
                            <option value="create_promo" <?= $filters['action'] === 'create_promo' ? 'selected' : '' ?>>Создание промокода</option>
                            <option value="delete_promo" <?= $filters['action'] === 'delete_promo' ? 'selected' : '' ?>>Удаление промокода</option>
                            <option value="file_upload" <?= $filters['action'] === 'file_upload' ? 'selected' : '' ?>>Загрузка файла</option>
                            <option value="backup_created" <?= $filters['action'] === 'backup_created' ? 'selected' : '' ?>>Создание резервной копии</option>
                            <option value="backup_restored" <?= $filters['action'] === 'backup_restored' ? 'selected' : '' ?>>Восстановление</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email пользователя</label>
                        <input type="text" name="user_email" value="<?= htmlspecialchars($filters['user_email']) ?>"
                               placeholder="Поиск по email..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Дата с</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Дата по</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="md:col-span-5 flex space-x-2">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-search mr-2"></i>Применить фильтры
                        </button>
                        <a href="/audit_log.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700">
                            <i class="fas fa-times mr-2"></i>Сбросить
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Журнал аудита -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Журнал действий (<?= $totalRecords ?> записей)
                    </h3>
                    <div class="text-sm text-gray-500">
                        Страница <?= $page ?> из <?= $totalPages ?>
                    </div>
                </div>
                
                <?php if (empty($logs)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-clipboard-list text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500">Записи не найдены</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Время</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Пользователь</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Действие</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ресурс</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP адрес</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Детали</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <?= getUserTypeBadge($log['user_type']) ?>
                                                <div class="ml-2">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($log['user_email']) ?></div>
                                                    <?php if ($log['user_id']): ?>
                                                        <div class="text-sm text-gray-500">ID: <?= $log['user_id'] ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <i class="<?= getActionIcon($log['action']) ?> mr-2"></i>
                                                <span class="text-sm text-gray-900"><?= getActionText($log['action']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php if ($log['resource_type'] && $log['resource_id']): ?>
                                                <?= ucfirst($log['resource_type']) ?> #<?= $log['resource_id'] ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($log['ip_address']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php if ($log['details']): ?>
                                                <button onclick="showDetails('<?= htmlspecialchars($log['details']) ?>')" 
                                                        class="text-blue-600 hover:text-blue-900">
                                                    <i class="fas fa-eye mr-1"></i>Подробности
                                                </button>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Пагинация -->
                    <?php if ($totalPages > 1): ?>
                        <div class="mt-6 flex justify-center">
                            <nav class="flex space-x-2">
                                <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                        Предыдущая
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                       class="px-3 py-2 text-sm font-medium <?= $i === $page ? 'text-blue-600 bg-blue-50 border-blue-300' : 'text-gray-500 bg-white border-gray-300' ?> border rounded-md hover:bg-gray-50">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                        Следующая
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Модальное окно для деталей -->
    <div id="detailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Детали действия</h3>
                </div>
                <div class="px-6 py-4">
                    <pre id="detailsContent" class="text-sm text-gray-700 bg-gray-100 p-4 rounded-lg overflow-auto"></pre>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
                    <button onclick="closeDetails()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                        Закрыть
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showDetails(details) {
            const modal = document.getElementById('detailsModal');
            const content = document.getElementById('detailsContent');
            
            try {
                const parsed = JSON.parse(details);
                content.textContent = JSON.stringify(parsed, null, 2);
            } catch (e) {
                content.textContent = details;
            }
            
            modal.classList.remove('hidden');
        }
        
        function closeDetails() {
            document.getElementById('detailsModal').classList.add('hidden');
        }
        
        // Закрытие модального окна по клику вне его
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetails();
            }
        });
    </script>
</body>
</html>
