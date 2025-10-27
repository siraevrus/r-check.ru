<?php

// Проверка авторизации администратора
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin.php');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/loading.php';

use ReproCRM\Config\Database;

$_ENV['DB_TYPE'] = 'sqlite';
$db = Database::getInstance();
$pdo = $db;

$message = '';
$messageType = '';

// Обработка создания резервной копии
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'create_backup') {
    try {
        $backupDir = __DIR__ . '/backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $backupDir . "reprocrm_backup_{$timestamp}.db";
        $originalDb = __DIR__ . '/database/reprocrm.db';
        
        if (copy($originalDb, $backupFile)) {
            NotificationSystem::success("Резервная копия успешно создана: reprocrm_backup_{$timestamp}.db");
        } else {
            NotificationSystem::error("Ошибка при создании резервной копии");
        }
    } catch (Exception $e) {
        NotificationSystem::error("Ошибка: " . $e->getMessage());
    }
}

// Обработка восстановления из резервной копии
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'restore_backup') {
    $backupFile = $_POST['backup_file'] ?? '';
    
    if (!empty($backupFile)) {
        try {
            $backupPath = __DIR__ . '/backups/' . $backupFile;
            $originalDb = __DIR__ . '/database/reprocrm.db';
            
            if (file_exists($backupPath)) {
                // Создаем резервную копию текущей базы перед восстановлением
                $currentBackup = __DIR__ . '/backups/reprocrm_backup_before_restore_' . date('Y-m-d_H-i-s') . '.db';
                copy($originalDb, $currentBackup);
                
                if (copy($backupPath, $originalDb)) {
                    NotificationSystem::success("База данных успешно восстановлена из файла: {$backupFile}");
                } else {
                    NotificationSystem::error("Ошибка при восстановлении базы данных");
                }
            } else {
                NotificationSystem::error("Файл резервной копии не найден");
            }
        } catch (Exception $e) {
            NotificationSystem::error("Ошибка: " . $e->getMessage());
        }
    } else {
        NotificationSystem::error("Выберите файл для восстановления");
    }
}

// Обработка удаления резервной копии
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete_backup') {
    $backupFile = $_POST['backup_file'] ?? '';
    
    if (!empty($backupFile)) {
        try {
            $backupPath = __DIR__ . '/backups/' . $backupFile;
            
            if (file_exists($backupPath) && unlink($backupPath)) {
                NotificationSystem::success("Резервная копия {$backupFile} успешно удалена");
            } else {
                NotificationSystem::error("Ошибка при удалении файла или файл не найден");
            }
        } catch (Exception $e) {
            NotificationSystem::error("Ошибка: " . $e->getMessage());
        }
    }
}

// Получаем список резервных копий
$backupDir = __DIR__ . '/backups/';
$backups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'db') {
            $filePath = $backupDir . $file;
            $backups[] = [
                'name' => $file,
                'size' => filesize($filePath),
                'date' => filemtime($filePath),
                'path' => $filePath
            ];
        }
    }
    
    // Сортируем по дате (новые сверху)
    usort($backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Получаем статистику базы данных
$stats = [];
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM promo_codes");
    $stats['promo_codes'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $stats['users'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sales");
    $stats['sales'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM admins");
    $stats['admins'] = $stmt->fetch()['count'];
} catch (Exception $e) {
    $stats = ['error' => $e->getMessage()];
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
    <title>Резервное копирование - Система РЕПРО</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-gray-100">
    <?= NotificationSystem::render() ?>
    <?= LoadingSystem::render() ?>
    
<?php include 'admin_navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Статистика базы данных -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Статистика базы данных</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-ticket-alt text-blue-600 text-2xl mr-3"></i>
                            <div>
                                <p class="text-sm font-medium text-blue-900">Промокоды</p>
                                <p class="text-2xl font-bold text-blue-600"><?= $stats['promo_codes'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-green-50 p-4 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-user-md text-green-600 text-2xl mr-3"></i>
                            <div>
                                <p class="text-sm font-medium text-green-900">Врачи</p>
                                <p class="text-2xl font-bold text-green-600"><?= $stats['doctors'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-shopping-cart text-purple-600 text-2xl mr-3"></i>
                            <div>
                                <p class="text-sm font-medium text-purple-900">Продажи</p>
                                <p class="text-2xl font-bold text-purple-600"><?= $stats['sales'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-orange-50 p-4 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-user-shield text-orange-600 text-2xl mr-3"></i>
                            <div>
                                <p class="text-sm font-medium text-orange-900">Администраторы</p>
                                <p class="text-2xl font-bold text-orange-600"><?= $stats['admins'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Создание резервной копии -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Создать резервную копию</h3>
                <p class="text-gray-600 mb-4">Создайте резервную копию текущего состояния базы данных.</p>
                
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="create_backup">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-medium">
                        <i class="fas fa-download mr-2"></i>Создать резервную копию
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Список резервных копий -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Резервные копии (<?= count($backups) ?>)</h3>
                
                <?php if (empty($backups)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-database text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500">Резервные копии не найдены</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Файл</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Размер</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата создания</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($backups as $backup): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <i class="fas fa-database text-blue-600 mr-3"></i>
                                                <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($backup['name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= formatBytes($backup['size']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('d.m.Y H:i:s', $backup['date']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            <form method="POST" class="inline" onsubmit="return confirm('Вы уверены, что хотите восстановить базу данных из этой резервной копии?')">
                                                <input type="hidden" name="action" value="restore_backup">
                                                <input type="hidden" name="backup_file" value="<?= htmlspecialchars($backup['name']) ?>">
                                                <button type="submit" class="text-green-600 hover:text-green-900">
                                                    <i class="fas fa-upload mr-1"></i>Восстановить
                                                </button>
                                            </form>
                                            
                                            <form method="POST" class="inline" onsubmit="return confirm('Вы уверены, что хотите удалить эту резервную копию?')">
                                                <input type="hidden" name="action" value="delete_backup">
                                                <input type="hidden" name="backup_file" value="<?= htmlspecialchars($backup['name']) ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash mr-1"></i>Удалить
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Информация -->
        <div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Важная информация</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <ul class="list-disc list-inside space-y-1">
                            <li>Резервные копии сохраняются в папку <code>/backups/</code></li>
                            <li>Перед восстановлением автоматически создается резервная копия текущего состояния</li>
                            <li>Рекомендуется создавать резервные копии перед важными изменениями</li>
                            <li>Регулярно очищайте старые резервные копии для экономии места</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
