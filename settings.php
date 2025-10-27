<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/loading.php';

use ReproCRM\Config\Database;
use ReproCRM\Utils\EmailNotifier;

$_ENV['DB_TYPE'] = 'sqlite';
$db = Database::getInstance();
$pdo = $db;

// Файл конфигурации настроек
$settingsFile = __DIR__ . '/config/settings.json';

// Загружаем настройки
$settings = [
    'email' => [
        'enabled' => false,
        'smtp_host' => 'localhost',
        'smtp_port' => 587,
        'smtp_username' => '',
        'smtp_password' => '',
        'from_email' => 'noreply@reprosystem.ru',
        'from_name' => 'Система РЕПРО'
    ],
    'notifications' => [
        'promo_code_created' => true,
        'sales_report' => true,
        'admin_alerts' => true
    ],
    'system' => [
        'timezone' => 'Europe/Moscow',
        'date_format' => 'd.m.Y',
        'time_format' => 'H:i:s',
        'items_per_page' => 50,
        'backup_retention_days' => 30,
        'audit_log_retention_days' => 365
    ]
];

// Загружаем сохраненные настройки
if (file_exists($settingsFile)) {
    $savedSettings = json_decode(file_get_contents($settingsFile), true);
    if ($savedSettings) {
        $settings = array_merge($settings, $savedSettings);
    }
}

// Обработка сохранения настроек
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    try {
        // Обновляем настройки
        $newSettings = [
            'email' => [
                'enabled' => isset($_POST['email_enabled']),
                'smtp_host' => $_POST['smtp_host'] ?? 'localhost',
                'smtp_port' => (int)($_POST['smtp_port'] ?? 587),
                'smtp_username' => $_POST['smtp_username'] ?? '',
                'smtp_password' => $_POST['smtp_password'] ?? '',
                'from_email' => $_POST['from_email'] ?? 'noreply@reprosystem.ru',
                'from_name' => $_POST['from_name'] ?? 'Система РЕПРО'
            ],
            'notifications' => [
                'promo_code_created' => isset($_POST['promo_code_created']),
                'sales_report' => isset($_POST['sales_report']),
                'admin_alerts' => isset($_POST['admin_alerts'])
            ],
            'system' => [
                'timezone' => $_POST['timezone'] ?? 'Europe/Moscow',
                'date_format' => $_POST['date_format'] ?? 'd.m.Y',
                'time_format' => $_POST['time_format'] ?? 'H:i:s',
                'items_per_page' => (int)($_POST['items_per_page'] ?? 50),
                'backup_retention_days' => (int)($_POST['backup_retention_days'] ?? 30),
                'audit_log_retention_days' => (int)($_POST['audit_log_retention_days'] ?? 365)
            ]
        ];
        
        // Создаем директорию config если не существует
        $configDir = dirname($settingsFile);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        // Сохраняем настройки
        file_put_contents($settingsFile, json_encode($newSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Обновляем переменную
        $settings = $newSettings;
        
        NotificationSystem::success("Настройки успешно сохранены!");
        
    } catch (Exception $e) {
        NotificationSystem::error("Ошибка при сохранении настроек: " . $e->getMessage());
    }
}

// Обработка тестирования email
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'test_email') {
    try {
        $emailNotifier = new EmailNotifier($settings['email']);
        $result = $emailNotifier->testConfiguration();
        
        if ($result['success']) {
            NotificationSystem::success($result['message']);
        } else {
            NotificationSystem::error($result['message']);
        }
        
    } catch (Exception $e) {
        NotificationSystem::error("Ошибка при тестировании email: " . $e->getMessage());
    }
}

// Обработка очистки старых данных
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'cleanup_data') {
    try {
        $cleaned = 0;
        
        // Очистка старых резервных копий
        if (isset($_POST['cleanup_backups'])) {
            $backupDir = __DIR__ . '/backups/';
            if (is_dir($backupDir)) {
                $retentionDays = $settings['system']['backup_retention_days'];
                $files = glob($backupDir . '*.db');
                
                foreach ($files as $file) {
                    if (filemtime($file) < time() - ($retentionDays * 24 * 60 * 60)) {
                        unlink($file);
                        $cleaned++;
                    }
                }
            }
        }
        
        // Очистка старых логов аудита
        if (isset($_POST['cleanup_audit_logs'])) {
            $auditLogger = new \ReproCRM\Utils\AuditLogger($pdo);
            $retentionDays = $settings['system']['audit_log_retention_days'];
            $cleanedLogs = $auditLogger->cleanOldLogs($retentionDays);
            $cleaned += $cleanedLogs;
        }
        
        NotificationSystem::success("Очистка завершена. Удалено записей: {$cleaned}");
        
    } catch (Exception $e) {
        NotificationSystem::error("Ошибка при очистке данных: " . $e->getMessage());
    }
}

// Получаем статистику системы
$systemStats = [];
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM promo_codes");
    $systemStats['promo_codes'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $systemStats['users'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sales");
    $systemStats['sales'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM audit_log");
    $systemStats['audit_logs'] = $stmt->fetch()['count'];
    
    // Размер базы данных
    $dbFile = __DIR__ . '/database/reprocrm.db';
    $systemStats['db_size'] = file_exists($dbFile) ? filesize($dbFile) : 0;
    
    // Количество резервных копий
    $backupDir = __DIR__ . '/backups/';
    $systemStats['backups'] = is_dir($backupDir) ? count(glob($backupDir . '*.db')) : 0;
    
} catch (Exception $e) {
    $systemStats = ['error' => $e->getMessage()];
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $base = log($size, 1024);
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки системы - Система РЕПРО</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-gray-100">
    <?= NotificationSystem::render() ?>
    <?= LoadingSystem::render() ?>
    
<?php include 'admin_navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Статистика системы -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Статистика системы</h3>
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg text-center">
                        <i class="fas fa-ticket-alt text-blue-600 text-2xl mb-2"></i>
                        <p class="text-sm text-blue-900">Промокоды</p>
                        <p class="text-lg font-bold text-blue-600"><?= $systemStats['promo_codes'] ?? 0 ?></p>
                    </div>
                    
                    <div class="bg-green-50 p-4 rounded-lg text-center">
                        <i class="fas fa-user-md text-green-600 text-2xl mb-2"></i>
                        <p class="text-sm text-green-900">Врачи</p>
                        <p class="text-lg font-bold text-green-600"><?= $systemStats['doctors'] ?? 0 ?></p>
                    </div>
                    
                    <div class="bg-purple-50 p-4 rounded-lg text-center">
                        <i class="fas fa-shopping-cart text-purple-600 text-2xl mb-2"></i>
                        <p class="text-sm text-purple-900">Продажи</p>
                        <p class="text-lg font-bold text-purple-600"><?= $systemStats['sales'] ?? 0 ?></p>
                    </div>
                    
                    <div class="bg-orange-50 p-4 rounded-lg text-center">
                        <i class="fas fa-clipboard-list text-orange-600 text-2xl mb-2"></i>
                        <p class="text-sm text-orange-900">Логи аудита</p>
                        <p class="text-lg font-bold text-orange-600"><?= $systemStats['audit_logs'] ?? 0 ?></p>
                    </div>
                    
                    <div class="bg-indigo-50 p-4 rounded-lg text-center">
                        <i class="fas fa-database text-indigo-600 text-2xl mb-2"></i>
                        <p class="text-sm text-indigo-900">Размер БД</p>
                        <p class="text-lg font-bold text-indigo-600"><?= formatBytes($systemStats['db_size'] ?? 0) ?></p>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg text-center">
                        <i class="fas fa-archive text-gray-600 text-2xl mb-2"></i>
                        <p class="text-sm text-gray-900">Резервные копии</p>
                        <p class="text-lg font-bold text-gray-600"><?= $systemStats['backups'] ?? 0 ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Настройки Email -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Настройки Email уведомлений</h3>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="email_enabled" id="email_enabled" 
                               <?= $settings['email']['enabled'] ? 'checked' : '' ?>
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="email_enabled" class="ml-2 block text-sm text-gray-900">
                            Включить email уведомления
                        </label>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="smtp_host" class="block text-sm font-medium text-gray-700">SMTP сервер</label>
                            <input type="text" name="smtp_host" id="smtp_host" 
                                   value="<?= htmlspecialchars($settings['email']['smtp_host']) ?>"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="smtp_port" class="block text-sm font-medium text-gray-700">SMTP порт</label>
                            <input type="number" name="smtp_port" id="smtp_port" 
                                   value="<?= htmlspecialchars($settings['email']['smtp_port']) ?>"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="smtp_username" class="block text-sm font-medium text-gray-700">Имя пользователя</label>
                            <input type="text" name="smtp_username" id="smtp_username" 
                                   value="<?= htmlspecialchars($settings['email']['smtp_username']) ?>"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="smtp_password" class="block text-sm font-medium text-gray-700">Пароль</label>
                            <input type="password" name="smtp_password" id="smtp_password" 
                                   value="<?= htmlspecialchars($settings['email']['smtp_password']) ?>"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="from_email" class="block text-sm font-medium text-gray-700">Email отправителя</label>
                            <input type="email" name="from_email" id="from_email" 
                                   value="<?= htmlspecialchars($settings['email']['from_email']) ?>"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="from_name" class="block text-sm font-medium text-gray-700">Имя отправителя</label>
                            <input type="text" name="from_name" id="from_name" 
                                   value="<?= htmlspecialchars($settings['email']['from_name']) ?>"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-save mr-2"></i>Сохранить настройки
                        </button>
                        
                        <button type="submit" formaction="?action=test_email" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                            <i class="fas fa-paper-plane mr-2"></i>Тестировать Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Настройки уведомлений -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Типы уведомлений</h3>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <input type="checkbox" name="promo_code_created" id="promo_code_created" 
                                   <?= $settings['notifications']['promo_code_created'] ? 'checked' : '' ?>
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="promo_code_created" class="ml-2 block text-sm text-gray-900">
                                Уведомления о создании промокодов
                            </label>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" name="sales_report" id="sales_report" 
                                   <?= $settings['notifications']['sales_report'] ? 'checked' : '' ?>
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="sales_report" class="ml-2 block text-sm text-gray-900">
                                Еженедельные отчеты о продажах
                            </label>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" name="admin_alerts" id="admin_alerts" 
                                   <?= $settings['notifications']['admin_alerts'] ? 'checked' : '' ?>
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="admin_alerts" class="ml-2 block text-sm text-gray-900">
                                Уведомления для администраторов
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Сохранить настройки
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Настройки системы -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Настройки системы</h3>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="timezone" class="block text-sm font-medium text-gray-700">Часовой пояс</label>
                            <select name="timezone" id="timezone" 
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="Europe/Moscow" <?= $settings['system']['timezone'] === 'Europe/Moscow' ? 'selected' : '' ?>>Москва</option>
                                <option value="Europe/Kiev" <?= $settings['system']['timezone'] === 'Europe/Kiev' ? 'selected' : '' ?>>Киев</option>
                                <option value="Europe/Minsk" <?= $settings['system']['timezone'] === 'Europe/Minsk' ? 'selected' : '' ?>>Минск</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="date_format" class="block text-sm font-medium text-gray-700">Формат даты</label>
                            <select name="date_format" id="date_format" 
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="d.m.Y" <?= $settings['system']['date_format'] === 'd.m.Y' ? 'selected' : '' ?>>dd.mm.yyyy</option>
                                <option value="Y-m-d" <?= $settings['system']['date_format'] === 'Y-m-d' ? 'selected' : '' ?>>yyyy-mm-dd</option>
                                <option value="m/d/Y" <?= $settings['system']['date_format'] === 'm/d/Y' ? 'selected' : '' ?>>mm/dd/yyyy</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="time_format" class="block text-sm font-medium text-gray-700">Формат времени</label>
                            <select name="time_format" id="time_format" 
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="H:i:s" <?= $settings['system']['time_format'] === 'H:i:s' ? 'selected' : '' ?>>24:00:00</option>
                                <option value="h:i:s A" <?= $settings['system']['time_format'] === 'h:i:s A' ? 'selected' : '' ?>>12:00:00 PM</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="items_per_page" class="block text-sm font-medium text-gray-700">Элементов на странице</label>
                            <input type="number" name="items_per_page" id="items_per_page" 
                                   value="<?= htmlspecialchars($settings['system']['items_per_page']) ?>"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="backup_retention_days" class="block text-sm font-medium text-gray-700">Хранение резервных копий (дней)</label>
                            <input type="number" name="backup_retention_days" id="backup_retention_days" 
                                   value="<?= htmlspecialchars($settings['system']['backup_retention_days']) ?>"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="audit_log_retention_days" class="block text-sm font-medium text-gray-700">Хранение логов аудита (дней)</label>
                            <input type="number" name="audit_log_retention_days" id="audit_log_retention_days" 
                                   value="<?= htmlspecialchars($settings['system']['audit_log_retention_days']) ?>"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Сохранить настройки
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Очистка данных -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Очистка данных</h3>
                <p class="text-gray-600 mb-4">Удаление старых данных для освобождения места на диске.</p>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="cleanup_data">
                    
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <input type="checkbox" name="cleanup_backups" id="cleanup_backups" 
                                   class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                            <label for="cleanup_backups" class="ml-2 block text-sm text-gray-900">
                                Удалить старые резервные копии (старше <?= $settings['system']['backup_retention_days'] ?> дней)
                            </label>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" name="cleanup_audit_logs" id="cleanup_audit_logs" 
                                   class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                            <label for="cleanup_audit_logs" class="ml-2 block text-sm text-gray-900">
                                Удалить старые логи аудита (старше <?= $settings['system']['audit_log_retention_days'] ?> дней)
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700"
                            onclick="return confirm('Вы уверены, что хотите удалить старые данные? Это действие нельзя отменить.')">
                        <i class="fas fa-trash mr-2"></i>Выполнить очистку
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
