<?php
/**
 * Альтернативный endpoint для авторизации врача
 * Использует обычный путь без PATH_INFO
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ReproCRM\Config\Config;
use ReproCRM\Security\JWT;
use ReproCRM\Utils\Response;

// Загружаем конфигурацию
Config::load();
JWT::init();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Только POST метод
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Метод не поддерживается', 405);
}

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



