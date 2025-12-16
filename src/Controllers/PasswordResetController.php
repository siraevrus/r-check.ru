<?php

namespace ReproCRM\Controllers;

use ReproCRM\Models\Admin;
use ReproCRM\Models\User;
use ReproCRM\Utils\Response;
use ReproCRM\Security\RateLimiter;
use ReproCRM\Config\Database;

class PasswordResetController
{
    public function requestReset(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['email']) || empty(trim($data['email']))) {
            Response::error('Email обязателен', 400);
            return;
        }
        
        $email = trim($data['email']);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Некорректный email', 400);
            return;
        }
        
        // Проверка rate limit
        $clientIP = RateLimiter::getClientIP();
        if (!RateLimiter::checkLimit($clientIP, 'password_reset')) {
            Response::error('Слишком много запросов на восстановление пароля. Попробуйте позже.', 429);
            return;
        }
        
        // Ищем пользователя среди врачей и администраторов
        $user = null;
        $userType = null;
        
        $doctor = User::findByEmail($email);
        if ($doctor) {
            $user = $doctor;
            $userType = 'doctor';
        } else {
            $admin = Admin::findByEmail($email);
            if ($admin) {
                $user = $admin;
                $userType = 'admin';
            }
        }
        
        if (!$user) {
            // Всегда возвращаем успех для безопасности
            Response::success(['message' => 'Если пользователь с таким email существует, инструкции отправлены на почту']);
            return;
        }
        
        // Генерируем токен
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 час
        
        // Сохраняем токен в базе
        $db = Database::getInstance();
        
        // Удаляем старые токены для этого email
        $stmt = $db->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);
        
        // Создаем новый токен
        $stmt = $db->prepare("
            INSERT INTO password_resets (email, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$email, $token, $expiresAt]);
        
        // Отправляем email
        $resetUrl = $_ENV['APP_URL'] . "/reset-password?token={$token}";
        
        if ($this->sendResetEmail($email, $resetUrl, $userType)) {
            Response::success(['message' => 'Инструкции по восстановлению пароля отправлены на ваш email']);
        } else {
            Response::error('Ошибка при отправке email', 500);
        }
    }
    
    public function resetPassword(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['token']) || !isset($data['password'])) {
            Response::error('Токен и новый пароль обязательны', 400);
            return;
        }
        
        $token = trim($data['token']);
        $password = $data['password'];
        
        if (strlen($password) < 6) {
            Response::error('Пароль должен содержать минимум 6 символов', 400);
            return;
        }
        
        // Проверяем токен
        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("
            SELECT * FROM password_resets 
            WHERE token = ? AND expires_at > ? AND used_at IS NULL
        ");
        $stmt->execute([$token, $now]);
        $resetRecord = $stmt->fetch();
        
        if (!$resetRecord) {
            Response::error('Неверный или истекший токен', 400);
            return;
        }
        
        $email = $resetRecord['email'];
        
        // Ищем пользователя
        $user = null;
        
        $doctor = User::findByEmail($email);
        if ($doctor) {
            $user = $doctor;
        } else {
            $admin = Admin::findByEmail($email);
            if ($admin) {
                $user = $admin;
            }
        }
        
        if (!$user) {
            Response::error('Пользователь не найден', 404);
            return;
        }
        
        // Обновляем пароль
        $user->setPassword($password);
        
        if (!$user->save()) {
            Response::error('Ошибка при обновлении пароля', 500);
            return;
        }
        
        // Помечаем токен как использованный
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("UPDATE password_resets SET used_at = ? WHERE token = ?");
        $stmt->execute([$now, $token]);
        
        Response::success(['message' => 'Пароль успешно изменен']);
    }
    
    private function sendResetEmail(string $email, string $resetUrl, string $userType): bool
    {
        $subject = 'Восстановление пароля - ' . ($_ENV['APP_NAME'] ?? 'Система учета продаж');
        
        $message = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <title>Восстановление пароля</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #667eea; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .button { background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                    .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Восстановление пароля</h1>
                    </div>
                    <div class='content'>
                        <p>Здравствуйте!</p>
                        <p>Вы запросили восстановление пароля для вашего аккаунта в системе " . ($_ENV['APP_NAME'] ?? 'Система учета продаж') . ".</p>
                        <p>Для создания нового пароля нажмите на кнопку ниже:</p>
                        <p><a href='{$resetUrl}' class='button'>Восстановить пароль</a></p>
                        <p>Или скопируйте и вставьте эту ссылку в браузер:</p>
                        <p style='word-break: break-all; background: #f0f0f0; padding: 10px; border-radius: 5px;'>{$resetUrl}</p>
                        <p><strong>Ссылка действительна в течение 1 часа.</strong></p>
                        <p>Если вы не запрашивали восстановление пароля, просто проигнорируйте это письмо.</p>
                    </div>
                    <div class='footer'>
                        <p>С уважением,<br>Команда " . ($_ENV['APP_NAME'] ?? 'Система учета продаж') . "</p>
                        <p>Это автоматическое сообщение. Пожалуйста, не отвечайте на него.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        // Используем EmailNotifier для надежной отправки через PHPMailer
        $emailNotifier = new \ReproCRM\Utils\EmailNotifier();
        return $emailNotifier->send($email, $subject, $message, true);
    }
}
