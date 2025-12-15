<?php

// Проверка авторизации администратора
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
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

// Получаем данные из POST запроса
$input = json_decode(file_get_contents('php://input'), true);
$uploadId = $input['upload_id'] ?? null;

if (!$uploadId) {
    echo json_encode(['success' => false, 'error' => 'ID загрузки не указан']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Получаем информацию о загрузке
    $stmt = $pdo->prepare("SELECT * FROM upload_history WHERE id = ?");
    $stmt->execute([$uploadId]);
    $upload = $stmt->fetch();
    
    if (!$upload) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Загрузка не найдена']);
        exit;
    }
    
    // Парсим период из error_log (формат: "период: YYYY-MM-DD - YYYY-MM-DD")
    $periodFrom = null;
    $periodTo = null;
    $errorLog = $upload['error_log'] ?? '';
    
    // Пробуем разные варианты формата периода
    if (preg_match('/период:\s*(\d{4}-\d{2}-\d{2})\s*-\s*(\d{4}-\d{2}-\d{2})/i', $errorLog, $matches)) {
        $periodFrom = $matches[1];
        $periodTo = $matches[2];
    } elseif (preg_match('/(\d{4}-\d{2}-\d{2})\s*-\s*(\d{4}-\d{2}-\d{2})/i', $errorLog, $matches)) {
        // Альтернативный формат без слова "период"
        $periodFrom = $matches[1];
        $periodTo = $matches[2];
    }
    
    // Формируем SQL запрос для получения продаж, связанных с этой загрузкой
    $sql = "
        SELECT s.*, pc.code as promo_code
        FROM sales s
        JOIN promo_codes pc ON s.promo_code_id = pc.id
        WHERE 1=1
    ";
    $params = [];
    
    // Если период указан, используем его как основной критерий поиска
    if ($periodFrom && $periodTo) {
        $sql .= " AND s.sale_date >= ? AND s.sale_date <= ?";
        $params[] = $periodFrom;
        $params[] = $periodTo;
    } else {
        // Если период не указан, используем временное окно (30 минут от created_at загрузки)
        // для учета времени обработки больших файлов, особенно XLSX
        $uploadCreatedAt = $upload['created_at'];
        $uploadCreatedAtEnd = date('Y-m-d H:i:s', strtotime($uploadCreatedAt . ' +30 minutes'));
        $sql .= " AND s.created_at >= ? AND s.created_at <= ?";
        $params[] = $uploadCreatedAt;
        $params[] = $uploadCreatedAtEnd;
    }
    
    $sql .= " ORDER BY s.created_at DESC";
    
    // Логируем для отладки
    error_log("Delete upload ID {$uploadId}: periodFrom={$periodFrom}, periodTo={$periodTo}, error_log=" . substr($errorLog, 0, 100));
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $salesToDelete = $stmt->fetchAll();
    
    // Логируем количество найденных записей
    $foundCount = count($salesToDelete);
    error_log("Delete upload ID {$uploadId}: найдено записей для удаления: {$foundCount}");
    
    // Защита от случайного удаления всех данных
    // Если период не найден и найдено слишком много записей (>1000), отменяем удаление
    if (!$periodFrom || !$periodTo) {
        if ($foundCount > 1000) {
            $pdo->rollBack();
            error_log("Delete upload ID {$uploadId}: ОТМЕНЕНО - период не найден и найдено слишком много записей ({$foundCount})");
            echo json_encode([
                'success' => false, 
                'error' => 'Не удалось определить период загрузки. Удаление отменено для защиты данных. Найдено записей: ' . $foundCount
            ]);
            exit;
        }
    }
    
    $deletedCount = 0;
    $deletedSales = 0;
    
    // Удаляем продажи и обновляем total_sales в promo_codes
    $promoCodeUpdates = []; // Массив для накопления изменений по промокодам
    
    foreach ($salesToDelete as $sale) {
        $stmt = $pdo->prepare("DELETE FROM sales WHERE id = ?");
        if ($stmt->execute([$sale['id']])) {
            $deletedCount++;
            $deletedSales += $sale['quantity'];
            
            // Накапливаем изменения для обновления total_sales
            $promoCodeId = $sale['promo_code_id'];
            if (!isset($promoCodeUpdates[$promoCodeId])) {
                $promoCodeUpdates[$promoCodeId] = 0;
            }
            $promoCodeUpdates[$promoCodeId] += $sale['quantity'];
        }
    }
    
    // Обновляем total_sales для каждого промокода
    foreach ($promoCodeUpdates as $promoCodeId => $quantityToSubtract) {
        $stmt = $pdo->prepare("UPDATE promo_codes SET total_sales = total_sales - ?, updated_at = datetime('now') WHERE id = ?");
        $stmt->execute([$quantityToSubtract, $promoCodeId]);
    }
    
    // Удаляем запись из истории загрузок
    $stmt = $pdo->prepare("DELETE FROM upload_history WHERE id = ?");
    $stmt->execute([$uploadId]);
    
    $pdo->commit();
    
    // Логирование
    error_log("Загрузка удалена: ID $uploadId, файл {$upload['filename']}, удалено записей: $deletedCount, продаж: $deletedSales");
    
    echo json_encode([
        'success' => true, 
        'message' => "Загрузка удалена. Удалено записей: $deletedCount, продаж: $deletedSales",
        'deleted_count' => $deletedCount,
        'deleted_sales' => $deletedSales
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Ошибка удаления загрузки: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка при удалении: ' . $e->getMessage()]);
}
