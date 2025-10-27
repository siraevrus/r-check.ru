<?php

// Проверка авторизации администратора
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin.php');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use ReproCRM\Config\Config;
use ReproCRM\Utils\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

Config::load();
$_ENV['DB_TYPE'] = 'sqlite';

// Подключаемся к базе данных
$dbPath = __DIR__ . '/database/reprocrm.db';
$pdo = new PDO("sqlite:" . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Проверяем, есть ли данные в сессии (после POST-редиректа)
$message = $_SESSION['upload_message'] ?? '';
$messageType = $_SESSION['upload_message_type'] ?? '';
$uploadedData = $_SESSION['upload_data'] ?? [];
$errorDetails = $_SESSION['upload_errors'] ?? [];

// Отладочная информация
error_log("file_upload.php loaded - Message: $message, Type: $messageType, Data count: " . count($uploadedData));

// Очищаем данные из сессии после их получения
unset($_SESSION['upload_message'], $_SESSION['upload_message_type'], $_SESSION['upload_data'], $_SESSION['upload_errors']);

// Логирование для отладки
$logDir = __DIR__ . '/logs';
$logFile = $logDir . '/file_upload.log';

// Создаем папку логов если её нет
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
    chmod($logDir, 0777);
}

// Создаем файл логов если его нет
if (!file_exists($logFile)) {
    touch($logFile);
    chmod($logFile, 0666);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $result = file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
    
    // Если не удалось записать в файл, пробуем записать в error_log
    if ($result === false) {
        error_log("Failed to write to log file: $logFile - $message");
    }
}

// Логирование загрузки страницы (production)
logMessage("Загрузка файлов - страница открыта");

// Обработка загрузки файла
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['file'])) {
        logMessage("Начало обработки файла: " . $_FILES['file']['name']);
        
        // Получаем период из формы
        $periodFrom = $_POST['period_from'] ?? '';
        $periodTo = $_POST['period_to'] ?? '';
        
        // Валидация периода
        if (empty($periodFrom) || empty($periodTo)) {
            $message = 'Необходимо указать период данных (дата от и дата до)';
            $messageType = 'error';
            $_SESSION['upload_message'] = $message;
            $_SESSION['upload_message_type'] = $messageType;
            header('Location: /file_upload.php');
            exit;
        }
        
        if (strtotime($periodFrom) > strtotime($periodTo)) {
            $message = 'Дата "от" не может быть больше даты "до"';
            $messageType = 'error';
            $_SESSION['upload_message'] = $message;
            $_SESSION['upload_message_type'] = $messageType;
            header('Location: /file_upload.php');
            exit;
        }

    if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = $_FILES['file']['name'];
        $fileTmpName = $_FILES['file']['tmp_name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Валидация файла
        $validator = new Validator();
        if (!$validator->validateFile($_FILES['file'])) {
            $message = implode(' ', array_merge(...array_values($validator->getErrors())));
            $messageType = 'error';
            $uploadedData = [];
            $errorDetails = [];
            
            // Сохраняем ошибку в сессию и делаем редирект
            $_SESSION['upload_message'] = $message;
            $_SESSION['upload_message_type'] = $messageType;
            $_SESSION['upload_data'] = $uploadedData;
            $_SESSION['upload_errors'] = $errorDetails;
            header('Location: /file_upload.php');
            exit;
        } else {
            try {
                // Перемещаем файл во временную директорию
                $uniqueFileName = uniqid() . '_' . $fileName;
                $uploadPath = $uploadDir . $uniqueFileName;

                if (move_uploaded_file($fileTmpName, $uploadPath)) {
                    $result = parseFile($uploadPath, $fileExtension, $pdo, $periodFrom, $periodTo);

                    if ($result['success'] && $result['upload_period_id']) {
                        // Обновляем запись в истории загрузок только если нет критичных ошибок
                        try {
                            $totalSales = $result['total_sales'] ?? 0;
                            $recordsCount = $result['processed'] ?? 0;

                            $stmt = $pdo->prepare("
                                UPDATE upload_history
                                SET rows_processed = ?, rows_success = ?, status = ?, error_log = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $recordsCount,
                                $result['added'] + $result['updated'],
                                'completed',
                                "Загружено {$result['added']} новых записей, обновлено {$result['updated']} существующих",
                                $result['upload_period_id']
                            ]);

                            logMessage("Загрузка обновлена в истории: ID " . $result['upload_period_id']);
                        } catch (Exception $e) {
                            logMessage("Ошибка обновления истории: " . $e->getMessage());
                        }
                        
                        $message = "Файл успешно обработан! Обработано записей: {$result['processed']}, Добавлено: {$result['added']}, Обновлено: {$result['updated']}, Ошибок: {$result['errors']}";
                        logMessage("Файл успешно обработан без критичных ошибок");
                        $messageType = 'success';
                        $uploadedData = $result['uploaded_data'] ?? [];
                        $errorDetails = $result['error_details'] ?? [];
                        logMessage("Файл обработан: добавлено {$result['added']}, обновлено {$result['updated']}, ошибок {$result['errors']}");
                    } else {
                        $message = 'Ошибка обработки файла: ' . $result['error'];
                        $messageType = 'error';
                        $uploadedData = [];
                        $errorDetails = [];
                        logMessage("Ошибка: " . $result['error']);
                    }

                    // Удаляем временный файл
                    unlink($uploadPath);
                } else {
                    $message = 'Ошибка загрузки файла';
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $message = 'Ошибка: ' . $e->getMessage();
                $messageType = 'error';
                logMessage("Ошибка обработки: " . $e->getMessage());
            }
        }
    } else {
        $message = 'Ошибка загрузки файла: ' . $_FILES['file']['error'];
        $messageType = 'error';
    }
    
    // Сохраняем данные в сессию и делаем редирект
    $_SESSION['upload_message'] = $message;
    $_SESSION['upload_message_type'] = $messageType;
    $_SESSION['upload_data'] = $uploadedData;
    $_SESSION['upload_errors'] = $errorDetails;
    
    // Редирект на эту же страницу (PRG pattern - Post/Redirect/Get)
    header('Location: /file_upload.php');
    exit;
    }
}

// Маппинг кодов продуктов на названия
$productCodeMapping = [
    '526804' => 'Репрорелакс гиперкортизол',
    '526805' => 'Репрорелакс гипокортизол',
    '526807' => 'Репробиом',
    '526812' => 'Репродетокси',
    '526811' => 'Репроэнерджи',
    '526806' => 'Репрометабо',
    '526808' => 'Репроэмбрио',
    '526809' => 'Репрогеном'
];

// Функция для преобразования кода продукта в название
function convertProductCodeToName($productValue) {
    // Маппинг кодов продуктов на названия (локальный для функции)
    $productCodeMapping = [
        '526804' => 'Репрорелакс гиперкортизол',
        '526805' => 'Репрорелакс гипокортизол',
        '526807' => 'Репробиом',
        '526812' => 'Репродетокси',
        '526811' => 'Репроэнерджи',
        '526806' => 'Репрометабо',
        '526808' => 'Репроэмбрио',
        '526809' => 'Репрогеном'
    ];

    // Отладочная информация
    logMessage("convertProductCodeToName вход: " . json_encode($productValue) . " (тип: " . gettype($productValue) . ")");

    // Преобразуем в строку, если это объект
    if (is_object($productValue)) {
        $productValue = (string)$productValue;
    }

    // Убираем пробелы и проверяем, является ли значение числом (кодом продукта)
    $cleanValue = trim($productValue);
    logMessage("convertProductCodeToName после trim: '{$cleanValue}' (тип: " . gettype($cleanValue) . ")");

    // Если значение является числом и есть в маппинге, заменяем на название
    logMessage("Проверка маппинга: is_numeric('{$cleanValue}') = " . (is_numeric($cleanValue) ? 'true' : 'false') . ", isset = " . (isset($productCodeMapping[$cleanValue]) ? 'true' : 'false'));
    if (is_numeric($cleanValue) && isset($productCodeMapping[$cleanValue])) {
        logMessage("Преобразован код продукта {$cleanValue} в название: " . $productCodeMapping[$cleanValue]);
        return $productCodeMapping[$cleanValue];
    }

    logMessage("Код продукта {$cleanValue} не найден в маппинге, возвращаем оригинал");
    return $cleanValue; // Возвращаем оригинальное значение, если это не код
}

// Функция парсинга файла
function parseFile($filePath, $extension, $pdo, $periodFrom, $periodTo) {
    logMessage("Парсинг файла: " . basename($filePath));
    logMessage("Расширение файла: $extension");
    logMessage("Период: $periodFrom - $periodTo");

    try {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        
        // Определяем заголовки
        $headers = [];
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $cellValue = $worksheet->getCell($col . '1')->getValue();
            $headers[$col] = trim($cellValue);
        }
        
        // Маппинг колонок (ожидаемые названия)
        $columnMapping = [
            'promo_code' => null,
            'product_name' => null,
            'quantity' => null,
            'sale_date' => null
        ];
        
        // Ищем соответствия в заголовках
        foreach ($headers as $col => $header) {
            $headerLower = mb_strtolower($header);
            
            if (strpos($headerLower, 'промокод') !== false || strpos($headerLower, 'promo') !== false) {
                $columnMapping['promo_code'] = $col;
            } elseif (strpos($headerLower, 'продукт') !== false || strpos($headerLower, 'товар') !== false || strpos($headerLower, 'product') !== false) {
                $columnMapping['product_name'] = $col;
            } elseif (strpos($headerLower, 'количество') !== false || strpos($headerLower, 'quantity') !== false || strpos($headerLower, 'кол') !== false || strpos($headerLower, 'продажи') !== false || strpos($headerLower, 'продаж') !== false) {
                $columnMapping['quantity'] = $col;
            } elseif (strpos($headerLower, 'дата') !== false || strpos($headerLower, 'date') !== false) {
                $columnMapping['sale_date'] = $col;
            }
        }
        
        $processed = 0;
        $added = 0;
        $updated = 0;
        $errors = 0;
        $totalSales = 0;
        $uploadedData = [];
        $errorDetails = [];
        
        // Создаем запись в upload_history в начале обработки
        $stmt = $pdo->prepare("
            INSERT INTO upload_history 
            (user_id, filename, file_path, error_log)
            VALUES (?, ?, ?, ?)
        ");
        $adminId = $_SESSION['admin_id'] ?? null;
        $fileName = basename($filePath);
        $description = "Начало обработки файла - период: $periodFrom - $periodTo";
        
        $stmt->execute([
            $adminId,
            $fileName,
            $filePath,
            $description
        ]);
        
        $currentUploadPeriodId = $pdo->lastInsertId();

        // Обрабатываем строки данных (начиная со 2-й строки) - только валидация, без сохранения
        for ($row = 2; $row <= $highestRow; $row++) {
            $processed++;

            try {
                $promoCode = $columnMapping['promo_code'] ? trim($worksheet->getCell($columnMapping['promo_code'] . $row)->getFormattedValue()) : '';
                $cell = $columnMapping['product_name'] ? $worksheet->getCell($columnMapping['product_name'] . $row) : null;
                $productNameRaw = $cell ? $cell->getValue() : '';
                $formattedValue = $cell ? $cell->getFormattedValue() : '';

                logMessage("Строка {$row}: сырое значение продукта: " . json_encode($productNameRaw) . " (тип: " . gettype($productNameRaw) . ")");
                logMessage("Строка {$row}: форматированное значение продукта: " . json_encode($formattedValue) . " (тип: " . gettype($formattedValue) . ")");
                $productName = convertProductCodeToName($formattedValue);
                $quantity = $columnMapping['quantity'] ? (int)$worksheet->getCell($columnMapping['quantity'] . $row)->getFormattedValue() : 1;
                $saleDate = $columnMapping['sale_date'] ? $worksheet->getCell($columnMapping['sale_date'] . $row)->getFormattedValue() : date('Y-m-d');

                // Валидация обязательных полей
                if (empty($promoCode) || empty($productName)) {
                    $errors++;
                    $errorDetails[] = [
                        'row' => $row,
                        'reason' => 'Отсутствует промокод или название продукта',
                        'promo_code' => $promoCode,
                        'product' => $productNameRaw
                    ];
                    continue;
                }

                // Преобразуем дату
                if ($saleDate instanceof DateTime) {
                    $saleDate = $saleDate->format('Y-m-d');
                } else {
                    $saleDate = date('Y-m-d', strtotime($saleDate));
                }

            } catch (Exception $e) {
                $errors++;
                $errorDetails[] = [
                    'row' => $row,
                    'reason' => $e->getMessage(),
                    'promo_code' => $promoCode ?? '',
                    'product' => $productNameRaw ?? ''
                ];
                error_log("Ошибка обработки строки {$row}: " . $e->getMessage());
            }
        }

        // Проверяем, есть ли критичные ошибки перед сохранением данных
        // Критичные ошибки: отсутствуют обязательные поля (промокод или продукт)
        $hasCriticalErrors = $errors > 0;
        logMessage("Валидация завершена: обработано {$processed} строк, ошибок {$errors}, критичных ошибок: " . ($hasCriticalErrors ? 'да' : 'нет'));

        if ($hasCriticalErrors) {
            // Удаляем созданную запись в upload_history при наличии ошибок
            $stmt = $pdo->prepare("DELETE FROM upload_history WHERE id = ?");
            $stmt->execute([$currentUploadPeriodId]);
            logMessage("Запись в истории загрузок удалена из-за критичных ошибок: ID " . $currentUploadPeriodId);

            return [
                'success' => false,
                'error' => "Файл содержит {$errors} ошибок. Данные не были загружены в систему.",
                'processed' => $processed,
                'added' => 0,
                'updated' => 0,
                'errors' => $errors,
                'total_sales' => 0,
                'upload_period_id' => null,
                'uploaded_data' => [],
                'error_details' => $errorDetails
            ];
        }

        // Если ошибок нет, очищаем старые данные и сохраняем новые
        // Очищаем старые данные за этот период (если они есть)
        // Это позволяет заменить данные за период, а не суммировать их
        $stmt = $pdo->prepare("
            DELETE FROM sales
            WHERE sale_date >= ? AND sale_date <= ?
        ");
        $stmt->execute([$periodFrom, $periodTo]);

        logMessage("Очищены старые данные за период {$periodFrom} - {$periodTo}");

        // Если ошибок нет, сохраняем данные в базу
        for ($row = 2; $row <= $highestRow; $row++) {
            try {
                $promoCode = $columnMapping['promo_code'] ? trim($worksheet->getCell($columnMapping['promo_code'] . $row)->getFormattedValue()) : '';
                $cell = $columnMapping['product_name'] ? $worksheet->getCell($columnMapping['product_name'] . $row) : null;
                $productNameRaw = $cell ? $cell->getValue() : '';
                $formattedValue = $cell ? $cell->getFormattedValue() : '';

                logMessage("Строка {$row} (сохранение): сырое значение продукта: " . json_encode($productNameRaw) . " (тип: " . gettype($productNameRaw) . ")");
                logMessage("Строка {$row} (сохранение): форматированное значение продукта: " . json_encode($formattedValue) . " (тип: " . gettype($formattedValue) . ")");
                $productName = convertProductCodeToName($formattedValue);
                $quantity = $columnMapping['quantity'] ? (int)$worksheet->getCell($columnMapping['quantity'] . $row)->getFormattedValue() : 1;
                $saleDate = $columnMapping['sale_date'] ? $worksheet->getCell($columnMapping['sale_date'] . $row)->getFormattedValue() : date('Y-m-d');

                // Преобразуем дату
                if ($saleDate instanceof DateTime) {
                    $saleDate = $saleDate->format('Y-m-d');
                } else {
                    $saleDate = date('Y-m-d', strtotime($saleDate));
                }

                // Находим промокод или создаем его, если не существует
                $stmt = $pdo->prepare("SELECT id FROM promo_codes WHERE code = ?");
                $stmt->execute([$promoCode]);
                $promoCodeId = $stmt->fetchColumn();

                if (!$promoCodeId) {
                    // Промокод не найден - создаем новый со статусом "unregistered"
                    $stmt = $pdo->prepare("INSERT INTO promo_codes (code, status, created_at, updated_at) VALUES (?, 'unregistered', datetime('now'), datetime('now'))");
                    $stmt->execute([$promoCode]);
                    $promoCodeId = $pdo->lastInsertId();
                }

                // Проверяем, есть ли уже запись с таким промокодом, продуктом и датой
                $stmt = $pdo->prepare("SELECT id, quantity FROM sales WHERE promo_code_id = ? AND product_name = ? AND sale_date = ?");
                $stmt->execute([$promoCodeId, $productName, $saleDate]);
                $existingSale = $stmt->fetch(PDO::FETCH_ASSOC);

                $saleId = null;

                if ($existingSale) {
                    // Запись существует в текущем периоде - обновляем количество (не суммируем!)
                    $quantityDifference = $quantity - $existingSale['quantity']; // Разность для обновления total_sales
                    $stmt = $pdo->prepare("UPDATE sales SET quantity = ? WHERE id = ?");
                    $stmt->execute([$quantity, $existingSale['id']]);
                    $saleId = $existingSale['id'];
                    $updated++;
                } else {
                    // Записи нет - создаем новую
                    $quantityDifference = $quantity; // Полная сумма для обновления total_sales
                    $stmt = $pdo->prepare("INSERT INTO sales (promo_code_id, product_name, sale_date, quantity, created_at) VALUES (?, ?, ?, ?, datetime('now'))");
                    $stmt->execute([$promoCodeId, $productName, $saleDate, $quantity]);
                    $saleId = $pdo->lastInsertId();
                    $added++;
                }

                // Общая сумма продаж будет пересчитана в конце обработки

                // Суммируем общую сумму продаж
                $totalSales += $quantity;

                // Сохраняем информацию о загруженных данных
                $uploadedData[] = [
                    'promo_code' => $promoCode,
                    'product' => $productName,
                    'quantity' => $quantity,
                    'date' => $saleDate,
                    'sale_id' => $saleId
                ];

            } catch (Exception $e) {
                // Эта ветка не должна выполняться, так как мы уже проверили данные выше
                $errors++;
                $errorDetails[] = [
                    'row' => $row,
                    'reason' => $e->getMessage(),
                    'promo_code' => $promoCode ?? '',
                    'product' => $productNameRaw ?? ''
                ];
                error_log("Ошибка обработки строки {$row}: " . $e->getMessage());
            }
        }
        
        // Пересчитываем общую сумму продаж для всех промокодов
        $stmt = $pdo->prepare("
            UPDATE promo_codes 
            SET total_sales = (
                SELECT COALESCE(SUM(quantity), 0) 
                FROM sales 
                WHERE sales.promo_code_id = promo_codes.id
            ),
            updated_at = datetime('now')
        ");
        $stmt->execute();
        
        logMessage("Пересчитаны общие суммы продаж для всех промокодов");
        
        return [
            'success' => true,
            'processed' => $processed,
            'added' => $added,
            'updated' => $updated,
            'errors' => $errors,
            'total_sales' => $totalSales,
            'upload_period_id' => $currentUploadPeriodId,
            'uploaded_data' => $uploadedData,
            'error_details' => $errorDetails
        ];
        
    } catch (Exception $e) {
        logMessage("Ошибка парсинга: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'processed' => $processed ?? 0,
            'added' => $added ?? 0,
            'updated' => $updated ?? 0,
            'errors' => $errors ?? 0,
            'uploaded_data' => [],
            'error_details' => []
        ];
    }
}

// Получаем историю загрузок (последние 10)

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Загрузка файлов - Система РЕПРО</title>
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
            max-width: 900px;
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
        
        <!-- Форма загрузки -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Загрузить файл</h3>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-6" id="uploadForm">
                    <!-- Период данных -->
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h4 class="font-medium text-blue-900 mb-3">
                            <i class="fas fa-calendar-alt mr-2"></i>Период данных
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Дата от <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="period_from" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Дата до <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="period_to" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <p class="text-xs text-blue-700 mt-2">
                            Укажите период, за который были совершены продажи в загружаемом файле
                        </p>
                    </div>
                    
                    <!-- Файл -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Выберите файл (CSV, XLS, XLSX)
                        </label>
                        <input type="file" name="file" required 
                               accept=".csv,.xls,.xlsx"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Максимальный размер: 10MB</p>
                    </div>
                    
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-upload mr-2"></i>Загрузить и обработать
                    </button>
                </form>
            </div>
        </div>
        
        <!-- История загрузок -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                    <i class="fas fa-history mr-2"></i>История загрузок
                </h3>
                
                <?php
                // Получаем историю загрузок
                $stmt = $pdo->prepare("
                    SELECT uh.*, a.email as admin_email
                    FROM upload_history uh 
                    LEFT JOIN admins a ON uh.user_id = a.id 
                    ORDER BY uh.created_at DESC 
                    LIMIT 20
                ");
                $stmt->execute();
                $uploadHistory = $stmt->fetchAll();
                ?>
                
                <?php if (empty($uploadHistory)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-upload text-4xl mb-4"></i>
                        <p>История загрузок пуста</p>
                        <p class="text-sm">После первой загрузки файла здесь появится информация</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($uploadHistory as $upload): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                            <div>
                                                <span class="text-sm font-medium text-gray-500">Файл:</span>
                                                <p class="text-sm text-gray-900">
                                                    <?= htmlspecialchars($upload['filename']) ?>
                                                </p>
                                            </div>
                                            <div>
                                                <span class="text-sm font-medium text-gray-500">Загружено:</span>
                                                <p class="text-sm text-gray-900">
                                                    <?= date('d.m.Y H:i', strtotime($upload['created_at'])) ?>
                                                </p>
                                            </div>
                                            <div>
                                                <span class="text-sm font-medium text-gray-500">Обработано:</span>
                                                <p class="text-sm text-gray-900 font-semibold">
                                                    <?= number_format($upload['rows_processed'] ?? 0, 0, ',', ' ') ?> строк
                                                </p>
                                            </div>
                                            <div>
                                                <span class="text-sm font-medium text-gray-500">Статус:</span>
                                                <p class="text-sm text-gray-900">
                                                    <?= htmlspecialchars($upload['status'] ?? 'processing') ?>
                                                </p>
                                            </div>
                                        </div>
                                        <?php if ($upload['file_name']): ?>
                                            <div class="mt-2">
                                                <span class="text-sm font-medium text-gray-500">Файл:</span>
                                                <span class="text-sm text-gray-700"><?= htmlspecialchars($upload['file_name']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($upload['admin_email']): ?>
                                            <div class="mt-1">
                                                <span class="text-sm font-medium text-gray-500">Загрузил:</span>
                                                <span class="text-sm text-gray-700"><?= htmlspecialchars($upload['admin_email']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-4 flex space-x-2">
                                        <button onclick="previewUpload(<?= $upload['id'] ?>)" 
                                                class="text-blue-600 hover:text-blue-800 p-2 rounded-lg hover:bg-blue-50 transition-colors"
                                                title="Просмотреть данные загрузки">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="deleteUpload(<?= $upload['id'] ?>)" 
                                                class="text-red-600 hover:text-red-800 p-2 rounded-lg hover:bg-red-50 transition-colors"
                                                title="Удалить загрузку">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Инструкции -->
        <div class="bg-white shadow rounded-lg" style="display: none;">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Инструкции по загрузке</h3>
                
                <div class="space-y-4">
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Поддерживаемые форматы:</h4>
                        <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                            <li>CSV (разделитель - запятая)</li>
                            <li>Excel XLS</li>
                            <li>Excel XLSX</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Ожидаемые колонки:</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <h5 class="font-medium text-gray-700 mb-1">Обязательные колонки:</h5>
                                <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                                    <li><strong>Промокод</strong> - код промокода (8 символов)</li>
                                    <li><strong>Продукт</strong> - название продукта</li>
                                    <li><strong>Продажи</strong> (или Количество) - количество продаж (суммируется с существующими)</li>
                                    <li><strong>Дата</strong> - дата продажи (по умолчанию: сегодня)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Пример CSV файла:</h4>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm text-gray-700">Промокод,Продукт,Дата,Продажи
TEST0001,Тестовый продукт А,2025-10-08,5
TEST0002,Тестовый продукт Б,2025-10-08,3
NEWCODE1,Новый продукт,2025-10-08,10</pre>
                        </div>
                    </div>

                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h4 class="font-medium text-blue-900 mb-2">
                            <i class="fas fa-file-alt mr-2"></i>Система логирования:
                        </h4>
                        <div class="text-sm space-y-2">
                            <p class="text-blue-800">
                                Все операции загрузки записываются в файл:
                                <code class="bg-blue-100 px-2 py-1 rounded font-mono">logs/file_upload.log</code>
                            </p>
                            <div class="flex items-start space-x-2">
                                <?php if (file_exists($logFile) && is_writable($logFile)): ?>
                                    <span class="text-green-600 font-semibold">✓ Статус:</span>
                                    <span class="text-green-700">Логирование работает</span>
                                <?php else: ?>
                                    <span class="text-orange-600 font-semibold">⚠ Статус:</span>
                                    <span class="text-orange-700">Файл логов будет создан при первой загрузке</span>
                                <?php endif; ?>
                            </div>
                            <?php if (file_exists($logFile)): ?>
                                <div class="flex items-start space-x-2">
                                    <span class="text-blue-800 font-semibold">Размер файла:</span>
                                    <span class="text-blue-700"><?= round(filesize($logFile) / 1024, 2) ?> KB</span>
                                </div>
                            <?php endif; ?>
                            <p class="text-blue-700 mt-2">
                                Логи содержат подробную информацию о каждом этапе загрузки и обработки файлов.
                            </p>
                        </div>
                    </div>
                    
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h4 class="font-medium text-blue-900 mb-2">
                            <i class="fas fa-info-circle mr-2"></i>Важно:
                        </h4>
                        <ul class="text-sm text-blue-800 space-y-1">
                            <li>• Система автоматически определит колонки по названиям</li>
                            <li>• Если промокод не существует, он будет создан со статусом "unregistered"</li>
                            <li>• <strong>Количество суммируется</strong> с существующими продажами (промокод + продукт + дата)</li>
                            <li>• Первая строка должна содержать заголовки</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно для отображения результата -->
    <div id="resultModal" class="modal">
        <div class="modal-content">
            <div id="modalHeader" class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center">
                    <i id="modalIcon" class="text-2xl mr-3"></i>
                    <h3 id="modalTitle" class="text-lg font-semibold"></h3>
                </div>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="modalBody" class="px-6 py-4 max-h-96 overflow-y-auto">
                <p id="modalMessage" class="text-gray-700 mb-4"></p>
                <div id="modalDetails"></div>
            </div>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                <button onclick="closeModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Закрыть
                </button>
            </div>
        </div>
    </div>

    <script>
        function showModal(type, message, uploadedData = [], errorDetails = []) {
            const modal = document.getElementById('resultModal');
            const modalHeader = document.getElementById('modalHeader');
            const modalIcon = document.getElementById('modalIcon');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalDetails = document.getElementById('modalDetails');

            if (type === 'success') {
                modalHeader.className = 'px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-green-50';
                modalIcon.className = 'fas fa-check-circle text-2xl mr-3 text-green-600';
                modalTitle.textContent = 'Успешная загрузка';
                modalTitle.className = 'text-lg font-semibold text-green-800';
            } else {
                modalHeader.className = 'px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-red-50';
                modalIcon.className = 'fas fa-exclamation-circle text-2xl mr-3 text-red-600';
                modalTitle.textContent = 'Ошибка загрузки';
                modalTitle.className = 'text-lg font-semibold text-red-800';
            }

            modalMessage.innerHTML = message;
            
            // Генерируем детальный отчет
            let detailsHtml = '';
            
            // Показываем загруженные данные
            if (uploadedData && uploadedData.length > 0) {
                detailsHtml += '<div class="mb-4">';
                detailsHtml += '<h4 class="font-semibold text-green-800 mb-2"><i class="fas fa-check-circle mr-2"></i>Загруженные данные:</h4>';
                detailsHtml += '<div class="overflow-x-auto">';
                detailsHtml += '<table class="min-w-full divide-y divide-gray-200 text-sm">';
                detailsHtml += '<thead class="bg-gray-50"><tr>';
                detailsHtml += '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Промокод</th>';
                detailsHtml += '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Продукт</th>';
                detailsHtml += '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Продажи</th>';
                detailsHtml += '</tr></thead><tbody class="bg-white divide-y divide-gray-200">';
                
                uploadedData.forEach(item => {
                    detailsHtml += '<tr>';
                    detailsHtml += `<td class="px-3 py-2 whitespace-nowrap font-mono text-xs">${item.promo_code}</td>`;
                    detailsHtml += `<td class="px-3 py-2">${item.product}</td>`;
                    detailsHtml += `<td class="px-3 py-2 text-center font-semibold text-blue-600">${item.quantity}</td>`;
                    detailsHtml += '</tr>';
                });
                
                detailsHtml += '</tbody></table></div></div>';
            }
            
            // Показываем ошибки
            if (errorDetails && errorDetails.length > 0) {
                detailsHtml += '<div class="mb-4">';
                detailsHtml += '<h4 class="font-semibold text-red-800 mb-2"><i class="fas fa-exclamation-triangle mr-2"></i>Ошибки при обработке:</h4>';
                detailsHtml += '<div class="bg-red-50 rounded p-3 space-y-2">';
                
                errorDetails.forEach(error => {
                    detailsHtml += '<div class="text-sm">';
                    detailsHtml += `<span class="font-semibold">Строка ${error.row}:</span> `;
                    detailsHtml += `<span class="text-red-700">${error.reason}</span>`;
                    if (error.promo_code || error.product) {
                        detailsHtml += '<br><span class="text-xs text-gray-600 ml-4">';
                        detailsHtml += `Промокод: ${error.promo_code || 'не указан'}, `;
                        detailsHtml += `Продукт: ${error.product || 'не указан'}`;
                        detailsHtml += '</span>';
                    }
                    detailsHtml += '</div>';
                });
                
                detailsHtml += '</div></div>';
            }
            
            modalDetails.innerHTML = detailsHtml;
            modal.classList.add('active');
        }

        function closeModal() {
            const modal = document.getElementById('resultModal');
            modal.classList.remove('active');
        }

        // Закрытие модального окна при клике вне его
        window.onclick = function(event) {
            const modal = document.getElementById('resultModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Закрытие модального окна по нажатию ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });

        <?php if ($message): ?>
            // Показываем модальное окно если есть сообщение
            window.addEventListener('DOMContentLoaded', function() {
                const uploadedData = <?= json_encode($uploadedData) ?>;
                const errorDetails = <?= json_encode($errorDetails) ?>;
                showModal('<?= $messageType ?>', '<?= htmlspecialchars($message, ENT_QUOTES) ?>', uploadedData, errorDetails);
            });
        <?php endif; ?>
        
        // Функция удаления загрузки
        async function deleteUpload(uploadId) {
            if (!confirm('Вы уверены, что хотите удалить эту загрузку? Данные будут вычитаны из базы.')) {
                return;
            }
            
            try {
                const response = await fetch('/delete_upload.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ upload_id: uploadId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Обновляем страницу для отображения изменений
                    location.reload();
                } else {
                    alert('Ошибка при удалении: ' + result.error);
                }
            } catch (error) {
                console.error('Ошибка:', error);
                alert('Ошибка при удалении загрузки');
            }
        }
        
        async function previewUpload(uploadId) {
            try {
                console.log('Запрос данных для загрузки ID:', uploadId);
                
                const response = await fetch('/preview_upload.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ upload_id: uploadId })
                });
                
                console.log('Ответ получен, статус:', response.status);
                
                const result = await response.json();
                console.log('Данные получены:', result);
                
                if (result.success) {
                    if (result.data && result.data.length > 0) {
                        showPreviewModal(result.data);
                    } else {
                        alert('Нет данных для отображения');
                    }
                } else {
                    alert('Ошибка при получении данных: ' + result.error);
                }
            } catch (error) {
                console.error('Ошибка:', error);
                alert('Ошибка при получении данных загрузки: ' + error.message);
            }
        }
        
        function showPreviewModal(data) {
            // Создаем модальное окно
            const modal = document.createElement('div');
            modal.className = 'modal active';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="bg-white rounded-lg shadow-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-medium text-gray-900">
                                    <i class="fas fa-eye mr-2"></i>Данные загрузки
                                </h3>
                                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>
                        </div>
                        <div class="px-6 py-4">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Промокод</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Продукт</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Количество</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        ${data.map(item => `
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">${item.promo_code}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${item.product}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono text-center">${item.quantity}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                            <div class="flex justify-between items-center">
                                <div class="text-sm text-gray-600">
                                    Всего записей: <span class="font-semibold">${data.length}</span>
                                </div>
                                <button onclick="closeModal()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                                    Закрыть
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
        
        function closeModal() {
            const modal = document.querySelector('.modal');
            if (modal) {
                modal.remove();
            }
        }
    </script>
</body>
</html>
