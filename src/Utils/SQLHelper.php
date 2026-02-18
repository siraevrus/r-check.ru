<?php

namespace ReproCRM\Utils;

class SQLHelper
{
    public static function now(): string
    {
        $dbType = $_ENV['DB_TYPE'] ?? 'mysql';
        return $dbType === 'sqlite' ? "datetime('now')" : "NOW()";
    }
    
    public static function dateFormat(string $column, string $format): string
    {
        $dbType = $_ENV['DB_TYPE'] ?? 'mysql';
        if ($dbType === 'sqlite') {
            return "strftime('{$format}', {$column})";
        } else {
            return "DATE_FORMAT({$column}, '{$format}')";
        }
    }
    
    public static function limitOffset(int $limit, int $offset): string
    {
        $dbType = $_ENV['DB_TYPE'] ?? 'mysql';
        if ($dbType === 'sqlite') {
            return "LIMIT {$limit} OFFSET {$offset}";
        } else {
            return "LIMIT {$limit} OFFSET {$offset}";
        }
    }
}
