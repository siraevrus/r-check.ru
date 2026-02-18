<?php

namespace ReproCRM\Models;

use ReproCRM\Config\Database;

class User
{
    public ?int $id = null;
    public int $promo_code_id;
    public string $full_name;
    public string $email;
    public string $city;
    public string $phone;
    public string $password_hash;
    public string $created_at;
    public string $updated_at;
    
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->id = $data['id'] ?? null;
            $this->promo_code_id = (int) ($data['promo_code_id'] ?? 0);
            $this->full_name = $data['full_name'] ?? '';
            $this->email = $data['email'] ?? '';
            $this->city = $data['city'] ?? '';
            $this->phone = $data['phone'] ?? '';
            $this->password_hash = $data['password_hash'] ?? '';
            $this->created_at = $data['created_at'] ?? '';
            $this->updated_at = $data['updated_at'] ?? '';
        }
    }
    
    public static function findByEmail(string $email): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $data = $stmt->fetch();
        
        return $data ? new self($data) : null;
    }
    
    public static function findById(int $id): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        
        return $data ? new self($data) : null;
    }
    
    public static function findByPromoCode(string $promoCode): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT u.* 
            FROM users u 
            JOIN promo_codes pc ON u.promo_code_id = pc.id 
            WHERE pc.code = ?
        ");
        $stmt->execute([$promoCode]);
        $data = $stmt->fetch();
        
        return $data ? new self($data) : null;
    }
    
    public function save(): bool
    {
        $db = Database::getInstance();
        
        if ($this->id) {
            $stmt = $db->prepare("
                UPDATE users 
                SET promo_code_id = ?, full_name = ?, email = ?, city = ?, 
                    password_hash = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            return $stmt->execute([
                $this->promo_code_id, $this->full_name, $this->email, 
                $this->city, $this->password_hash, $this->id
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO users (promo_code_id, full_name, email, city, password_hash) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $this->promo_code_id, $this->full_name, $this->email, 
                $this->city, $this->password_hash
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
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$this->id]);
    }
    
    public static function getAll(int $limit = 100, int $offset = 0): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT u.*, pc.code as promo_code,
                   (SELECT COUNT(*) FROM sales WHERE promo_code_id = pc.id) as sales_count
            FROM users u 
            JOIN promo_codes pc ON u.promo_code_id = pc.id 
            ORDER BY u.created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $results = $stmt->fetchAll();
        
        return array_map(fn($data) => new self($data), $results);
    }
    
    public static function getCount(): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
        $stmt->execute();
        $result = $stmt->fetch();

        return (int) $result['count'];
    }
    
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash);
    }
    
    public function setPassword(string $password): void
    {
        $this->password_hash = password_hash($password, PASSWORD_DEFAULT);
    }
    
    public function getPromoCode(): ?PromoCode
    {
        return PromoCode::findById($this->promo_code_id);
    }
    
    public function getSalesCount(): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM sales WHERE promo_code_id = ?");
        $stmt->execute([$this->promo_code_id]);
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }
    
    public function getSales(int $limit = 100, int $offset = 0): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM sales 
            WHERE promo_code_id = ? 
            ORDER BY sale_date DESC, created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$this->promo_code_id, $limit, $offset]);
        
        return $stmt->fetchAll();
    }
    
    public function resetSalesCount(int $adminId): bool
    {
        $db = Database::getInstance();
        
        try {
            $db->beginTransaction();
            
            // Получаем текущее количество продаж
            $currentCount = $this->getSalesCount();
            
            // Записываем в историю
            $stmt = $db->prepare("
                INSERT INTO promo_history (doctor_id, admin_id, action_type, previous_count, new_count) 
                VALUES (?, ?, 'reset', ?, 0)
            ");
            $stmt->execute([$this->id, $adminId, $currentCount]);
            
            // Удаляем все продажи
            $stmt = $db->prepare("DELETE FROM sales WHERE promo_code_id = ?");
            $stmt->execute([$this->promo_code_id]);
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollback();
            return false;
        }
    }
}
