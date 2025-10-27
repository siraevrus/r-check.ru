<?php

namespace ReproCRM\Models;

use ReproCRM\Config\Database;

class PromoCode
{
    public ?int $id = null;
    public string $code;
    public string $status;
    public string $created_at;
    public string $updated_at;
    
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->id = $data['id'] ?? null;
            $this->code = $data['code'] ?? '';
            $this->status = $data['status'] ?? 'unregistered';
            $this->created_at = $data['created_at'] ?? '';
            $this->updated_at = $data['updated_at'] ?? '';
        }
    }
    
    public static function findByCode(string $code): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM promo_codes WHERE code = ?");
        $stmt->execute([$code]);
        $data = $stmt->fetch();
        
        return $data ? new self($data) : null;
    }
    
    public static function findById(int $id): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM promo_codes WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        
        return $data ? new self($data) : null;
    }
    
    public function save(): bool
    {
        $db = Database::getInstance();
        
        if ($this->id) {
            $stmt = $db->prepare("
                UPDATE promo_codes 
                SET code = ?, status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            return $stmt->execute([$this->code, $this->status, $this->id]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO promo_codes (code, status) 
                VALUES (?, ?)
            ");
            $result = $stmt->execute([$this->code, $this->status]);
            
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
        $stmt = $db->prepare("DELETE FROM promo_codes WHERE id = ?");
        return $stmt->execute([$this->id]);
    }
    
    public static function getAll(int $limit = 100, int $offset = 0): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT pc.*, d.full_name 
            FROM promo_codes pc 
            LEFT JOIN doctors d ON pc.id = d.promo_code_id 
            ORDER BY pc.created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $results = $stmt->fetchAll();
        
        return array_map(fn($data) => new self($data), $results);
    }
    
    public static function getCount(): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM promo_codes");
        $stmt->execute();
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }
    
    public function markAsRegistered(): bool
    {
        $this->status = 'registered';
        return $this->save();
    }
    
    public function markAsUnregistered(): bool
    {
        $this->status = 'unregistered';
        return $this->save();
    }
    
    public function isValid(): bool
    {
        return strlen($this->code) === 7 && ctype_alnum($this->code);
    }
    
    public static function batchCreate(array $codes): int
    {
        $db = Database::getInstance();
        $addedCount = 0;
        
        foreach ($codes as $code) {
            $code = trim($code);
            if (strlen($code) === 7 && ctype_alnum($code)) {
                // Проверяем, не существует ли уже такой код
                $existing = self::findByCode($code);
                if (!$existing) {
                    $promoCode = new self(['code' => $code]);
                    if ($promoCode->save()) {
                        $addedCount++;
                    }
                }
            }
        }
        
        return $addedCount;
    }
}
