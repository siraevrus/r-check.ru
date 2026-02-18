<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ReproCRM\Config\Config;
use ReproCRM\Security\JWT;
use ReproCRM\Security\RateLimiter;

// Загружаем конфигурацию
Config::load();
JWT::init();
RateLimiter::init();

// Настройка CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Получаем путь и метод
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Убираем базовый путь /api/
$path = str_replace('/api/', '', parse_url($requestUri, PHP_URL_PATH));
$path = trim($path, '/');

// Разбиваем путь на части
$pathParts = explode('/', $path);
$endpoint = $pathParts[0] ?? '';

try {
    switch ($endpoint) {
        case 'auth':
            handleAuthRoutes($pathParts, $requestMethod);
            break;
        case 'admin':
            handleAdminRoutes($pathParts, $requestMethod);
            break;
        case 'doctor':
            handleDoctorRoutes($pathParts, $requestMethod);
            break;
        case 'files':
            handleFileRoutes($pathParts, $requestMethod);
            break;
        case 'password-reset':
            handlePasswordResetRoutes($pathParts, $requestMethod);
            break;
        default:
            \ReproCRM\Utils\Response::notFound('API endpoint не найден');
    }
} catch (Exception $e) {
    \ReproCRM\Utils\Response::error('Внутренняя ошибка сервера', 500);
}

function handleAuthRoutes($pathParts, $method) {
    $action = $pathParts[1] ?? '';
    $controller = new \ReproCRM\Controllers\AuthController();
    
    switch ($action) {
        case 'admin-login':
            if ($method === 'POST') {
                $controller->adminLogin();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'doctor-login':
            if ($method === 'POST') {
                $controller->doctorLogin();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'doctor-register':
            if ($method === 'POST') {
                $controller->doctorRegister();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'verify':
            if ($method === 'GET') {
                $controller->verifyToken();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'logout':
            if ($method === 'POST') {
                $controller->logout();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        default:
            \ReproCRM\Utils\Response::notFound();
    }
}

function handleAdminRoutes($pathParts, $method) {
    $action = $pathParts[1] ?? '';
    $controller = new \ReproCRM\Controllers\AdminController();
    
    switch ($action) {
        case 'dashboard':
            if ($method === 'GET') {
                $controller->getDashboard();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'promo-codes':
            if ($method === 'GET') {
                $controller->getPromoCodes();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'promo-code':
            if ($method === 'POST') {
                $controller->addPromoCode();
            } elseif ($method === 'DELETE') {
                $controller->deletePromoCode();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'doctors-report':
            if ($method === 'GET') {
                $controller->getDoctorsReport();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'doctor-details':
            if ($method === 'GET') {
                $controller->getDoctorDetails();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'reset-doctor-sales':
            if ($method === 'POST') {
                $controller->resetDoctorSales();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'admins':
            if ($method === 'GET') {
                $controller->getAdmins();
            } elseif ($method === 'POST') {
                $controller->addAdmin();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'admin':
            if ($method === 'DELETE') {
                $controller->deleteAdmin();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'profile':
            if ($method === 'PUT') {
                $controller->updateProfile();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        default:
            \ReproCRM\Utils\Response::notFound();
    }
}

function handleDoctorRoutes($pathParts, $method) {
    $action = $pathParts[1] ?? '';
    $controller = new \ReproCRM\Controllers\DoctorController();
    
    switch ($action) {
        case 'dashboard':
            if ($method === 'GET') {
                $controller->getDashboard();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'sales-report':
            if ($method === 'GET') {
                $controller->getSalesReport();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'sales-stats':
            if ($method === 'GET') {
                $controller->getSalesStats();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'profile':
            if ($method === 'GET') {
                $controller->getProfile();
            } elseif ($method === 'PUT') {
                $controller->updateProfile();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        default:
            \ReproCRM\Utils\Response::notFound();
    }
}

function handleFileRoutes($pathParts, $method) {
    $action = $pathParts[1] ?? '';
    $controller = new \ReproCRM\Controllers\FileController();
    
    switch ($action) {
        case 'upload-promo-codes':
            if ($method === 'POST') {
                $controller->uploadPromoCodes();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'upload-sales-report':
            if ($method === 'POST') {
                $controller->uploadSalesReport();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        default:
            \ReproCRM\Utils\Response::notFound();
    }
}

function handlePasswordResetRoutes($pathParts, $method) {
    $action = $pathParts[1] ?? '';
    $controller = new \ReproCRM\Controllers\PasswordResetController();
    
    switch ($action) {
        case 'request':
            if ($method === 'POST') {
                $controller->requestReset();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        case 'reset':
            if ($method === 'POST') {
                $controller->resetPassword();
            } else {
                \ReproCRM\Utils\Response::methodNotAllowed();
            }
            break;
        default:
            \ReproCRM\Utils\Response::notFound();
    }
}
