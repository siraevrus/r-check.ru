<?php

namespace ReproCRM\Security;

use ReproCRM\Config\Database;

class RateLimiter
{
    private static int $maxRequests;
    private static int $windowSeconds;
    
    public static function init(): void
    {
        self::$maxRequests = (int) ($_ENV['RATE_LIMIT_REQUESTS'] ?? 100);
        self::$windowSeconds = (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 3600);
    }
    
    public static function checkLimit(string $ipAddress, string $endpoint): bool
    {
        $db = Database::getInstance();
        
        // Очистка старых записей
        self::cleanup();
        
        // Получение текущего окна
        $windowStart = date('Y-m-d H:i:s', time() - self::$windowSeconds);
        
        $stmt = $db->prepare("
            SELECT request_count 
            FROM rate_limits 
            WHERE ip_address = ? AND endpoint = ? AND window_start > ?
        ");
        $stmt->execute([$ipAddress, $endpoint, $windowStart]);
        $result = $stmt->fetch();
        
        if ($result) {
            if ($result['request_count'] >= self::$maxRequests) {
                return false;
            }
            
            // Увеличение счетчика
            $stmt = $db->prepare("
                UPDATE rate_limits 
                SET request_count = request_count + 1 
                WHERE ip_address = ? AND endpoint = ? AND window_start > ?
            ");
            $stmt->execute([$ipAddress, $endpoint, $windowStart]);
        } else {
            // Создание новой записи
            $stmt = $db->prepare("
                INSERT INTO rate_limits (ip_address, endpoint, request_count, window_start) 
                VALUES (?, ?, 1, NOW())
            ");
            $stmt->execute([$ipAddress, $endpoint]);
        }
        
        return true;
    }
    
    private static function cleanup(): void
    {
        $db = Database::getInstance();
        $cutoff = date('Y-m-d H:i:s', time() - self::$windowSeconds);
        
        $stmt = $db->prepare("DELETE FROM rate_limits WHERE window_start < ?");
        $stmt->execute([$cutoff]);
    }
    
    public static function getClientIP(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
