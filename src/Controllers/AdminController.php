<?php

namespace ReproCRM\Controllers;

use ReproCRM\Models\Admin;
use ReproCRM\Models\PromoCode;
use ReproCRM\Models\Doctor;
use ReproCRM\Models\Sale;
use ReproCRM\Middleware\AuthMiddleware;
use ReproCRM\Utils\Response;

class AdminController
{
    public function getDashboard(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        $stats = [
            'total_promo_codes' => PromoCode::getCount(),
            'registered_doctors' => User::getCount(),
            'unregistered_codes' => PromoCode::getCount() - User::getCount(),
            'total_sales' => Sale::getSalesReport([])
        ];
        
        Response::success($stats);
    }
    
    public function getPromoCodes(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        $page = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 50);
        $search = $_GET['search'] ?? '';
        
        $offset = ($page - 1) * $limit;
        
        $db = \ReproCRM\Config\Database::getInstance();
        
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
        $countParams = array_slice($params, 0, -2); // Убираем limit и offset
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
    }
    
    public function addPromoCode(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['code']) || empty(trim($data['code']))) {
            Response::error('Промокод обязателен', 400);
            return;
        }
        
        $code = strtoupper(trim($data['code']));
        
        $length = strlen($code);
        if ($length < 5 || $length > 10) {
            Response::error('Промокод должен содержать от 5 до 10 символов', 400);
            return;
        }
        
        if (!preg_match('/^[A-Z0-9!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]+$/', $code)) {
            Response::error('Промокод должен содержать только заглавные буквы, цифры и символы', 400);
            return;
        }
        
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
    }
    
    public function deletePromoCode(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        $code = $_GET['code'] ?? '';
        
        if (empty($code)) {
            Response::error('Промокод не указан', 400);
            return;
        }
        
        $promoCode = PromoCode::findByCode($code);
        if (!$promoCode) {
            Response::error('Промокод не найден', 404);
            return;
        }
        
        if ($promoCode->status === 'registered') {
            Response::error('Нельзя удалить зарегистрированный промокод', 400);
            return;
        }
        
        if (!$promoCode->delete()) {
            Response::error('Ошибка при удалении промокода', 500);
            return;
        }
        
        Response::success(['message' => 'Промокод удален успешно']);
    }
    
    public function getDoctorsReport(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        $filters = [
            'promo_code' => $_GET['promo_code'] ?? '',
            'doctor_name' => $_GET['doctor_name'] ?? '',
            'city' => $_GET['city'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? ''
        ];
        
        $report = Sale::getSalesReport($filters);
        
        Response::success([
            'report' => $report,
            'filters' => $filters
        ]);
    }
    
    public function getDoctorDetails(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        $doctorId = (int) ($_GET['id'] ?? 0);
        
        if (!$doctorId) {
            Response::error('ID врача не указан', 400);
            return;
        }
        
        $doctor = User::findById($doctorId);
        if (!$doctor) {
            Response::error('Врач не найден', 404);
            return;
        }
        
        $page = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 50);
        $offset = ($page - 1) * $limit;
        
        $sales = $doctor->getSales($limit, $offset);
        $totalSales = $doctor->getSalesCount();
        
        Response::success([
            'doctor' => [
                'id' => $doctor->id,
                'full_name' => $doctor->full_name,
                'email' => $doctor->email,
                'city' => $doctor->city,
                'promo_code' => $doctor->getPromoCode()->code ?? null,
                'sales_count' => $totalSales
            ],
            'sales' => $sales,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalSales,
                'pages' => ceil($totalSales / $limit)
            ]
        ]);
    }
    
    public function resetDoctorSales(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['doctor_id']) || !is_numeric($data['doctor_id'])) {
            Response::error('ID врача обязателен', 400);
            return;
        }
        
        $doctorId = (int) $data['doctor_id'];
        $adminId = $payload['user_id'];
        
        $doctor = User::findById($doctorId);
        if (!$doctor) {
            Response::error('Врач не найден', 404);
            return;
        }
        
        if (!$doctor->resetSalesCount($adminId)) {
            Response::error('Ошибка при сбросе статистики', 500);
            return;
        }
        
        Response::success(['message' => 'Статистика сброшена успешно']);
    }
    
    public function getAdmins(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        $admins = Admin::getAll();
        
        Response::success([
            'admins' => array_map(function($admin) {
                return [
                    'id' => $admin->id,
                    'email' => $admin->email,
                    'created_at' => $admin->created_at
                ];
            }, $admins)
        ]);
    }
    
    public function addAdmin(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['email']) || !isset($data['password'])) {
            Response::error('Email и пароль обязательны', 400);
            return;
        }
        
        $email = trim($data['email']);
        $password = $data['password'];
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Некорректный email', 400);
            return;
        }
        
        if (strlen($password) < 6) {
            Response::error('Пароль должен содержать минимум 6 символов', 400);
            return;
        }
        
        $existingAdmin = Admin::findByEmail($email);
        if ($existingAdmin) {
            Response::error('Администратор с таким email уже существует', 400);
            return;
        }
        
        $admin = new Admin(['email' => $email]);
        $admin->setPassword($password);
        
        if (!$admin->save()) {
            Response::error('Ошибка при создании администратора', 500);
            return;
        }
        
        Response::success([
            'message' => 'Администратор создан успешно',
            'admin' => [
                'id' => $admin->id,
                'email' => $admin->email
            ]
        ], 201);
    }
    
    public function deleteAdmin(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        $adminId = (int) ($_GET['id'] ?? 0);
        
        if (!$adminId) {
            Response::error('ID администратора не указан', 400);
            return;
        }
        
        if ($adminId === $payload['user_id']) {
            Response::error('Нельзя удалить самого себя', 400);
            return;
        }
        
        $admin = Admin::findById($adminId);
        if (!$admin) {
            Response::error('Администратор не найден', 404);
            return;
        }
        
        if (!$admin->delete()) {
            Response::error('Ошибка при удалении администратора', 500);
            return;
        }
        
        Response::success(['message' => 'Администратор удален успешно']);
    }
    
    public function updateProfile(): void
    {
        $payload = AuthMiddleware::requireAdmin();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $admin = Admin::findById($payload['user_id']);
        if (!$admin) {
            Response::error('Администратор не найден', 404);
            return;
        }
        
        if (isset($data['email'])) {
            $email = trim($data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::error('Некорректный email', 400);
                return;
            }
            
            $existingAdmin = Admin::findByEmail($email);
            if ($existingAdmin && $existingAdmin->id !== $admin->id) {
                Response::error('Администратор с таким email уже существует', 400);
                return;
            }
            
            $admin->email = $email;
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                Response::error('Пароль должен содержать минимум 6 символов', 400);
                return;
            }
            $admin->setPassword($data['password']);
        }
        
        if (!$admin->save()) {
            Response::error('Ошибка при обновлении профиля', 500);
            return;
        }
        
        Response::success([
            'message' => 'Профиль обновлен успешно',
            'admin' => [
                'id' => $admin->id,
                'email' => $admin->email
            ]
        ]);
    }
}
