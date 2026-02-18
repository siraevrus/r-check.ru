<?php
/**
 * Простой REST API для системы РЕПРО
 */

require_once __DIR__ . '/vendor/autoload.php';

use ReproCRM\Config\Database;
use ReproCRM\Security\JWT;

// Настройки
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Обработка OPTIONS запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Инициализация
$_ENV['DB_TYPE'] = 'sqlite';
$db = Database::getInstance();
$pdo = $db;

// Функция для отправки JSON ответа
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Функция для отправки ошибки
function sendError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => $message,
            'code' => $statusCode
        ],
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Получение маршрута
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Парсинг маршрута
$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Удаляем 'api' и 'simple.php' из начала пути
if (count($pathParts) >= 2 && $pathParts[0] === 'api' && $pathParts[1] === 'simple.php') {
    $pathParts = array_slice($pathParts, 2);
}

$endpoint = implode('/', $pathParts);

// Маршрутизация
try {
    switch ($endpoint) {
        case 'health':
            sendResponse([
                'success' => true,
                'data' => [
                    'status' => 'healthy',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'version' => '1.0.0'
                ]
            ]);
            break;
            
        case 'auth/login':
            handleAuthLogin($pdo);
            break;

        case 'doctor-login':
            handleDoctorLogin($pdo);
            break;
            
        case 'promo-codes':
            handlePromoCodes($pdo, $requestMethod);
            break;
            
        case 'doctors':
            handleDoctors($pdo, $requestMethod);
            break;
            
        case 'analytics/dashboard':
            handleAnalytics($pdo);
            break;
            
        default:
            sendError('Endpoint not found', 404);
            break;
    }
} catch (Exception $e) {
    sendError('Internal server error: ' . $e->getMessage(), 500);
}

/**
 * Обработка входа в систему
 */
function handleAuthLogin($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    $userType = $input['user_type'] ?? 'admin';
    
    if (empty($email) || empty($password)) {
        sendError('Email and password are required', 400);
        return;
    }
    
    try {
        $table = $userType === 'admin' ? 'admins' : 'doctors';
        $stmt = $pdo->prepare("SELECT id, email, password_hash FROM {$table} WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            sendError('Invalid credentials', 401);
            return;
        }
        
        $jwt = new JWT();
        $token = $jwt->createToken([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'user_type' => $userType
        ]);
        
        sendResponse([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'user_type' => $userType
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        sendError('Login failed: ' . $e->getMessage(), 500);
    }
}

/**
 * Обработка промокодов
 */
function handlePromoCodes($pdo, $method) {
    switch ($method) {
        case 'GET':
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $limit = (int)($_GET['limit'] ?? 50);
            
            $whereConditions = [];
            $params = [];
            
            if (!empty($search)) {
                $whereConditions[] = "(pc.code LIKE ? OR d.full_name LIKE ? OR d.email LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            }
            
            if (!empty($status)) {
                $whereConditions[] = "pc.status = ?";
                $params[] = $status;
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $sql = "
                SELECT 
                    pc.id,
                    pc.code,
                    pc.status,
                    pc.created_at,
                    pc.updated_at,
                    d.full_name as user_name,
                    d.email as user_email,
                    d.city as user_city,
                    COUNT(s.id) as sales_count
                FROM promo_codes pc
                LEFT JOIN users d ON d.promo_code_id = pc.id
                LEFT JOIN sales s ON s.promo_code_id = pc.id
                {$whereClause}
                GROUP BY pc.id, pc.code, pc.status, pc.created_at, pc.updated_at, d.full_name, d.email, d.city
                ORDER BY pc.created_at DESC
                LIMIT ?
            ";
            
            $params[] = $limit;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $promoCodes = $stmt->fetchAll();
            
            sendResponse([
                'success' => true,
                'data' => $promoCodes
            ]);
            break;
            
        default:
            sendError('Method not allowed', 405);
            break;
    }
}

/**
 * Обработка врачей
 */
function handleDoctors($pdo, $method) {
    switch ($method) {
        case 'GET':
            $search = $_GET['search'] ?? '';
            $city = $_GET['city'] ?? '';
            $limit = (int)($_GET['limit'] ?? 50);
            
            $whereConditions = [];
            $params = [];
            
            if (!empty($search)) {
                $whereConditions[] = "(d.full_name LIKE ? OR d.email LIKE ? OR pc.code LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            }
            
            if (!empty($city)) {
                $whereConditions[] = "d.city = ?";
                $params[] = $city;
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $sql = "
                SELECT 
                    d.id,
                    d.full_name,
                    d.email,
                    d.city,
                    d.created_at,
                    pc.code as promo_code,
                    COUNT(s.id) as sales_count,
                    COALESCE(SUM(s.quantity), 0) as total_quantity
                FROM users d
                LEFT JOIN promo_codes pc ON d.promo_code_id = pc.id
                LEFT JOIN sales s ON s.promo_code_id = pc.id
                {$whereClause}
                GROUP BY d.id, d.full_name, d.email, d.city, d.created_at, pc.code
                ORDER BY d.created_at DESC
                LIMIT ?
            ";
            
            $params[] = $limit;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $doctors = $stmt->fetchAll();
            
            sendResponse([
                'success' => true,
                'data' => $doctors
            ]);
            break;
            
        default:
            sendError('Method not allowed', 405);
            break;
    }
}

/**
 * Обработка аналитики
 */
function handleAnalytics($pdo) {
    try {
        // Общая статистика
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM promo_codes");
        $totalPromoCodes = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $totalUsers = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM sales");
        $totalSales = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM promo_codes WHERE status = 'unregistered'");
        $unregisteredCodes = $stmt->fetch()['count'];
        
        // Топ врачей
        $stmt = $pdo->query("
            SELECT 
                d.full_name,
                d.city,
                pc.code as promo_code,
                COUNT(s.id) as sales_count
            FROM users d
            LEFT JOIN promo_codes pc ON d.promo_code_id = pc.id
            LEFT JOIN sales s ON s.promo_code_id = pc.id
            GROUP BY d.id, d.full_name, d.city, pc.code
            ORDER BY sales_count DESC
            LIMIT 10
        ");
        $topDoctors = $stmt->fetchAll();
        
        sendResponse([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_promo_codes' => $totalPromoCodes,
                    'total_doctors' => $totalDoctors,
                    'total_sales' => $totalSales,
                    'unregistered_codes' => $unregisteredCodes
                ],
                'top_doctors' => $topDoctors
            ]
        ]);
        
    } catch (Exception $e) {
        sendError('Failed to fetch analytics: ' . $e->getMessage(), 500);
    }
}

/**
 * Обработка входа врача в систему
 */
function handleDoctorLogin($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        sendError('Email and password are required', 400);
        return;
    }

    try {
        // Ищем врача в базе данных
        $stmt = $pdo->prepare("SELECT id, email, password_hash, full_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $doctor = $stmt->fetch();

        if (!$doctor || !password_verify($password, $doctor['password_hash'])) {
            sendError('Invalid email or password', 401);
            return;
        }

        // Генерируем токен для врача
        $jwt = new JWT();
        $token = $jwt->generate([
            'user_id' => $doctor['id'],
            'email' => $doctor['email'],
            'user_type' => 'doctor',
            'exp' => time() + (24 * 60 * 60) // 24 часа
        ]);

        sendResponse([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $doctor['id'],
                    'email' => $doctor['email'],
                    'full_name' => $doctor['full_name'],
                    'user_type' => 'doctor'
                ]
            ]
        ]);

    } catch (Exception $e) {
        sendError('Login failed: ' . $e->getMessage(), 500);
    }
}
?>
