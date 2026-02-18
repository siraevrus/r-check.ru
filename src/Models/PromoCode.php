<?php

namespace ReproCRM\Models;

use ReproCRM\Config\Database;
use ReproCRM\Utils\PromoCodeNormalizer;

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

    /**
     * Находит промокод по последним трем цифрам (идентификатору).
     */
    public static function findByLastThreeDigits(string $digits): ?self
    {
        $digits = str_pad(preg_replace('/\D/', '', $digits), 3, '0', STR_PAD_LEFT);
        if (strlen($digits) !== 3) {
            return null;
        }
        $db = Database::getInstance();
        $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $db->prepare(
                "SELECT * FROM promo_codes WHERE SUBSTR(REPLACE(REPLACE(code, '-', ''), ' ', ''), -3) = ? LIMIT 1"
            );
        } else {
            $stmt = $db->prepare(
                "SELECT * FROM promo_codes WHERE SUBSTRING(REPLACE(REPLACE(code, '-', ''), ' ', ''), -3) = ? LIMIT 1"
            );
        }
        $stmt->execute([$digits]);
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
        $len = strlen(preg_replace('/[\s\-]/', '', $this->code));
        return $len >= 5 && $len <= 10;
    }

    /**
     * Пакетное создание промокодов с нормализацией и проверкой дубликатов по последним трем цифрам.
     */
    public static function batchCreate(array $codes): int
    {
        $db = Database::getInstance();
        $addedCount = 0;

        foreach ($codes as $code) {
            $code = trim($code);
            if ($code === '') {
                continue;
            }
            $normalized = PromoCodeNormalizer::normalize($code);
            if ($normalized === '') {
                continue;
            }
            $digits = PromoCodeNormalizer::extractLastThreeDigits($code);
            if ($digits !== null) {
                $existing = self::findByLastThreeDigits($digits);
                if ($existing !== null) {
                    continue;
                }
            }
            $existingByCode = self::findByCode($normalized);
            if ($existingByCode !== null) {
                continue;
            }
            $promoCode = new self(['code' => $normalized]);
            if ($promoCode->save()) {
                $addedCount++;
            }
        }

        return $addedCount;
    }
}
