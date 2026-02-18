<?php

namespace ReproCRM\Controllers;

use ReproCRM\Models\Admin;
use ReproCRM\Models\PromoCode;
use ReproCRM\Models\Doctor;
use ReproCRM\Models\Sale;
use ReproCRM\Middleware\AuthMiddleware;
use ReproCRM\Utils\Response;

class AdminDashboardController
{
    public function getDashboardDataRaw(): array
    {
        try {
            // Устанавливаем тип базы данных
            $_ENV['DB_TYPE'] = 'sqlite';
            $db = \ReproCRM\Config\Database::getInstance();
            
            // Общая статистика
            $stmt = $db->query("SELECT COUNT(*) as count FROM promo_codes");
            $stats = [
                'total_promo_codes' => $stmt->fetch()['count'],
                'registered_doctors' => 0,
                'total_sales' => 0,
                'unregistered_codes' => 0
            ];
            
            // Получаем количество зарегистрированных врачей
            $stmt = $db->query("SELECT COUNT(*) as count FROM users");
            $stats['registered_users'] = $stmt->fetch()['count'];
            
            // Получаем количество незарегистрированных промокодов
            $stmt = $db->query("SELECT COUNT(*) as count FROM promo_codes WHERE status = 'unregistered'");
            $stats['unregistered_codes'] = $stmt->fetch()['count'];
            
            // Получаем общее количество продаж
            $stmt = $db->query("SELECT COUNT(*) as count FROM sales");
            $stats['total_sales'] = $stmt->fetch()['count'];
            
            // Топ врачей по продажам
            $stmt = $db->query("
                SELECT 
                    d.full_name,
                    d.city,
                    d.promo_code,
                    COUNT(s.id) as sales_count,
                    COALESCE(SUM(s.quantity), 0) as total_quantity
                FROM users d
                LEFT JOIN promo_codes pc ON d.promo_code_id = pc.id
                LEFT JOIN sales s ON pc.id = s.promo_code_id
                GROUP BY d.id, d.full_name, d.city, d.promo_code
                ORDER BY sales_count DESC
                LIMIT 10
            ");
            $topDoctors = $stmt->fetchAll();
            
            return [
                'stats' => $stats,
                'top_doctors' => $topDoctors,
                'monthly_stats' => []
            ];
        } catch (\Exception $e) {
            return [
                'stats' => [
                    'total_promo_codes' => 0,
                    'registered_doctors' => 0,
                    'total_sales' => 0,
                    'unregistered_codes' => 0
                ],
                'top_doctors' => [],
                'monthly_stats' => []
            ];
        }
    }

    public function getDashboardData(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        try {
            $db = \ReproCRM\Config\Database::getInstance();
            
            // Общая статистика
            $stats = [
                'total_promo_codes' => PromoCode::getCount(),
                'registered_doctors' => User::getCount(),
                'total_sales' => 0,
                'unregistered_codes' => 0
            ];
            
            // Получаем количество незарегистрированных промокодов
            $stmt = $db->query("SELECT COUNT(*) as count FROM promo_codes WHERE status = 'unregistered'");
            $stats['unregistered_codes'] = (int) $stmt->fetch()['count'];
            
            // Получаем общее количество продаж
            $stmt = $db->query("SELECT COUNT(*) as count FROM sales");
            $stats['total_sales'] = (int) $stmt->fetch()['count'];
            
            // Топ врачей по продажам
            $stmt = $db->query("
                SELECT d.full_name, d.city, pc.code as promo_code,
                       COUNT(s.id) as sales_count,
                       SUM(s.quantity) as total_quantity
                FROM users d
                JOIN promo_codes pc ON d.promo_code_id = pc.id
                LEFT JOIN sales s ON pc.id = s.promo_code_id
                GROUP BY d.id, d.full_name, d.city, pc.code
                ORDER BY sales_count DESC
                LIMIT 5
            ");
            $topDoctors = $stmt->fetchAll();
            
            // Статистика по месяцам
            $stmt = $db->query("
                SELECT strftime('%Y-%m', sale_date) as month,
                       COUNT(*) as sales_count,
                       SUM(quantity) as total_quantity
                FROM sales 
                WHERE sale_date >= date('now', '-12 months')
                GROUP BY strftime('%Y-%m', sale_date)
                ORDER BY month DESC
                LIMIT 12
            ");
            $monthlyStats = $stmt->fetchAll();
            
            Response::success([
                'stats' => $stats,
                'top_doctors' => $topDoctors,
                'monthly_stats' => $monthlyStats
            ]);
            
        } catch (\Exception $e) {
            Response::error('Ошибка получения данных: ' . $e->getMessage(), 500);
        }
    }
    
    public function getPromoCodes(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        $page = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 20);
        $search = $_GET['search'] ?? '';
        
        try {
            $db = \ReproCRM\Config\Database::getInstance();
            
            $offset = ($page - 1) * $limit;
            
            $whereClause = '';
            $params = [];
            
            if (!empty($search)) {
                $whereClause = 'WHERE pc.code LIKE ? OR d.full_name LIKE ?';
                $params = ["%{$search}%", "%{$search}%"];
            }
            
            $query = "
                SELECT pc.*, d.full_name, d.email, d.city,
                       (SELECT COUNT(*) FROM sales WHERE promo_code_id = pc.id) as sales_count
                FROM promo_codes pc 
                LEFT JOIN doctors d ON pc.id = d.promo_code_id 
                {$whereClause}
                ORDER BY pc.created_at DESC 
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $promoCodes = $stmt->fetchAll();
            
            // Получаем общее количество для пагинации
            $countQuery = "SELECT COUNT(*) as total FROM promo_codes pc LEFT JOIN doctors d ON pc.id = d.promo_code_id {$whereClause}";
            $countParams = array_slice($params, 0, -2);
            $countStmt = $db->prepare($countQuery);
            $countStmt->execute($countParams);
            $total = $countStmt->fetch()['total'];
            
            Response::success([
                'promo_codes' => $promoCodes,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int) $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            
        } catch (\Exception $e) {
            Response::error('Ошибка получения промокодов: ' . $e->getMessage(), 500);
        }
    }
    
    public function addPromoCode(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['code']) || empty(trim($data['code']))) {
            Response::error('Промокод обязателен', 400);
            return;
        }
        
        $code = trim($data['code']);
        
        if (strlen($code) !== 7 || !ctype_alnum($code)) {
            Response::error('Промокод должен содержать 7 буквенно-цифровых символов', 400);
            return;
        }
        
        try {
            $existingPromoCode = PromoCode::findByCode($code);
            if ($existingPromoCode) {
                Response::error('Промокод уже существует', 400);
                return;
            }
            
            $promoCode = new PromoCode(['code' => $code]);
            
            if (!$promoCode->save()) {
                Response::error('Ошибка при создании промокода', 500);
                return;
            }
            
            Response::success([
                'message' => 'Промокод создан успешно',
                'promo_code' => [
                    'id' => $promoCode->id,
                    'code' => $promoCode->code,
                    'status' => $promoCode->status
                ]
            ], 201);
            
        } catch (\Exception $e) {
            Response::error('Ошибка: ' . $e->getMessage(), 500);
        }
    }
    
    public function getDoctorsReport(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        try {
            $db = \ReproCRM\Config\Database::getInstance();
            
            $filters = [
                'promo_code' => $_GET['promo_code'] ?? '',
                'doctor_name' => $_GET['doctor_name'] ?? '',
                'city' => $_GET['city'] ?? ''
            ];
            
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
            $report = $stmt->fetchAll();
            
            Response::success([
                'report' => $report,
                'filters' => $filters
            ]);
            
        } catch (\Exception $e) {
            Response::error('Ошибка получения отчета: ' . $e->getMessage(), 500);
        }
    }
}
