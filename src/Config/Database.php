<?php

namespace ReproCRM\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $dbType = $_ENV['DB_TYPE'] ?? 'mysql';
                
                if ($dbType === 'sqlite') {
                    // Используем SQLite
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
                    
                    // Инициализируем базу данных если она пустая
                    self::initSQLiteDatabase();
                    
                } else {
                    // Используем MySQL
                    $host = $_ENV['DB_HOST'] ?? 'localhost';
                    $dbname = $_ENV['DB_NAME'] ?? 'reprocrm_sales';
                    $username = $_ENV['DB_USER'] ?? 'root';
                    $password = $_ENV['DB_PASS'] ?? '';
                    
                    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
                    
                    self::$instance = new PDO($dsn, $username, $password, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                }
            } catch (PDOException $e) {
                throw new PDOException("Ошибка подключения к базе данных: " . $e->getMessage());
            }
        }
        
        return self::$instance;
    }
    
    private static function initSQLiteDatabase(): void
    {
        $db = self::$instance;
        
        // Проверяем, есть ли таблицы
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admins'");
        if ($stmt->fetch() === false) {
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
                                error_log("SQLite init error: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }
    }
    
    private function __construct() {}
    private function __clone() {}
}
