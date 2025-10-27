<?php

namespace ReproCRM\Config;

class Config
{
    public static function load(): void
    {
        if (file_exists(__DIR__ . '/../../config.env')) {
            $lines = file(__DIR__ . '/../../config.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Удаляем кавычки если они есть
                    if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                        (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                        $value = substr($value, 1, -1);
                    }
                    
                    $_ENV[$key] = $value;
                }
            }
        }
    }
    
    public static function get(string $key, string $default = ''): string
    {
        return $_ENV[$key] ?? $default;
    }
    
    public static function getAppUrl(): string
    {
        return self::get('APP_URL', 'http://localhost:8000');
    }
    
    public static function getAppName(): string
    {
        return self::get('APP_NAME', 'Система РЕПРО');
    }
    
    public static function getJwtSecret(): string
    {
        return self::get('JWT_SECRET', 'default_secret_change_in_production');
    }
}
