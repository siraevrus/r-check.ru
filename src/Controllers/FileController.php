<?php

namespace ReproCRM\Controllers;

use ReproCRM\Models\PromoCode;
use ReproCRM\Models\Sale;
use ReproCRM\Middleware\AuthMiddleware;
use ReproCRM\Utils\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

class FileController
{
    public function uploadPromoCodes(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Ошибка загрузки файла', 400);
            return;
        }
        
        $file = $_FILES['file'];
        $allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain'];
        $allowedExtensions = ['csv', 'xls', 'xlsx', 'txt'];
        
        // Проверка расширения файла
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedExtensions)) {
            Response::error('Неподдерживаемый тип файла. Разрешены: CSV, XLS, XLSX, TXT', 400);
            return;
        }
        
        // Проверка MIME-типа (дополнительная проверка)
        if (!in_array($file['type'], $allowedTypes) && $file['type'] !== 'application/octet-stream') {
            // Если MIME-тип не совпадает, но расширение правильное, все равно разрешаем
            // (некоторые браузеры могут отправлять неправильные MIME-типы)
        }
        
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            Response::error('Размер файла не должен превышать 5MB', 400);
            return;
        }
        
        try {
            $codes = $this->parsePromoCodesFile($file['tmp_name'], $file['type'], $file['name']);
            $addedCount = PromoCode::batchCreate($codes);
            
            Response::success([
                'message' => "Обработано {$addedCount} новых промокодов из " . count($codes) . " всего",
                'added_count' => $addedCount,
                'total_processed' => count($codes)
            ]);
        } catch (\Exception $e) {
            Response::error('Ошибка при обработке файла: ' . $e->getMessage(), 400);
        }
    }
    
    public function uploadSalesReport(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Ошибка загрузки файла', 400);
            return;
        }
        
        $file = $_FILES['file'];
        $allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        $allowedExtensions = ['csv', 'xls', 'xlsx'];
        
        // Проверка расширения файла
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedExtensions)) {
            Response::error('Неподдерживаемый тип файла. Разрешены: CSV, XLS, XLSX', 400);
            return;
        }
        
        // Проверка MIME-типа (дополнительная проверка)
        if (!in_array($file['type'], $allowedTypes) && $file['type'] !== 'application/octet-stream') {
            // Если MIME-тип не совпадает, но расширение правильное, все равно разрешаем
            // (некоторые браузеры могут отправлять неправильные MIME-типы)
        }
        
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            Response::error('Размер файла не должен превышать 10MB', 400);
            return;
        }
        
        try {
            $salesData = $this->parseSalesReportFile($file['tmp_name'], $file['type'], $file['name']);
            $addedCount = Sale::batchCreate($salesData);
            
            Response::success([
                'message' => "Добавлено {$addedCount} записей о продажах из " . count($salesData) . " всего",
                'added_count' => $addedCount,
                'total_processed' => count($salesData)
            ]);
        } catch (\Exception $e) {
            Response::error('Ошибка при обработке файла: ' . $e->getMessage(), 400);
        }
    }
    
    private function parsePromoCodesFile(string $filePath, string $mimeType, string $fileName = ''): array
    {
        $codes = [];
        
        // Определяем тип файла по расширению или MIME-типу
        $fileExtension = $fileName ? strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) : '';
        $isCsv = in_array($fileExtension, ['csv', 'txt']) || strpos($mimeType, 'csv') !== false || strpos($mimeType, 'text') !== false;
        
        if ($isCsv) {
            // Обработка CSV/TXT файлов
            $handle = fopen($filePath, 'r');
            if ($handle === false) {
                throw new \Exception('Не удалось открыть файл');
            }
            
            while (($line = fgetcsv($handle)) !== false) {
                if (!empty($line[0])) {
                    $codes[] = trim($line[0]);
                }
            }
            
            fclose($handle);
        } else {
            // Обработка Excel файлов
            $reader = IOFactory::createReader(IOFactory::identify($filePath));
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            foreach ($rows as $row) {
                if (!empty($row[0])) {
                    $codes[] = trim($row[0]);
                }
            }
        }
        
        return array_filter($codes, function($code) {
            return strlen($code) === 7 && ctype_alnum($code);
        });
    }
    
    private function parseSalesReportFile(string $filePath, string $mimeType, string $fileName = ''): array
    {
        $salesData = [];
        
        // Определяем тип файла по расширению или MIME-типу
        $fileExtension = $fileName ? strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) : '';
        $isCsv = $fileExtension === 'csv' || strpos($mimeType, 'csv') !== false;
        
        if ($isCsv) {
            // Обработка CSV файлов
            $handle = fopen($filePath, 'r');
            if ($handle === false) {
                throw new \Exception('Не удалось открыть файл');
            }
            
            // Пропускаем заголовок, если есть
            $header = fgetcsv($handle);
            
            while (($line = fgetcsv($handle)) !== false) {
                if (count($line) >= 3) {
                    $promoCode = trim($line[0]);
                    $productName = trim($line[1]);
                    $saleDate = trim($line[2]);
                    $quantity = isset($line[3]) ? (int) $line[3] : 1;
                    
                    // Валидация данных
                    if (empty($promoCode) || empty($productName) || empty($saleDate)) {
                        continue;
                    }
                    
                    // Находим промокод в базе
                    $promoCodeObj = PromoCode::findByCode($promoCode);
                    if (!$promoCodeObj) {
                        continue; // Пропускаем неизвестные промокоды
                    }
                    
                    // Валидация даты
                    $dateObj = \DateTime::createFromFormat('Y-m-d', $saleDate);
                    if (!$dateObj || $dateObj->format('Y-m-d') !== $saleDate) {
                        // Пробуем другие форматы
                        $dateObj = \DateTime::createFromFormat('d.m.Y', $saleDate);
                        if ($dateObj) {
                            $saleDate = $dateObj->format('Y-m-d');
                        } else {
                            $dateObj = \DateTime::createFromFormat('d/m/Y', $saleDate);
                            if ($dateObj) {
                                $saleDate = $dateObj->format('Y-m-d');
                            } else {
                                continue; // Пропускаем невалидные даты
                            }
                        }
                    }
                    
                    $salesData[] = [
                        'promo_code_id' => $promoCodeObj->id,
                        'product_name' => $productName,
                        'sale_date' => $saleDate,
                        'quantity' => $quantity
                    ];
                }
            }
            
            fclose($handle);
        } else {
            // Обработка Excel файлов
            $reader = IOFactory::createReader(IOFactory::identify($filePath));
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            // Пропускаем заголовок
            array_shift($rows);
            
            foreach ($rows as $row) {
                if (count($row) >= 3) {
                    $promoCode = trim($row[0]);
                    $productName = trim($row[1]);
                    $saleDate = trim($row[2]);
                    $quantity = isset($row[3]) ? (int) $row[3] : 1;
                    
                    // Валидация данных
                    if (empty($promoCode) || empty($productName) || empty($saleDate)) {
                        continue;
                    }
                    
                    // Находим промокод в базе
                    $promoCodeObj = PromoCode::findByCode($promoCode);
                    if (!$promoCodeObj) {
                        continue; // Пропускаем неизвестные промокоды
                    }
                    
                    // Обработка даты из Excel
                    if (is_numeric($saleDate)) {
                        // Excel дата как число
                        $dateObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($saleDate);
                        $saleDate = $dateObj->format('Y-m-d');
                    } else {
                        // Строковая дата
                        $dateObj = \DateTime::createFromFormat('Y-m-d', $saleDate);
                        if (!$dateObj || $dateObj->format('Y-m-d') !== $saleDate) {
                            $dateObj = \DateTime::createFromFormat('d.m.Y', $saleDate);
                            if ($dateObj) {
                                $saleDate = $dateObj->format('Y-m-d');
                            } else {
                                $dateObj = \DateTime::createFromFormat('d/m/Y', $saleDate);
                                if ($dateObj) {
                                    $saleDate = $dateObj->format('Y-m-d');
                                } else {
                                    continue;
                                }
                            }
                        }
                    }
                    
                    $salesData[] = [
                        'promo_code_id' => $promoCodeObj->id,
                        'product_name' => $productName,
                        'sale_date' => $saleDate,
                        'quantity' => $quantity
                    ];
                }
            }
        }
        
        return $salesData;
    }
}
