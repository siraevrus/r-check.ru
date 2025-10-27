<?php

// Отключаем любой вывод до отправки JSON
ob_start();

// Обработка ошибок
error_reporting(E_ALL);
ini_set('display_errors', 0); // Не показываем ошибки в output
ini_set('log_errors', 1);

try {
    require_once __DIR__ . '/../vendor/autoload.php';
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => 'Ошибка загрузки autoload: ' . $e->getMessage()
        ]
    ]);
    exit;
}

use ReproCRM\Config\Config;
use ReproCRM\Models\Admin;
use ReproCRM\Security\JWT;
use ReproCRM\Utils\Response;

try {
    // Загружаем конфигурацию
    Config::load();
    JWT::init();
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => 'Ошибка инициализации: ' . $e->getMessage()
        ]
    ]);
    exit;
}

// Очищаем буфер перед отправкой заголовков
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$requestUri = $_SERVER['REQUEST_URI'];
$path = str_replace('/api/simple.php', '', parse_url($requestUri, PHP_URL_PATH));
$path = trim($path, '/');

try {
    if ($path === 'admin/dashboard' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller = new \ReproCRM\Controllers\AdminDashboardController();
        $controller->getDashboardData();
        
    } elseif ($path === 'admin/promo-codes' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller = new \ReproCRM\Controllers\AdminDashboardController();
        $controller->getPromoCodes();
        
    } elseif ($path === 'admin/promo-code' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new \ReproCRM\Controllers\AdminDashboardController();
        $controller->addPromoCode();
        
    } elseif ($path === 'admin/doctors-report' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller = new \ReproCRM\Controllers\AdminDashboardController();
        $controller->getDoctorsReport();
        
    } elseif ($path === 'admin-login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['email']) || !isset($data['password'])) {
            Response::error('Email и пароль обязательны', 400);
        }
        
        $admin = Admin::findByEmail($data['email']);
        if (!$admin || !$admin->verifyPassword($data['password'])) {
            Response::error('Неверный email или пароль', 401);
        }
        
        $token = JWT::generate([
            'user_id' => $admin->id,
            'user_type' => 'admin',
            'email' => $admin->email
        ]);
        
        Response::success([
            'token' => $token,
            'user' => [
                'id' => $admin->id,
                'email' => $admin->email,
                'type' => 'admin'
            ]
        ]);
        
    } elseif ($path === 'doctor-login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['email']) || !isset($data['password'])) {
                Response::error('Email и пароль обязательны', 400);
            }
            
            $doctor = \ReproCRM\Models\User::findByEmail($data['email']);
            if (!$doctor) {
                Response::error('Врач с таким email не найден', 401);
            }
            
            if (!$doctor->verifyPassword($data['password'])) {
                Response::error('Неверный пароль', 401);
            }
            
            $token = JWT::generate([
                'user_id' => $doctor->id,
                'user_type' => 'doctor',
                'email' => $doctor->email,
                'promo_code_id' => $doctor->promo_code_id
            ]);
            
            Response::success([
                'token' => $token,
                'user' => [
                    'id' => $doctor->id,
                    'email' => $doctor->email,
                    'full_name' => $doctor->full_name,
                    'city' => $doctor->city,
                    'type' => 'doctor'
                ]
            ]);
        } catch (\Exception $e) {
            Response::error('Ошибка авторизации: ' . $e->getMessage(), 500);
        }
        
    } else {
        Response::error('Endpoint не найден', 404);
    }
    
} catch (Exception $e) {
    Response::error('Внутренняя ошибка сервера: ' . $e->getMessage(), 500);
}
