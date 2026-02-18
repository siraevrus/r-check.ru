<?php

namespace ReproCRM\Models;

use ReproCRM\Config\Database;

class Admin
{
    public ?int $id = null;
    public string $email;
    public string $password_hash;
    public string $created_at;
    public string $updated_at;
    
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->id = $data['id'] ?? null;
            $this->email = $data['email'] ?? '';
            $this->password_hash = $data['password_hash'] ?? '';
            $this->created_at = $data['created_at'] ?? '';
            $this->updated_at = $data['updated_at'] ?? '';
        }
    }
    
    public static function findByEmail(string $email): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $data = $stmt->fetch();
        
        return $data ? new self($data) : null;
    }
    
    public static function findById(int $id): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        
        return $data ? new self($data) : null;
    }
    
    public function save(): bool
    {
        $db = Database::getInstance();
        
        if ($this->id) {
            $stmt = $db->prepare("
                UPDATE admins 
                SET email = ?, password_hash = ?, updated_at = datetime('now') 
                WHERE id = ?
            ");
            return $stmt->execute([$this->email, $this->password_hash, $this->id]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO admins (email, password_hash) 
                VALUES (?, ?)
            ");
            $result = $stmt->execute([$this->email, $this->password_hash]);
            
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
        $stmt = $db->prepare("DELETE FROM admins WHERE id = ?");
        return $stmt->execute([$this->id]);
    }
    
    public static function getAll(): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM admins ORDER BY created_at DESC");
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        return array_map(fn($data) => new self($data), $results);
    }
    
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash);
    }
    
    public function setPassword(string $password): void
    {
        $this->password_hash = password_hash($password, PASSWORD_DEFAULT);
    }
}
