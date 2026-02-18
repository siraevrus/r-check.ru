<?php

// Создание PHP сессии для врача
// Настройка параметров сессии для корректной работы
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_only_cookies', 1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$token = $input['token'] ?? '';
$user = $input['user'] ?? null;

if (empty($email) || empty($token)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Создаем сессию для пользователя
$_SESSION['user_logged_in'] = true;
$_SESSION['user_email'] = $email;
$_SESSION['user_token'] = $token;
$_SESSION['user_id'] = $user['id'] ?? null;
$_SESSION['user_name'] = $user['full_name'] ?? '';

// Принудительно сохраняем сессию
session_write_close();

echo json_encode([
    'success' => true,
    'message' => 'Session created successfully',
    'session_id' => session_id(),
    'session_data' => [
        'email' => $email,
        'user_id' => $user['id'] ?? null,
        'logged_in' => true
    ]
]);



