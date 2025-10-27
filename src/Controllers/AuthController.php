<?php

namespace ReproCRM\Controllers;

use ReproCRM\Models\Admin;
use ReproCRM\Models\Doctor;
use ReproCRM\Security\JWT;
use ReproCRM\Security\RateLimiter;
use ReproCRM\Utils\Response;

class AuthController
{
    public function adminLogin(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['email']) || !isset($data['password'])) {
            Response::error('Email и пароль обязательны', 400);
            return;
        }
        
        $email = trim($data['email']);
        $password = $data['password'];
        
        // Проверка rate limit
        $clientIP = RateLimiter::getClientIP();
        if (!RateLimiter::checkLimit($clientIP, 'admin_login')) {
            Response::error('Слишком много попыток входа. Попробуйте позже.', 429);
            return;
        }
        
        $admin = Admin::findByEmail($email);
        if (!$admin || !$admin->verifyPassword($password)) {
            Response::error('Неверный email или пароль', 401);
            return;
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
    }
    
    public function doctorLogin(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['email']) || !isset($data['password'])) {
            Response::error('Email и пароль обязательны', 400);
            return;
        }
        
        $email = trim($data['email']);
        $password = $data['password'];
        
        // Проверка rate limit
        $clientIP = RateLimiter::getClientIP();
        if (!RateLimiter::checkLimit($clientIP, 'doctor_login')) {
            Response::error('Слишком много попыток входа. Попробуйте позже.', 429);
            return;
        }
        
        $doctor = User::findByEmail($email);
        if (!$doctor || !$doctor->verifyPassword($password)) {
            Response::error('Неверный email или пароль', 401);
            return;
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
    }
    
    public function doctorRegister(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $requiredFields = ['full_name', 'email', 'city', 'password', 'promo_code'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                Response::error("Поле {$field} обязательно", 400);
                return;
            }
        }
        
        $fullName = trim($data['full_name']);
        $email = trim($data['email']);
        $city = trim($data['city']);
        $password = $data['password'];
        $promoCode = trim($data['promo_code']);
        
        // Валидация email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Некорректный email', 400);
            return;
        }
        
        // Валидация промокода
        if (strlen($promoCode) !== 7 || !ctype_alnum($promoCode)) {
            Response::error('Промокод должен содержать 7 буквенно-цифровых символов', 400);
            return;
        }
        
        // Валидация пароля
        if (strlen($password) < 6) {
            Response::error('Пароль должен содержать минимум 6 символов', 400);
            return;
        }
        
        // Проверка, не зарегистрирован ли уже email
        if (User::findByEmail($email)) {
            Response::error('Пользователь с таким email уже зарегистрирован', 400);
            return;
        }
        
        // Проверка промокода
        $promoCodeObj = \ReproCRM\Models\PromoCode::findByCode($promoCode);
        if (!$promoCodeObj) {
            Response::error('Неверный промокод', 400);
            return;
        }
        
        if ($promoCodeObj->status === 'registered') {
            Response::error('Промокод уже использован', 400);
            return;
        }
        
        // Проверка, не зарегистрирован ли уже этот промокод
        if (User::findByPromoCode($promoCode)) {
            Response::error('Промокод уже зарегистрирован', 400);
            return;
        }
        
        // Создание пользователя
        $doctor = new Doctor([
            'promo_code_id' => $promoCodeObj->id,
            'full_name' => $fullName,
            'email' => $email,
            'city' => $city
        ]);
        $doctor->setPassword($password);
        
        if (!$doctor->save()) {
            Response::error('Ошибка при регистрации', 500);
            return;
        }
        
        // Обновление статуса промокода
        $promoCodeObj->markAsRegistered();
        
        Response::success([
            'message' => 'Регистрация успешна',
            'user' => [
                'id' => $doctor->id,
                'email' => $doctor->email,
                'full_name' => $doctor->full_name,
                'city' => $doctor->city
            ]
        ], 201);
    }
    
    public function verifyToken(): void
    {
        $token = JWT::getTokenFromHeader();
        
        if (!$token) {
            Response::error('Токен не предоставлен', 401);
            return;
        }
        
        $payload = JWT::validate($token);
        
        if (!$payload) {
            Response::error('Неверный или истекший токен', 401);
            return;
        }
        
        Response::success([
            'valid' => true,
            'user' => [
                'id' => $payload['user_id'],
                'type' => $payload['user_type'],
                'email' => $payload['email']
            ]
        ]);
    }
    
    public function logout(): void
    {
        // В JWT нет серверной сессии, поэтому просто возвращаем успех
        // В реальном приложении можно добавить blacklist токенов
        Response::success(['message' => 'Выход выполнен успешно']);
    }
}
