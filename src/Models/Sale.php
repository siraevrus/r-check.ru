<?php

namespace ReproCRM\Models;

use ReproCRM\Config\Database;

class Sale
{
    public ?int $id = null;
    public int $promo_code_id;
    public string $product_name;
    public string $sale_date;
    public int $quantity;
    public string $created_at;
    
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->id = $data['id'] ?? null;
            $this->promo_code_id = (int) ($data['promo_code_id'] ?? 0);
            $this->product_name = $data['product_name'] ?? '';
            $this->sale_date = $data['sale_date'] ?? '';
            $this->quantity = (int) ($data['quantity'] ?? 1);
            $this->created_at = $data['created_at'] ?? '';
        }
    }
    
    public function save(): bool
    {
        $db = Database::getInstance();
        
        if ($this->id) {
            $stmt = $db->prepare("
                UPDATE sales 
                SET promo_code_id = ?, product_name = ?, sale_date = ?, quantity = ? 
                WHERE id = ?
            ");
            return $stmt->execute([
                $this->promo_code_id, $this->product_name, $this->sale_date, 
                $this->quantity, $this->id
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO sales (promo_code_id, product_name, sale_date, quantity) 
                VALUES (?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $this->promo_code_id, $this->product_name, $this->sale_date, $this->quantity
            ]);
            
            if ($result) {
                $this->id = $db->lastInsertId();
            }
            
            return $result;
        }
    }
    
    public function delete(): bool
    {
        if (!$this->id) {
            return false;
        }
        
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM sales WHERE id = ?");
        return $stmt->execute([$this->id]);
    }
    
    public static function getByPromoCode(int $promoCodeId, int $limit = 100, int $offset = 0): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM sales 
            WHERE promo_code_id = ? 
            ORDER BY sale_date DESC, created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$promoCodeId, $limit, $offset]);
        
        return array_map(fn($data) => new self($data), $stmt->fetchAll());
    }
    
    public static function getTotalByPromoCode(int $promoCodeId): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT SUM(quantity) as total FROM sales WHERE promo_code_id = ?");
        $stmt->execute([$promoCodeId]);
        $result = $stmt->fetch();
        
        return (int) ($result['total'] ?? 0);
    }
    
    public static function batchCreate(array $salesData): int
    {
        $db = Database::getInstance();
        $addedCount = 0;
        
        $stmt = $db->prepare("
            INSERT INTO sales (promo_code_id, product_name, sale_date, quantity) 
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($salesData as $saleData) {
            try {
                if ($stmt->execute([
                    $saleData['promo_code_id'],
                    $saleData['product_name'],
                    $saleData['sale_date'],
                    $saleData['quantity'] ?? 1
                ])) {
                    $addedCount++;
                }
            } catch (\Exception $e) {
                // Логируем ошибку, но продолжаем обработку
                error_log("Ошибка при добавлении продажи: " . $e->getMessage());
            }
        }
        
        return $addedCount;
    }
    
    public static function getSalesReport(array $filters = []): array
    {
        $db = Database::getInstance();
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['promo_code'])) {
            $whereConditions[] = "pc.code LIKE ?";
            $params[] = "%{$filters['promo_code']}%";
        }
        
        if (!empty($filters['doctor_name'])) {
            $whereConditions[] = "d.full_name LIKE ?";
            $params[] = "%{$filters['doctor_name']}%";
        }
        
        if (!empty($filters['city'])) {
            $whereConditions[] = "d.city LIKE ?";
            $params[] = "%{$filters['city']}%";
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "s.sale_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "s.sale_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
        
        $query = "
            SELECT 
                d.id as doctor_id,
                d.full_name,
                d.email,
                d.city,
                pc.code as promo_code,
                COUNT(s.id) as sales_count,
                SUM(s.quantity) as total_quantity
            FROM users d
            JOIN promo_codes pc ON d.promo_code_id = pc.id
            LEFT JOIN sales s ON pc.id = s.promo_code_id
            {$whereClause}
            GROUP BY d.id, d.full_name, d.email, d.city, pc.code
            ORDER BY sales_count DESC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
}
