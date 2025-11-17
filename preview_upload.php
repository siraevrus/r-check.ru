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
    // Получаем информацию о загрузке
    $stmt = $pdo->prepare("SELECT * FROM upload_history WHERE id = ?");
    $stmt->execute([$uploadId]);
    $upload = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$upload) {
        echo json_encode(['success' => false, 'error' => 'Загрузка не найдена']);
        exit;
    }
    
    // Парсим период из error_log (формат: "Начало обработки файла - период: YYYY-MM-DD - YYYY-MM-DD")
    $periodFrom = null;
    $periodTo = null;
    if (preg_match('/период:\s*(\d{4}-\d{2}-\d{2})\s*-\s*(\d{4}-\d{2}-\d{2})/i', $upload['error_log'] ?? '', $matches)) {
        $periodFrom = $matches[1];
        $periodTo = $matches[2];
    }
    
    // Получаем продажи, созданные в момент загрузки или сразу после
    // Используем created_at продаж для связи с created_at загрузки
    // Также фильтруем по периоду sale_date, если период указан
    $uploadCreatedAt = $upload['created_at'];
    $uploadCreatedAtEnd = date('Y-m-d H:i:s', strtotime($uploadCreatedAt . ' +5 minutes')); // +5 минут для учета времени обработки
    
    $sql = "
        SELECT 
            pc.code as promo_code,
            s.product_name as product,
            s.quantity,
            s.sale_date,
            ? as upload_date,
            ? as description
        FROM sales s
        JOIN promo_codes pc ON s.promo_code_id = pc.id
        WHERE s.created_at >= ? AND s.created_at <= ?
    ";
    
    $params = [
        $upload['created_at'],
        $upload['error_log'] ?? '',
        $uploadCreatedAt,
        $uploadCreatedAtEnd
    ];
    
    // Если период указан, дополнительно фильтруем по sale_date
    if ($periodFrom && $periodTo) {
        $sql .= " AND s.sale_date >= ? AND s.sale_date <= ?";
        $params[] = $periodFrom;
        $params[] = $periodTo;
    }
    
    $sql .= " ORDER BY s.created_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $salesData
    ]);
    
} catch (Exception $e) {
    error_log("Ошибка получения данных загрузки: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка при получении данных: ' . $e->getMessage()]);
}


