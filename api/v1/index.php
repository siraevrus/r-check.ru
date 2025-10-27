<?php
/**
 * REST API v1 для системы РЕПРО
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Utils/ApiResponse.php';

use ReproCRM\Config\Database;
use ReproCRM\Security\JWT;
use ReproCRM\Security\RateLimiter;
use ReproCRM\Utils\ApiResponse;

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
$response = new ApiResponse();

// Rate limiting
$rateLimiter = new RateLimiter($pdo);
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$endpoint = $_SERVER['REQUEST_URI'];

if (!$rateLimiter->isAllowed($clientIp, $endpoint)) {
    $response->error('Too many requests', 429);
    exit;
}

// Получение маршрута
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Парсинг маршрута
$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Удаляем 'api/v1' из начала пути
if (count($pathParts) >= 2 && $pathParts[0] === 'api' && $pathParts[1] === 'v1') {
    $pathParts = array_slice($pathParts, 2);
}

$endpoint = implode('/', $pathParts);

// Аутентификация для защищенных endpoints
$publicEndpoints = ['auth/login', 'auth/register', 'health'];
$requiresAuth = !in_array($endpoint, $publicEndpoints);

if ($requiresAuth) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = null;
    
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
    }
    
    if (!$token) {
        $response->error('Authorization token required', 401);
        exit;
    }
    
    try {
        $jwt = new JWT();
        $decoded = $jwt->validateToken($token);
        
        if (!$decoded) {
            $response->error('Invalid or expired token', 401);
            exit;
        }
        
        // Добавляем информацию о пользователе в контекст
        $GLOBALS['current_user'] = $decoded;
        
    } catch (Exception $e) {
        $response->error('Authentication failed: ' . $e->getMessage(), 401);
        exit;
    }
}

// Маршрутизация
try {
    switch ($endpoint) {
        case 'health':
            $response->success([
                'status' => 'healthy',
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '1.0.0'
            ]);
            break;
            
        case 'auth/login':
            handleAuthLogin($pdo, $response);
            break;
            
        case 'auth/register':
            handleAuthRegister($pdo, $response);
            break;
            
        case 'promo-codes':
            handlePromoCodes($pdo, $response, $requestMethod);
            break;
            
        case 'doctors':
            handleDoctors($pdo, $response, $requestMethod);
            break;
            
        case 'sales':
            handleSales($pdo, $response, $requestMethod);
            break;
            
        case 'analytics/dashboard':
            handleAnalytics($pdo, $response);
            break;
            
        case 'export/excel':
            handleExport($pdo, $response);
            break;
            
        default:
            $response->error('Endpoint not found', 404);
            break;
    }
} catch (Exception $e) {
    $response->error('Internal server error: ' . $e->getMessage(), 500);
}

/**
 * Обработка входа в систему
 */
function handleAuthLogin($pdo, $response) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response->error('Method not allowed', 405);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    $userType = $input['user_type'] ?? 'admin'; // 'admin' или 'doctor'
    
    if (empty($email) || empty($password)) {
        $response->error('Email and password are required', 400);
        return;
    }
    
    try {
        $table = $userType === 'admin' ? 'admins' : 'doctors';
        $stmt = $pdo->prepare("SELECT id, email, password_hash FROM {$table} WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $response->error('Invalid credentials', 401);
            return;
        }
        
        $jwt = new JWT();
        $token = $jwt->createToken([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'user_type' => $userType
        ]);
        
        $response->success([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'user_type' => $userType
            ]
        ]);
        
    } catch (Exception $e) {
        $response->error('Login failed: ' . $e->getMessage(), 500);
    }
}

/**
 * Обработка регистрации
 */
function handleAuthRegister($pdo, $response) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response->error('Method not allowed', 405);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    $userType = $input['user_type'] ?? 'doctor';
    
    if (empty($email) || empty($password)) {
        $response->error('Email and password are required', 400);
        return;
    }
    
    try {
        $table = $userType === 'admin' ? 'admins' : 'doctors';
        
        // Проверяем, существует ли пользователь
        $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $response->error('User already exists', 409);
            return;
        }
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        if ($userType === 'doctor') {
            $fullName = $input['full_name'] ?? '';
            $city = $input['city'] ?? '';
            $promoCodeId = $input['promo_code_id'] ?? null;
            
            if (empty($fullName) || empty($city) || !$promoCodeId) {
                $response->error('Full name, city, and promo code are required for doctors', 400);
                return;
            }
            
            $stmt = $pdo->prepare("INSERT INTO doctors (promo_code_id, full_name, email, city, password_hash, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))");
            $stmt->execute([$promoCodeId, $fullName, $email, $city, $passwordHash]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO admins (email, password_hash, created_at) VALUES (?, ?, datetime('now'))");
            $stmt->execute([$email, $passwordHash]);
        }
        
        $userId = $pdo->lastInsertId();
        
        $jwt = new JWT();
        $token = $jwt->createToken([
            'user_id' => $userId,
            'email' => $email,
            'user_type' => $userType
        ]);
        
        $response->success([
            'token' => $token,
            'user' => [
                'id' => $userId,
                'email' => $email,
                'user_type' => $userType
            ]
        ], 201);
        
    } catch (Exception $e) {
        $response->error('Registration failed: ' . $e->getMessage(), 500);
    }
}

/**
 * Обработка промокодов
 */
function handlePromoCodes($pdo, $response, $method) {
    switch ($method) {
        case 'GET':
            // Получение списка промокодов
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = ($page - 1) * $limit;
            
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
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $promoCodes = $stmt->fetchAll();
            
            // Получаем общее количество
            $countSql = "SELECT COUNT(*) FROM promo_codes pc LEFT JOIN doctors d ON d.promo_code_id = pc.id {$whereClause}";
            $countParams = array_slice($params, 0, -2); // Убираем limit и offset
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetchColumn();
            
            $response->success([
                'data' => $promoCodes,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'POST':
            // Создание нового промокода
            $input = json_decode(file_get_contents('php://input'), true);
            $code = strtoupper(trim($input['code'] ?? ''));
            
            if (empty($code)) {
                $response->error('Promo code is required', 400);
                return;
            }
            
            try {
                // Проверяем уникальность
                $stmt = $pdo->prepare("SELECT id FROM promo_codes WHERE code = ?");
                $stmt->execute([$code]);
                
                if ($stmt->fetch()) {
                    $response->error('Promo code already exists', 409);
                    return;
                }
                
                $stmt = $pdo->prepare("INSERT INTO promo_codes (code, status, created_at, updated_at) VALUES (?, 'unregistered', datetime('now'), datetime('now'))");
                $stmt->execute([$code]);
                
                $promoId = $pdo->lastInsertId();
                
                $response->success([
                    'id' => $promoId,
                    'code' => $code,
                    'status' => 'unregistered'
                ], 201);
                
            } catch (Exception $e) {
                $response->error('Failed to create promo code: ' . $e->getMessage(), 500);
            }
            break;
            
        default:
            $response->error('Method not allowed', 405);
            break;
    }
}

/**
 * Обработка врачей
 */
function handleDoctors($pdo, $response, $method) {
    switch ($method) {
        case 'GET':
            $search = $_GET['search'] ?? '';
            $city = $_GET['city'] ?? '';
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = ($page - 1) * $limit;
            
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
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $doctors = $stmt->fetchAll();
            
            $response->success(['data' => $doctors]);
            break;
            
        default:
            $response->error('Method not allowed', 405);
            break;
    }
}

/**
 * Обработка продаж
 */
function handleSales($pdo, $response, $method) {
    switch ($method) {
        case 'GET':
            $promoCode = $_GET['promo_code'] ?? '';
            $dateFrom = $_GET['date_from'] ?? '';
            $dateTo = $_GET['date_to'] ?? '';
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = ($page - 1) * $limit;
            
            $whereConditions = [];
            $params = [];
            
            if (!empty($promoCode)) {
                $whereConditions[] = "pc.code = ?";
                $params[] = $promoCode;
            }
            
            if (!empty($dateFrom)) {
                $whereConditions[] = "s.sale_date >= ?";
                $params[] = $dateFrom;
            }
            
            if (!empty($dateTo)) {
                $whereConditions[] = "s.sale_date <= ?";
                $params[] = $dateTo;
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $sql = "
                SELECT 
                    s.id,
                    s.product_name,
                    s.sale_date,
                    s.quantity,
                    s.created_at,
                    pc.code as promo_code
                FROM sales s
                LEFT JOIN promo_codes pc ON s.promo_code_id = pc.id
                {$whereClause}
                ORDER BY s.sale_date DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $sales = $stmt->fetchAll();
            
            $response->success(['data' => $sales]);
            break;
            
        default:
            $response->error('Method not allowed', 405);
            break;
    }
}

/**
 * Обработка аналитики
 */
function handleAnalytics($pdo, $response) {
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
        
        $response->success([
            'stats' => [
                'total_promo_codes' => $totalPromoCodes,
                'total_doctors' => $totalDoctors,
                'total_sales' => $totalSales,
                'unregistered_codes' => $unregisteredCodes
            ],
            'top_doctors' => $topDoctors
        ]);
        
    } catch (Exception $e) {
        $response->error('Failed to fetch analytics: ' . $e->getMessage(), 500);
    }
}

/**
 * Обработка экспорта
 */
function handleExport($pdo, $response) {
    $type = $_GET['type'] ?? '';
    
    if (empty($type)) {
        $response->error('Export type is required', 400);
        return;
    }
    
    // Здесь можно добавить логику экспорта
    // Пока возвращаем информацию о доступных типах
    $availableTypes = ['promo_codes', 'doctors', 'sales'];
    
    if (!in_array($type, $availableTypes)) {
        $response->error('Invalid export type. Available types: ' . implode(', ', $availableTypes), 400);
        return;
    }
    
    $response->success([
        'message' => 'Export endpoint ready',
        'type' => $type,
        'url' => "/export_excel.php?type={$type}"
    ]);
}
?>
