<?php
// Включаем отображение ошибок для диагностики
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Проверка авторизации администратора
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin.php');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use ReproCRM\Config\Config;

try {
    Config::load();
    $_ENV['DB_TYPE'] = 'sqlite';

    // Подключаемся к базе данных
    $dbPath = __DIR__ . '/database/reprocrm.db';
    if (!file_exists($dbPath)) {
        throw new Exception('База данных не найдена: ' . $dbPath);
    }
    
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Получаем ID врача из параметров
    $doctorId = (int)($_GET['id'] ?? 0);

    if ($doctorId <= 0) {
        header('Location: /users_report.php');
        exit;
    }

    // Обработка удаления списаний
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_deduction') {
        $deductionId = (int)($_POST['deduction_id'] ?? 0);
        
        if ($deductionId <= 0) {
            $message = 'Неверный ID списания';
            $messageType = 'error';
        } else {
            try {
                // Проверяем, что списание принадлежит этому пользователю
                $stmt = $pdo->prepare("
                    SELECT d.id, d.amount 
                    FROM deductions d
                    WHERE d.id = ? AND d.user_id = ?
                ");
                $stmt->execute([$deductionId, $doctorId]);
                $deduction = $stmt->fetch();
                
                if (!$deduction) {
                    $message = 'Списание не найдено';
                    $messageType = 'error';
                } else {
                    // Удаляем списание
                    $stmt = $pdo->prepare("DELETE FROM deductions WHERE id = ?");
                    $stmt->execute([$deductionId]);
                    
                    $message = "Списание на сумму {$deduction['amount']} успешно удалено";
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                $message = 'Ошибка при удалении списания: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
        
        // Перенаправляем чтобы обновить данные
        header("Location: /user_profile.php?id=$doctorId&message=" . urlencode($message) . "&type=" . urlencode($messageType));
        exit;
    }

    // Обработка списаний
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deduct') {
        $deductAmount = (int)($_POST['deduct_amount'] ?? 0);
        
        if ($deductAmount <= 0) {
            $message = 'Сумма списания должна быть больше 0';
            $messageType = 'error';
        } else {
            try {
                // Получаем общую сумму продаж пользователя
                $stmt = $pdo->prepare("
                    SELECT pc.total_sales
                    FROM users u
                    JOIN promo_codes pc ON u.promo_code_id = pc.id
                    WHERE u.id = ?
                ");
                $stmt->execute([$doctorId]);
                $userData = $stmt->fetch();
                
                if (!$userData) {
                    $message = 'Пользователь не найден';
                    $messageType = 'error';
                } else {
                    $totalSales = $userData['total_sales'];
                    
                    // Получаем общую сумму уже списанного
                    $stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(amount), 0) as total_deducted
                        FROM deductions
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$doctorId]);
                    $deductionData = $stmt->fetch();
                    $totalDeducted = $deductionData['total_deducted'];
                    
                    // Проверяем, можно ли списать
                    $remainingBalance = $totalSales - $totalDeducted;
                    
                    if ($deductAmount > $remainingBalance) {
                        $message = "Нельзя списать так как общая сумма больше количества продаж пользователя. Доступно для списания: {$remainingBalance}";
                        $messageType = 'error';
                    } else {
                        // Выполняем списание
                        $stmt = $pdo->prepare("
                            INSERT INTO deductions (user_id, amount, deduction_date, created_at)
                            VALUES (?, ?, datetime('now'), datetime('now'))
                        ");
                        $stmt->execute([$doctorId, $deductAmount]);
                        
                        $message = "Списание на сумму {$deductAmount} успешно выполнено";
                        $messageType = 'success';
                    }
                }
            } catch (Exception $e) {
                $message = 'Ошибка при списании: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
        
        // Перенаправляем чтобы обновить данные
        header("Location: /user_profile.php?id=$doctorId&message=" . urlencode($message) . "&type=" . urlencode($messageType));
        exit;
    }

    // Обработка сообщений
    $message = '';
    $messageType = '';

    // Получаем сообщение из URL параметров
    if (isset($_GET['message'])) {
        $message = $_GET['message'];
        $messageType = $_GET['type'] ?? 'info';
    }

    // Обновляем статистику пользователя после возможного обнуления продаж
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.full_name,
            u.email,
            u.city,
            u.phone,
            u.created_at,
            pc.code as promo_code,
            pc.total_sales,
            COUNT(s.id) as sales_records_count,
            COALESCE(SUM(s.quantity), 0) as total_quantity
        FROM users u
        LEFT JOIN promo_codes pc ON u.promo_code_id = pc.id
        LEFT JOIN sales s ON s.promo_code_id = pc.id
        WHERE u.id = ?
        GROUP BY u.id, u.full_name, u.email, u.city, u.phone, u.created_at, pc.code, pc.total_sales
    ");
    $stmt->execute([$doctorId]);
    $doctor = $stmt->fetch();

    // Проверка существования пользователя (данные уже получены выше)
    if (!$doctor) {
        header('Location: /users_report.php');
        exit;
    }

    // Получаем данные о списаниях пользователя
    $stmt = $pdo->prepare("
        SELECT 
            d.id,
            d.amount,
            d.deduction_date,
            d.created_at
        FROM deductions d
        WHERE d.user_id = ?
        ORDER BY d.deduction_date DESC
    ");
    $stmt->execute([$doctorId]);
    $deductions = $stmt->fetchAll();

    // Вычисляем общую сумму списаний
    $totalDeducted = 0;
    foreach ($deductions as $deduction) {
        $totalDeducted += $deduction['amount'];
    }

    // Вычисляем остаток (общие продажи минус все списания)
    $remainingBalance = ($doctor['total_sales'] ?? 0) - $totalDeducted;

    // Получаем информацию о периодах загрузки данных для пользователя
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            uh.created_at as upload_date,
            uh.filename,
            uh.status
        FROM upload_history uh
        INNER JOIN sales s ON s.promo_code_id IN (
            SELECT promo_code_id FROM users WHERE id = ?
        )
        ORDER BY uh.created_at DESC
    ");
    $stmt->execute([$doctorId]);
    $uploadPeriods = $stmt->fetchAll();

    // Получаем продажи пользователя, сгруппированные по периодам загрузки и продуктам для сводной таблицы
    $stmt = $pdo->prepare("
        SELECT
            uh.created_at as upload_date,
            s.product_name,
            SUM(s.quantity) as total_quantity,
            uh.filename
        FROM sales s
        JOIN promo_codes pc ON s.promo_code_id = pc.id
        JOIN users u ON u.promo_code_id = pc.id
        LEFT JOIN upload_history uh ON s.created_at >= uh.created_at
        WHERE u.id = ?
        GROUP BY uh.id, uh.created_at, s.product_name, uh.filename
        ORDER BY uh.created_at DESC, s.product_name ASC
    ");
    $stmt->execute([$doctorId]);
    $salesData = $stmt->fetchAll();

    // Формируем сводную таблицу по периодам загрузки
    $pivotData = [];
    $products = [];
    $periods = [];

    foreach ($salesData as $sale) {
        $periodKey = $sale['upload_date'] ?: 'unknown';
        $product = $sale['product_name'];
        $quantity = $sale['total_quantity'];
        
        if (!in_array($periodKey, $periods)) {
            $periods[] = $periodKey;
        }
        if (!in_array($product, $products)) {
            $products[] = $product;
        }
        
        $pivotData[$periodKey][$product] = $quantity;
    }

    // Создаем массив для хранения информации о периодах
    $periodInfo = [];
    foreach ($salesData as $sale) {
        $periodKey = $sale['upload_date'] ?: 'unknown';
        if (!isset($periodInfo[$periodKey])) {
            $periodInfo[$periodKey] = [
                'upload_date' => $sale['upload_date'],
                'filename' => $sale['filename']
            ];
        }
    }

    // Сортируем продукты
    sort($products);
    // Периоды уже отсортированы по upload_date DESC в SQL запросе

} catch (Exception $e) {
    // В случае ошибки показываем её пользователю
    die("Ошибка: " . $e->getMessage() . "<br>Файл: " . $e->getFile() . "<br>Строка: " . $e->getLine());
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль врача - <?= htmlspecialchars($doctor['full_name']) ?> - Система </title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
            animation: fadeIn 0.3s;
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: #fefefe;
            padding: 0;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 800px; /* Увеличиваем размер в 2 раза */
            animation: slideIn 0.3s;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100">
    <?php include 'admin_navigation.php'; ?>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Сообщения -->
        <?php if (!empty($message)): ?>
        <div class="mb-6">
            <div class="rounded-md p-4 <?= $messageType === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' ?>">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <?php if ($messageType === 'success'): ?>
                            <i class="fas fa-check-circle text-green-400"></i>
                        <?php else: ?>
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        <?php endif; ?>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium <?= $messageType === 'success' ? 'text-green-800' : 'text-red-800' ?>">
                            <?= htmlspecialchars($message) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Кнопка назад -->
        <div class="mb-6">
            <a href="/users_report.php" class="inline-flex items-center text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-2"></i>
                Назад к списку пользователей
            </a>
        </div>

        <!-- Информация о враче -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">
                        <?= htmlspecialchars($doctor['full_name']) ?>
                    </h1>
                    <p class="text-gray-600">
                        ID пользователя: <?= $doctor['id'] ?>
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Email</h3>
                        <p class="mt-1 text-lg text-gray-900">
                            <i class="fas fa-envelope mr-2 text-gray-400"></i>
                            <?= htmlspecialchars($doctor['email']) ?>
                        </p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Город</h3>
                        <p class="mt-1 text-lg text-gray-900">
                            <i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>
                            <?= htmlspecialchars($doctor['city']) ?>
                        </p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Телефон</h3>
                        <p class="mt-1 text-lg text-gray-900">
                            <i class="fas fa-phone mr-2 text-gray-400"></i>
                            <?= htmlspecialchars($doctor['phone'] ?? 'Не указан') ?>
                        </p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Промокод</h3>
                        <p class="mt-1 text-lg text-gray-900 font-mono">
                            <i class="fas fa-ticket-alt mr-2 text-gray-400"></i>
                            <?= htmlspecialchars($doctor['promo_code'] ?? 'Не назначен') ?>
                        </p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Дата регистрации</h3>
                        <p class="mt-1 text-lg text-gray-900">
                            <i class="fas fa-calendar-plus mr-2 text-gray-400"></i>
                            <?= date('d.m.Y H:i', strtotime($doctor['created_at'])) ?>
                        </p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Всего продаж</h3>
                        <p class="mt-1 text-lg text-gray-900 font-bold">
                            <i class="fas fa-chart-line mr-2 text-green-500"></i>
                            <?= number_format($doctor['total_sales'] ?? 0, 0, ',', ' ') ?> шт.
                        </p>
                    </div>

                </div>

            </div>
        </div>

        <!-- Таблица продаж -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                    Реализация
                </h2>


                <?php if (empty($salesData)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-shopping-cart text-4xl mb-4"></i>
                    <p>У этого пользователя пока нет реализации продуктов</p>
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
                                            
                                            if ($periodInfo && !empty($periodInfo['filename'])) {
                                                echo htmlspecialchars($periodInfo['filename']);
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

        <!-- Блок списаний -->
        <div class="bg-white shadow rounded-lg mt-8">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg leading-6 font-medium text-gray-900">
                        <i class="fas fa-minus-circle mr-2 text-red-500"></i>
                        Списание
                    </h2>
                    <button onclick="openDeductModal()" 
                            class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center">
                        <i class="fas fa-minus mr-2"></i>
                        Списать
                    </button>
                </div>

                <!-- Информация об остатке -->
                <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="text-center">
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Общая сумма продаж</h3>
                            <p class="mt-1 text-2xl font-bold text-green-600">
                                <?= number_format($doctor['total_sales'] ?? 0, 0, ',', ' ') ?> шт.
                            </p>
                        </div>
                        <div class="text-center">
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Списано</h3>
                            <p class="mt-1 text-2xl font-bold text-red-600">
                                <?= number_format($totalDeducted, 0, ',', ' ') ?> шт.
                            </p>
                        </div>
                        <div class="text-center">
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Остаток</h3>
                            <p class="mt-1 text-2xl font-bold <?= $remainingBalance >= 0 ? 'text-blue-600' : 'text-red-600' ?>">
                                <?= number_format($remainingBalance, 0, ',', ' ') ?> шт.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Таблица списаний -->
                <?php if (!empty($deductions)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата списания</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Сумма</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Остаток после списания</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php 
                            $runningBalance = $doctor['total_sales'] ?? 0;
                            foreach ($deductions as $deduction): 
                                $runningBalance -= $deduction['amount'];
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= date('d.m.Y H:i', strtotime($deduction['deduction_date'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-mono">
                                    -<?= number_format($deduction['amount'], 0, ',', ' ') ?> шт.
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                                    <?= number_format($runningBalance, 0, ',', ' ') ?> шт.
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Вы уверены, что хотите удалить это списание?')">
                                        <input type="hidden" name="action" value="delete_deduction">
                                        <input type="hidden" name="deduction_id" value="<?= $deduction['id'] ?>">
                                        <button type="submit" 
                                                class="text-red-600 hover:text-red-800 hover:bg-red-50 px-2 py-1 rounded transition-colors"
                                                title="Удалить списание">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-minus-circle text-4xl mb-4"></i>
                    <p>Списания не производились</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Модальное окно для списания -->
    <div id="deductModal" class="modal">
        <div class="modal-content">
            <div class="bg-white rounded-lg shadow-lg">
                <div class="px-8 py-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-2xl font-medium text-gray-900">
                            <i class="fas fa-minus-circle mr-3 text-red-500 text-2xl"></i>
                            Списание средств
                        </h3>
                        <button onclick="closeDeductModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                </div>
                <div class="px-8 py-6">
                    <form id="deductForm" method="POST">
                        <input type="hidden" name="action" value="deduct">
                        
                        <div class="mb-6">
                            <label class="block text-lg font-medium text-gray-700 mb-3">
                                Сумма для списания
                            </label>
                            <input type="number" 
                                   name="deduct_amount" 
                                   id="deductAmount"
                                   min="1" 
                                   max="<?= max(0, $remainingBalance) ?>"
                                   required
                                   class="w-full px-4 py-3 text-lg border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                            <p class="text-sm text-gray-500 mt-2">
                                Доступно для списания: <?= number_format($remainingBalance, 0, ',', ' ') ?> шт.
                            </p>
                        </div>
                        
                        <div class="flex justify-end space-x-4">
                            <button type="button" 
                                    onclick="closeDeductModal()"
                                    class="px-6 py-3 text-lg border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                Отмена
                            </button>
                            <button type="submit" 
                                    class="px-6 py-3 text-lg bg-red-600 text-white rounded-lg hover:bg-red-700">
                                Списать
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openDeductModal() {
            const modal = document.getElementById('deductModal');
            modal.style.display = 'flex';
            modal.classList.add('active');
            document.getElementById('deductAmount').focus();
        }
        
        function closeDeductModal() {
            const modal = document.getElementById('deductModal');
            modal.style.display = 'none';
            modal.classList.remove('active');
            document.getElementById('deductForm').reset();
        }
        
        // Закрытие модального окна при клике вне его
        document.getElementById('deductModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeductModal();
            }
        });
        
        // Закрытие модального окна при нажатии Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeductModal();
            }
        });
    </script>
</body>
</html>
