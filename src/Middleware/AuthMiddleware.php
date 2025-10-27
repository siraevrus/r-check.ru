<?php

namespace ReproCRM\Middleware;

use ReproCRM\Security\JWT;
use ReproCRM\Utils\Response;

class AuthMiddleware
{
    public static function requireAuth(): array
    {
        $token = JWT::getTokenFromHeader();
        
        if (!$token) {
            Response::unauthorized('Токен авторизации не предоставлен');
        }
        
        $payload = JWT::validate($token);
        
        if (!$payload) {
            Response::unauthorized('Неверный или истекший токен');
        }
        
        return $payload;
    }
    
    public static function requireAdmin(): array
    {
        $payload = self::requireAuth();
        
        if ($payload['user_type'] !== 'admin') {
            Response::forbidden('Требуются права администратора');
        }
        
        return $payload;
    }
    
    public static function requireDoctor(): array
    {
        $payload = self::requireAuth();
        
        if ($payload['user_type'] !== 'doctor') {
            Response::forbidden('Требуются права медицинского специалиста');
        }
        
        return $payload;
    }
    
    public static function requireAdminOrDoctor(): array
    {
        $payload = self::requireAuth();
        
        if (!in_array($payload['user_type'], ['admin', 'doctor'])) {
            Response::forbidden('Требуются права администратора или медицинского специалиста');
        }
        
        return $payload;
    }
}
