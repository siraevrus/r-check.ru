<?php

namespace ReproCRM\Config;

use PDO;
use PDOException;

class DatabaseSQLite
{
    private static ?PDO $instance = null;
    
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $dbPath = __DIR__ . '/../../database/reprocrm.db';
                $dsn = "sqlite:" . $dbPath;
                
                // Создаем директорию если не существует
                $dbDir = dirname($dbPath);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }
                
                self::$instance = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                
                // Включаем поддержку внешних ключей для SQLite
                self::$instance->exec('PRAGMA foreign_keys = ON');
                
            } catch (PDOException $e) {
                throw new PDOException("Ошибка подключения к базе данных SQLite: " . $e->getMessage());
            }
        }
        
        return self::$instance;
    }
    
    public static function initDatabase(): void
    {
        $db = self::getInstance();
        
        // Читаем и выполняем SQL схему
        $sqlFile = __DIR__ . '/../../database/schema.sqlite.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $statements = explode(';', $sql);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    try {
                        $db->exec($statement);
                    } catch (PDOException $e) {
                        // Игнорируем ошибки для операций, которые могут уже существовать
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            throw $e;
                        }
                    }
                }
            }
        }
    }
    
    private function __construct() {}
    private function __clone() {}
}
