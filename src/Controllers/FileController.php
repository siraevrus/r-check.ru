<?php

namespace ReproCRM\Controllers;

use ReproCRM\Models\PromoCode;
use ReproCRM\Models\Sale;
use ReproCRM\Middleware\AuthMiddleware;
use ReproCRM\Utils\Response;
use ReproCRM\Utils\PromoCodeNormalizer;
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
        
        if (!in_array($file['type'], $allowedTypes)) {
            Response::error('Неподдерживаемый тип файла. Разрешены: CSV, XLS, XLSX, TXT', 400);
            return;
        }
        
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            Response::error('Размер файла не должен превышать 5MB', 400);
            return;
        }
        
        try {
            $codes = $this->parsePromoCodesFile($file['tmp_name'], $file['type']);
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
        
        if (!in_array($file['type'], $allowedTypes)) {
            Response::error('Неподдерживаемый тип файла. Разрешены: CSV, XLS, XLSX', 400);
            return;
        }
        
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            Response::error('Размер файла не должен превышать 10MB', 400);
            return;
        }
        
        try {
            $salesData = $this->parseSalesReportFile($file['tmp_name'], $file['type']);
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
    
    private function parsePromoCodesFile(string $filePath, string $mimeType): array
    {
        $codes = [];
        
        if (strpos($mimeType, 'csv') !== false || strpos($mimeType, 'text') !== false) {
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
        
        $normalized = [];
        foreach ($codes as $code) {
            $n = PromoCodeNormalizer::normalize($code);
            if ($n !== '' && strlen($n) >= 5 && strlen($n) <= 10) {
                $normalized[] = $n;
            }
        }
        return array_values(array_unique($normalized));
    }
    
    private function parseSalesReportFile(string $filePath, string $mimeType): array
    {
        $salesData = [];
        
        if (strpos($mimeType, 'csv') !== false) {
            // Обработка CSV файлов
            $handle = fopen($filePath, 'r');
            if ($handle === false) {
                throw new \Exception('Не удалось открыть файл');
            }
            
            // Пропускаем заголовок, если есть
            $header = fgetcsv($handle);
            
            while (($line = fgetcsv($handle)) !== false) {
                if (count($line) >= 3) {
                    $promoCodeRaw = trim($line[0]);
                    $promoCode = PromoCodeNormalizer::normalize($promoCodeRaw);
                    if ($promoCode === '') {
                        $promoCode = $promoCodeRaw;
                    }
                    $productName = trim($line[1]);
                    $saleDate = trim($line[2]);
                    $quantity = isset($line[3]) ? (int) $line[3] : 1;
                    
                    // Валидация данных
                    if (empty($promoCode) || empty($productName) || empty($saleDate)) {
                        continue;
                    }
                    
                    // Находим промокод в базе (по нормализованному коду или по последним трем цифрам того же типа)
                    $promoCodeObj = PromoCode::findByCode($promoCode);
                    if (!$promoCodeObj) {
                        $digits = PromoCodeNormalizer::extractLastThreeDigits($promoCode);
                        if ($digits !== null) {
                            $hasHyphen = PromoCodeNormalizer::hasHyphenInNormalized($promoCode);
                            $promoCodeObj = PromoCode::findByLastThreeDigits($digits, $hasHyphen);
                        }
                    }
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
                    $promoCodeRaw = trim($row[0]);
                    $promoCode = PromoCodeNormalizer::normalize($promoCodeRaw);
                    if ($promoCode === '') {
                        $promoCode = $promoCodeRaw;
                    }
                    $productName = trim($row[1]);
                    $saleDate = trim($row[2]);
                    $quantity = isset($row[3]) ? (int) $row[3] : 1;
                    
                    // Валидация данных
                    if (empty($promoCode) || empty($productName) || empty($saleDate)) {
                        continue;
                    }
                    
                    // Находим промокод в базе (по нормализованному коду или по последним трем цифрам того же типа)
                    $promoCodeObj = PromoCode::findByCode($promoCode);
                    if (!$promoCodeObj) {
                        $digits = PromoCodeNormalizer::extractLastThreeDigits($promoCode);
                        if ($digits !== null) {
                            $hasHyphen = PromoCodeNormalizer::hasHyphenInNormalized($promoCode);
                            $promoCodeObj = PromoCode::findByLastThreeDigits($digits, $hasHyphen);
                        }
                    }
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
