<?php

namespace ReproCRM\Controllers;

use ReproCRM\Models\User;
use ReproCRM\Models\Sale;
use ReproCRM\Middleware\AuthMiddleware;
use ReproCRM\Utils\Response;

class UserController
{
    public function getDashboard(): void
    {
        $payload = AuthMiddleware::requireUser();
        
        $user = User::findById($payload['user_id']);
        if (!$user) {
            Response::error('Пользователь не найден', 404);
            return;
        }
        
        $salesCount = $user->getSalesCount();
        $promoCode = $user->getPromoCode();
        
        $stats = [
            'full_name' => $user->full_name,
            'email' => $user->email,
            'city' => $user->city,
            'promo_code' => $promoCode ? $promoCode->code : null,
            'total_sales' => $salesCount
        ];
        
        Response::success($stats);
    }
    
    public function getSalesReport(): void
    {
        $payload = AuthMiddleware::requireUser();
        
        $user = User::findById($payload['user_id']);
        if (!$user) {
            Response::error('Пользователь не найден', 404);
            return;
        }
        
        $page = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 50);
        $offset = ($page - 1) * $limit;
        
        $sales = $user->getSales($limit, $offset);
        $totalSales = $user->getSalesCount();
        
        Response::success([
            'sales' => $sales,
            'total_count' => $totalSales,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalSales,
                'pages' => ceil($totalSales / $limit)
            ]
        ]);
    }
    
    public function getSalesStats(): void
    {
        $payload = AuthMiddleware::requireUser();
        
        $user = User::findById($payload['user_id']);
        if (!$user) {
            Response::error('Пользователь не найден', 404);
            return;
        }
        
        $db = \ReproCRM\Config\Database::getInstance();
        
        // Статистика по месяцам за последний год
        $stmt = $db->prepare("
            SELECT 
                DATE_FORMAT(sale_date, '%Y-%m') as month,
                COUNT(*) as sales_count,
                SUM(quantity) as total_quantity
            FROM sales 
            WHERE promo_code_id = ? 
                AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(sale_date, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->execute([$user->promo_code_id]);
        $monthlyStats = $stmt->fetchAll();

        // Топ продуктов
        $stmt = $db->prepare("
            SELECT
                product_name,
                COUNT(*) as sales_count,
                SUM(quantity) as total_quantity
            FROM sales
            WHERE promo_code_id = ?
            GROUP BY product_name
            ORDER BY sales_count DESC
            LIMIT 10
        ");
        $stmt->execute([$user->promo_code_id]);
        $topProducts = $stmt->fetchAll();
        
        Response::success([
            'monthly_stats' => $monthlyStats,
            'top_products' => $topProducts
        ]);
    }
    
    public function updateProfile(): void
    {
        $payload = AuthMiddleware::requireUser();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $user = User::findById($payload['user_id']);
        if (!$user) {
            Response::error('Пользователь не найден', 404);
            return;
        }
        
        if (isset($data['full_name'])) {
            $user->full_name = trim($data['full_name']);
        }

        if (isset($data['city'])) {
            $user->city = trim($data['city']);
        }

        if (isset($data['email'])) {
            $email = trim($data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::error('Некорректный email', 400);
                return;
            }

            $existingUser = User::findByEmail($email);
            if ($existingUser && $existingUser->id !== $user->id) {
                Response::error('Пользователь с таким email уже существует', 400);
                return;
            }

            $user->email = $email;
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                Response::error('Пароль должен содержать минимум 6 символов', 400);
                return;
            }
            $doctor->setPassword($data['password']);
        }
        
        if (!$user->save()) {
            Response::error('Ошибка при обновлении профиля', 500);
            return;
        }

        Response::success([
            'message' => 'Профиль обновлен успешно',
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'city' => $user->city
            ]
        ]);
    }
    
    public function getProfile(): void
    {
        $payload = AuthMiddleware::requireUser();
        
        $user = User::findById($payload['user_id']);
        if (!$user) {
            Response::error('Пользователь не найден', 404);
            return;
        }
        
        $promoCode = $user->getPromoCode();

        Response::success([
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'city' => $user->city,
                'promo_code' => $promoCode ? $promoCode->code : null,
                'created_at' => $user->created_at
            ]
        ]);
    }
}
