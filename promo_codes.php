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

use ReproCRM\Config\Config;
use ReproCRM\Utils\Validator;

Config::load();
$_ENV['DB_TYPE'] = 'sqlite';

// Подключаемся к базе данных
$dbPath = __DIR__ . '/database/reprocrm.db';
$pdo = new PDO("sqlite:" . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Обработка действий с промокодами и пользователями
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_promo') {
        $promoId = (int)$_POST['promo_id'];

        if ($promoId > 0) {
            try {
                // Проверяем, есть ли связанные записи
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE promo_code_id = ?");
                $stmt->execute([$promoId]);
                $userCount = $stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE promo_code_id = ?");
                $stmt->execute([$promoId]);
                $salesCount = $stmt->fetchColumn();

                if ($userCount > 0 || $salesCount > 0) {
                    NotificationSystem::error("Нельзя удалить промокод, который используется пользователями или имеет продажи");
                } else {
                    $stmt = $pdo->prepare("DELETE FROM promo_codes WHERE id = ?");
                    $stmt->execute([$promoId]);
                    NotificationSystem::success("Промокод успешно удален!");
                }
            } catch (Exception $e) {
                NotificationSystem::error("Ошибка при удалении промокода: " . $e->getMessage());
            }
        } else {
            NotificationSystem::error("Неверный ID промокода");
        }
    } elseif ($_POST['action'] === 'delete_user_from_promo') {
        $promoId = (int)$_POST['promo_id'];

        if ($promoId > 0) {
            try {
                // Проверяем, что промокод существует и зарегистрирован
                $stmt = $pdo->prepare("SELECT id, code FROM promo_codes WHERE id = ?");
                $stmt->execute([$promoId]);
                $promo = $stmt->fetch();

                if (!$promo) {
                    NotificationSystem::error("Промокод не найден");
                } else {
                    // Получаем ID пользователя, связанного с промокодом
                    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE promo_code_id = ?");
                    $stmt->execute([$promoId]);
                    $user = $stmt->fetch();

                    if (!$user) {
                        NotificationSystem::error("Пользователь не найден");
                    } else {
                        $pdo->beginTransaction();
                        
                        try {
                            // Удаляем пользователя из базы данных
                            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                            $stmt->execute([$user['id']]);

                            // Обновляем статус промокода на "unregistered"
                            $stmt = $pdo->prepare("UPDATE promo_codes SET status = 'unregistered', updated_at = datetime('now') WHERE id = ?");
                            $stmt->execute([$promoId]);

                            $pdo->commit();
                            
                            NotificationSystem::success("Пользователь '{$user['full_name']}' успешно отвязан от промокода '{$promo['code']}' и удален из системы");
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            throw $e;
                        }
                    }
                }
            } catch (Exception $e) {
                NotificationSystem::error("Ошибка при удалении пользователя: " . $e->getMessage());
            }
        } else {
            NotificationSystem::error("Неверный ID промокода");
        }
    }
}

// Обработка добавления промокода (одного или нескольких)
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_promo') {
    $codesInput = strtoupper(trim($_POST['code']));
    
    // Разделяем по запятой, пробелу или переносу строки
    $codes = preg_split('/[\s,;]+/', $codesInput, -1, PREG_SPLIT_NO_EMPTY);
    
    $validator = new Validator();
    $addedCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    
    foreach ($codes as $code) {
        $code = trim($code);
        
        if (empty($code)) {
            continue;
        }
        
        // Валидация промокода
        if ($validator->validatePromoCode($code)) {
            // Проверяем уникальность
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM promo_codes WHERE code = ?");
            $stmt->execute([$code]);
            $exists = $stmt->fetchColumn();
            
            if ($exists > 0) {
                $skippedCount++;
                continue; // Промокод уже существует, пропускаем
            }
            
            try {
                // Добавляем промокод
                $stmt = $pdo->prepare("INSERT INTO promo_codes (code, status, created_at, updated_at) VALUES (?, 'unregistered', datetime('now'), datetime('now'))");
                $stmt->execute([$code]);
                $addedCount++;
            } catch (Exception $e) {
                $errorCount++;
            }
        } else {
            $errorCount++;
        }
    }
    
    // Показываем итоговое сообщение
    if ($addedCount > 0) {
        NotificationSystem::success("Успешно добавлено промокодов: {$addedCount}");
    }
    if ($skippedCount > 0) {
        NotificationSystem::warning("Пропущено (уже существуют): {$skippedCount}");
    }
    if ($errorCount > 0) {
        NotificationSystem::error("Ошибок при добавлении: {$errorCount}");
    }
    if ($addedCount === 0 && $skippedCount === 0 && $errorCount === 0) {
        NotificationSystem::error("Не указаны промокоды");
    }
}


// Получаем параметры поиска
$searchQuery = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Формируем SQL запрос с поиском
$whereConditions = [];
$params = [];

if (!empty($searchQuery)) {
    $whereConditions[] = "(pc.code LIKE ? OR d.full_name LIKE ? OR d.email LIKE ? OR d.city LIKE ?)";
    $searchTerm = "%{$searchQuery}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($statusFilter)) {
    $whereConditions[] = "pc.status = ?";
    $params[] = $statusFilter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$sql = "
    SELECT 
        pc.id,
        pc.code,
        pc.status,
        pc.created_at,
        pc.updated_at,
        d.id as doctor_id,
        d.full_name,
        d.email,
        d.city,
        COUNT(s.id) as sales_count,
        COALESCE(SUM(s.quantity), 0) as total_sales
    FROM promo_codes pc
    LEFT JOIN users d ON d.promo_code_id = pc.id
    LEFT JOIN sales s ON s.promo_code_id = pc.id
    {$whereClause}
    GROUP BY pc.id, pc.code, pc.status, pc.created_at, pc.updated_at, d.id, d.full_name, d.email, d.city
    ORDER BY pc.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$promoCodes = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление промокодами</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body class="min-h-screen bg-gray-100">
        <?= NotificationSystem::render() ?>
        <?= LoadingSystem::render() ?>
<?php include 'admin_navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Добавление промокода -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Добавить новый промокод</h3>
                
                <?php if (isset($success)): ?>
                    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="action" value="add_promo">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Промокод(ы) -  от 5 до 10 символов
                            <span class="text-xs text-gray-500 font-normal ml-2">
                                (можно несколько через запятую, пробел или с новой строки)
                            </span>
                        </label>
                        <textarea name="code" required rows="5"
                               placeholder="Например:&#10;REPRO123&#10;CODE0001, NEWCODE2&#10;TEST0003 TEST0004&#10;..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase resize-none"></textarea>
                        <p class="text-xs text-gray-500 mt-1">
                            Разделители: запятая, пробел, точка с запятой или новая строка
                        </p>
                    </div>
                    <div class="flex justify-end mt-4">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Добавить
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Поиск и фильтры -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Поиск и фильтры</h3>
                
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Поиск</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>"
                               placeholder="Промокод, пользователь, email, город..."
                               class="w-full h-10 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Статус</label>
                        <select name="status" class="w-full h-10 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Все статусы</option>
                            <option value="registered" <?= $statusFilter === 'registered' ? 'selected' : '' ?>>Зарегистрированные</option>
                            <option value="unregistered" <?= $statusFilter === 'unregistered' ? 'selected' : '' ?>>Незарегистрированные</option>
                        </select>
                    </div>
                    
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-search mr-2"></i>Поиск
                        </button>
                        <a href="/promo_codes.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700">
                            <i class="fas fa-times mr-2"></i>Сбросить
                        </a>
                    </div>
                </form>
                
                <?php if (!empty($searchQuery) || !empty($statusFilter)): ?>
                <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        Найдено записей: <?= count($promoCodes) ?>
                        <?php if (!empty($searchQuery)): ?>
                            по запросу "<?= htmlspecialchars($searchQuery) ?>"
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Список промокодов -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Список промокодов (<?= count($promoCodes) ?>)
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Промокод</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Врач</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Город</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Продажи</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата создания</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($promoCodes as $promo): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($promo['code']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $promo['status'] === 'registered' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                        <?= $promo['status'] === 'registered' ? 'Зарегистрирован' : 'Не зарегистрирован' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if (!empty($promo['doctor_id']) && !empty($promo['full_name'])): ?>
                                        <div class="flex items-center space-x-2">
                                            <a href="/user_profile.php?id=<?= $promo['doctor_id'] ?>"
                                               class="text-blue-600 hover:text-blue-800 hover:underline">
                                                <?= htmlspecialchars($promo['full_name']) ?>
                                            </a>
                                            <form method="POST" class="inline" onsubmit="return confirm('Вы уверены, что хотите отвязать пользователя от промокода и удалить его из системы? Это действие нельзя отменить.')">
                                                <input type="hidden" name="action" value="delete_user_from_promo">
                                                <input type="hidden" name="promo_id" value="<?= $promo['id'] ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800" title="Отвязать и удалить пользователя">
                                                    <i class="fas fa-user-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($promo['city'] ?? '-') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-blue-600">
                                    <?= number_format($promo['total_sales'], 0, '.', ' ') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('d.m.Y H:i', strtotime($promo['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="flex space-x-2">
                                        <?php if ($promo['status'] === 'unregistered'): ?>
                                        <button onclick="deletePromo(<?= $promo['id'] ?>, '<?= htmlspecialchars($promo['code']) ?>')" 
                                                class="text-red-600 hover:text-red-800" title="Удалить">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (empty($promoCodes)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-ticket-alt text-4xl mb-4"></i>
                    <p>Промокоды не найдены</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Функция копирования удалена - иконка копирования убрана из интерфейса
        
        function copyToClipboard_removed(text) {
            // Старая функция - больше не используется
            navigator.clipboard.writeText(text).then(function() {
                showNotification('Промокод ' + text + ' скопирован в буфер обмена!', 'success');
            }, function(err) {
                console.error('Ошибка копирования: ', err);
                // Fallback для старых браузеров
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showNotification('Промокод ' + text + ' скопирован в буфер обмена!', 'success');
            });
        }
        
        function showNotification(message, type) {
            // Создаем уведомление
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg border-l-4 transform transition-all duration-300 ease-in-out';
            
            // Стили в зависимости от типа
            if (type === 'success') {
                notification.className += ' bg-green-50 text-green-800 border-green-400';
                notification.innerHTML = '<div class="flex items-center"><i class="fas fa-check-circle text-xl mr-3"></i><span>' + message + '</span></div>';
            } else if (type === 'error') {
                notification.className += ' bg-red-50 text-red-800 border-red-400';
                notification.innerHTML = '<div class="flex items-center"><i class="fas fa-exclamation-circle text-xl mr-3"></i><span>' + message + '</span></div>';
            }
            
            // Добавляем на страницу
            document.body.appendChild(notification);
            
            // Анимация появления
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Автоматическое скрытие через 3 секунды
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
        
        function deletePromo(id, code) {
            if (confirm('Вы уверены, что хотите удалить промокод ' + code + '?')) {
                // Создаем форму для отправки POST запроса
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_promo';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'promo_id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
    </script>
</body>
</html>
