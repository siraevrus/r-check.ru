<?php

namespace ReproCRM\Utils;

class Response
{
    public static function success($data = null, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public static function error(string $message, int $statusCode = 400, $details = null): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'details' => $details
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public static function unauthorized(string $message = 'Не авторизован'): void
    {
        self::error($message, 401);
    }
    
    public static function forbidden(string $message = 'Доступ запрещен'): void
    {
        self::error($message, 403);
    }
    
    public static function notFound(string $message = 'Ресурс не найден'): void
    {
        self::error($message, 404);
    }
    
    public static function methodNotAllowed(string $message = 'Метод не разрешен'): void
    {
        self::error($message, 405);
    }
    
    public static function validationError(array $errors): void
    {
        self::error('Ошибки валидации', 422, $errors);
    }
}
