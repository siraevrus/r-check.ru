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
    // Получаем продажи, связанные с конкретной загрузкой через upload_period_id
    $stmt = $pdo->prepare("
        SELECT 
            pc.code as promo_code,
            s.product_name as product,
            s.quantity,
            s.sale_date,
            uh.created_at as upload_date,
            uh.error_log as description
        FROM sales s
        JOIN promo_codes pc ON s.promo_code_id = pc.id
        JOIN upload_history uh ON s.upload_period_id = uh.id
        WHERE uh.id = ?
        ORDER BY s.created_at ASC
    ");
    $stmt->execute([$uploadId]);
    $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $salesData
    ]);
    
} catch (Exception $e) {
    error_log("Ошибка получения данных загрузки: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка при получении данных: ' . $e->getMessage()]);
}


