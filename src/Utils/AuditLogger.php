<?php

namespace ReproCRM\Utils;

/**
 * Класс для ведения журнала аудита действий пользователей
 */
class AuditLogger
{
    private $pdo;
    
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Логирование действия пользователя
     */
    public function log($userType, $userId, $userEmail, $action, $resourceType = null, $resourceId = null, $details = null)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_log (user_type, user_id, user_email, action, resource_type, resource_id, details, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
            ");
            
            $ipAddress = $this->getClientIpAddress();
            $userAgent = $this->getUserAgent();
            
            $detailsJson = $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
            
            $stmt->execute([
                $userType,
                $userId,
                $userEmail,
                $action,
                $resourceType,
                $resourceId,
                $detailsJson,
                $ipAddress,
                $userAgent
            ]);
            
            return true;
        } catch (\Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Логирование входа в систему
     */
    public function logLogin($userType, $userId, $userEmail, $success = true)
    {
        $action = $success ? 'login_success' : 'login_failed';
        return $this->log($userType, $userId, $userEmail, $action);
    }
    
    /**
     * Логирование выхода из системы
     */
    public function logLogout($userType, $userId, $userEmail)
    {
        return $this->log($userType, $userId, $userEmail, 'logout');
    }
    
    /**
     * Логирование создания промокода
     */
    public function logPromoCodeCreated($userType, $userId, $userEmail, $promoCodeId, $promoCode)
    {
        return $this->log($userType, $userId, $userEmail, 'create_promo', 'promo_code', $promoCodeId, [
            'promo_code' => $promoCode
        ]);
    }
    
    /**
     * Логирование удаления промокода
     */
    public function logPromoCodeDeleted($userType, $userId, $userEmail, $promoCodeId, $promoCode)
    {
        return $this->log($userType, $userId, $userEmail, 'delete_promo', 'promo_code', $promoCodeId, [
            'promo_code' => $promoCode
        ]);
    }
    
    /**
     * Логирование массовых операций
     */
    public function logBulkAction($userType, $userId, $userEmail, $action, $resourceType, $count, $details = null)
    {
        return $this->log($userType, $userId, $userEmail, $action, $resourceType, null, [
            'count' => $count,
            'details' => $details
        ]);
    }
    
    /**
     * Логирование загрузки файла
     */
    public function logFileUpload($userType, $userId, $userEmail, $fileName, $recordsProcessed, $recordsInserted)
    {
        return $this->log($userType, $userId, $userEmail, 'file_upload', 'sales', null, [
            'file_name' => $fileName,
            'records_processed' => $recordsProcessed,
            'records_inserted' => $recordsInserted
        ]);
    }
    
    /**
     * Логирование создания резервной копии
     */
    public function logBackupCreated($userType, $userId, $userEmail, $backupFileName)
    {
        return $this->log($userType, $userId, $userEmail, 'backup_created', 'database', null, [
            'backup_file' => $backupFileName
        ]);
    }
    
    /**
     * Логирование восстановления из резервной копии
     */
    public function logBackupRestored($userType, $userId, $userEmail, $backupFileName)
    {
        return $this->log($userType, $userId, $userEmail, 'backup_restored', 'database', null, [
            'backup_file' => $backupFileName
        ]);
    }
    
    /**
     * Получение логов с фильтрацией
     */
    public function getLogs($filters = [], $limit = 100, $offset = 0)
    {
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['user_type'])) {
            $whereConditions[] = "user_type = ?";
            $params[] = $filters['user_type'];
        }
        
        if (!empty($filters['action'])) {
            $whereConditions[] = "action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['user_email'])) {
            $whereConditions[] = "user_email LIKE ?";
            $params[] = "%{$filters['user_email']}%";
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT * FROM audit_log {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Получение статистики логов
     */
    public function getStats($days = 30)
    {
        $sql = "
            SELECT 
                action,
                COUNT(*) as count,
                user_type
            FROM audit_log 
            WHERE created_at >= date('now', '-{$days} days')
            GROUP BY action, user_type
            ORDER BY count DESC
        ";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Получение IP адреса клиента
     */
    private function getClientIpAddress()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
    }
    
    /**
     * Получение User Agent
     */
    private function getUserAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
    
    /**
     * Очистка старых логов (старше указанного количества дней)
     */
    public function cleanOldLogs($days = 365)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM audit_log WHERE created_at < date('now', '-{$days} days')");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log("Error cleaning old logs: " . $e->getMessage());
            return false;
        }
    }
}
?>
