<?php

// Проверка авторизации администратора
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin.php');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use ReproCRM\Config\Config;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

Config::load();
$_ENV['DB_TYPE'] = 'sqlite';

// Подключаемся к базе данных
$dbPath = __DIR__ . '/database/reprocrm.db';
$pdo = new PDO("sqlite:" . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$exportType = $_GET['type'] ?? 'promo_codes';

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    switch ($exportType) {
        case 'promo_codes':
            exportPromoCodes($pdo, $sheet);
            $filename = 'promo_codes_' . date('Y-m-d_H-i-s') . '.xlsx';
            break;
            
        case 'doctors':
            exportDoctors($pdo, $sheet, $_GET);
            $filename = 'users_report_' . date('Y-m-d_H-i-s') . '.xlsx';
            break;
            
        case 'sales':
            exportSales($pdo, $sheet);
            $filename = 'sales_report_' . date('Y-m-d_H-i-s') . '.xlsx';
            break;
            
        default:
            throw new Exception('Неверный тип экспорта');
    }
    
    // Настройка заголовков для скачивания
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
} catch (Exception $e) {
    http_response_code(500);
    echo "Ошибка экспорта: " . $e->getMessage();
}

function exportPromoCodes($pdo, $sheet) {
    $sheet->setTitle('Промокоды');
    
    // Заголовки
    $headers = ['ID', 'Промокод', 'Статус', 'Врач', 'Email', 'Город', 'Продажи', 'Дата создания'];
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col++, 1, $header);
    }
    
    // Стилизация заголовков
    $sheet->getStyle('A1:H1')->getFont()->setBold(true);
    $sheet->getStyle('A1:H1')->getFill()
          ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
          ->getStartColor()->setARGB('FFE0E0E0');
    
    // Данные
    $stmt = $pdo->query("
        SELECT 
            pc.id,
            pc.code,
            pc.status,
            d.full_name,
            d.email,
            d.city,
            COUNT(s.id) as sales_count,
            pc.created_at
        FROM promo_codes pc
        LEFT JOIN doctors d ON d.promo_code_id = pc.id
        LEFT JOIN sales s ON s.promo_code_id = pc.id
        GROUP BY pc.id, pc.code, pc.status, d.full_name, d.email, d.city, pc.created_at
        ORDER BY pc.created_at DESC
    ");
    
    $row = 2;
    while ($data = $stmt->fetch()) {
        $col = 1;
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['id']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['code']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['status'] === 'registered' ? 'Зарегистрирован' : 'Не зарегистрирован');
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['full_name'] ?? '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['email'] ?? '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['city'] ?? '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['sales_count']);
        $sheet->setCellValueByColumnAndRow($col++, $row, date('d.m.Y H:i', strtotime($data['created_at'])));
        $row++;
    }
    
    // Автоширина колонок
    foreach (range('A', 'H') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
}

function exportDoctors($pdo, $sheet, $filters = []) {
    $sheet->setTitle('Пользователи');
    
    // Получаем параметры фильтрации
    $filterCity = $filters['city'] ?? '';
    $filterStatus = $filters['status'] ?? '';
    $sortBy = $filters['sort'] ?? 'created_at';
    $sortOrder = $filters['order'] ?? 'desc';
    
    // Валидация параметров сортировки
    $allowedSorts = ['total_sales', 'full_name', 'city', 'created_at'];
    $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'created_at';
    $sortOrder = in_array($sortOrder, ['asc', 'desc']) ? $sortOrder : 'desc';
    
    // Формируем условия WHERE для первого запроса (пользователи)
    $whereConditions1 = [];
    $params1 = [];
    
    if (!empty($filterCity)) {
        $whereConditions1[] = "u.city = ?";
        $params1[] = $filterCity;
    }
    
    if (!empty($filterStatus)) {
        if ($filterStatus === 'registered') {
            $whereConditions1[] = "u.promo_code_id IS NOT NULL";
        } elseif ($filterStatus === 'unregistered') {
            $whereConditions1[] = "u.promo_code_id IS NULL";
        }
    }
    
    $whereClause1 = !empty($whereConditions1) ? 'WHERE ' . implode(' AND ', $whereConditions1) : '';
    
    // Формируем условия для второго запроса (промокоды без пользователей)
    $whereConditions2 = ["u.id IS NULL"];
    $params2 = [];
    
    // Определяем, нужно ли включать промокоды без пользователей
    $includeUnregisteredPromos = false;
    
    // Если фильтр по статусу "registered", то промокоды без пользователей не включаем
    if ($filterStatus === 'registered') {
        $includeUnregisteredPromos = false;
    } 
    // Если фильтр по статусу "unregistered" - всегда включаем промокоды без пользователей
    // (они тоже имеют статус "незарегистрированные")
    elseif ($filterStatus === 'unregistered') {
        // Включаем промокоды без пользователей только если не установлен фильтр по городу
        // (у промокодов без пользователей нет города)
        if (empty($filterCity)) {
            $includeUnregisteredPromos = true;
        }
    }
    // Если фильтр не установлен, включаем промокоды без пользователей только если не установлен фильтр по городу
    else {
        if (empty($filterCity)) {
            $includeUnregisteredPromos = true;
        }
    }
    
    $whereClause2 = 'WHERE ' . implode(' AND ', $whereConditions2);
    
    // Формируем SQL запрос
    $sql = "
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.city,
            pc.code as promo_code,
            CASE 
                WHEN u.promo_code_id IS NOT NULL THEN 'Зарегистрирован'
                ELSE 'Не зарегистрирован'
            END as registration_status,
            COALESCE(pc.total_sales, 0) as total_sales,
            COALESCE(u.created_at, pc.created_at) as created_at
        FROM users u
        LEFT JOIN promo_codes pc ON u.promo_code_id = pc.id
        {$whereClause1}
    ";
    
    if ($includeUnregisteredPromos) {
        $sql .= "
        UNION
        
        SELECT 
            NULL as id,
            NULL as full_name,
            NULL as email,
            NULL as city,
            pc.code as promo_code,
            'Не зарегистрирован' as registration_status,
            COALESCE(pc.total_sales, 0) as total_sales,
            pc.created_at
        FROM promo_codes pc
        LEFT JOIN users u ON u.promo_code_id = pc.id
        {$whereClause2}
        ";
    }
    
    // Маппинг полей сортировки
    $sortFieldMap = [
        'total_sales' => 'total_sales',
        'full_name' => 'full_name',
        'city' => 'city',
        'created_at' => 'created_at'
    ];
    $sortField = $sortFieldMap[$sortBy] ?? 'created_at';
    
    $sql .= " ORDER BY {$sortField} {$sortOrder}";
    
    // Заголовки согласно требованиям
    $headers = ['ПОЛЬЗОВАТЕЛЬ', 'EMAIL', 'ГОРОД', 'ПРОМОКОД', 'СТАТУС', 'ПРОДАЖИ', 'ДАТА РЕГИСТРАЦИИ'];
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col++, 1, $header);
    }
    
    // Стилизация заголовков
    $sheet->getStyle('A1:G1')->getFont()->setBold(true);
    $sheet->getStyle('A1:G1')->getFill()
          ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
          ->getStartColor()->setARGB('FFE0E0E0');
    
    // Выполняем запрос
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params1, $params2));
    
    $row = 2;
    while ($data = $stmt->fetch()) {
        $col = 1;
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['full_name'] ?? '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['email'] ?? '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['city'] ?? '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['promo_code'] ?? '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['registration_status']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['total_sales']);
        $sheet->setCellValueByColumnAndRow($col++, $row, date('d.m.Y', strtotime($data['created_at'])));
        $row++;
    }
    
    // Автоширина колонок
    foreach (range('A', 'G') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
}

function exportSales($pdo, $sheet) {
    $sheet->setTitle('Продажи');
    
    // Заголовки
    $headers = ['ID', 'Промокод', 'Врач', 'Продукт', 'Количество', 'Дата продажи', 'Дата добавления'];
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col++, 1, $header);
    }
    
    // Стилизация заголовков
    $sheet->getStyle('A1:G1')->getFont()->setBold(true);
    $sheet->getStyle('A1:G1')->getFill()
          ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
          ->getStartColor()->setARGB('FFE0E0E0');
    
    // Данные
    $stmt = $pdo->query("
        SELECT 
            s.id,
            pc.code as promo_code,
            d.full_name as doctor_name,
            s.product_name,
            s.quantity,
            s.sale_date,
            s.created_at
        FROM sales s
        JOIN promo_codes pc ON s.promo_code_id = pc.id
        LEFT JOIN doctors d ON d.promo_code_id = pc.id
        ORDER BY s.created_at DESC
    ");
    
    $row = 2;
    while ($data = $stmt->fetch()) {
        $col = 1;
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['id']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['promo_code']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['doctor_name'] ?? '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['product_name']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['quantity']);
        $sheet->setCellValueByColumnAndRow($col++, $row, date('d.m.Y', strtotime($data['sale_date'])));
        $sheet->setCellValueByColumnAndRow($col++, $row, date('d.m.Y H:i', strtotime($data['created_at'])));
        $row++;
    }
    
    // Автоширина колонок
    foreach (range('A', 'G') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
}
?>
