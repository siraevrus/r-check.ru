<?php

namespace ReproCRM\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * Класс для отправки email уведомлений
 */
class EmailNotifier
{
    private $config;
    
    public function __construct($config = [])
    {
        $this->config = array_merge([
            'smtp_host' => $_ENV['MAIL_HOST'] ?? $_ENV['SMTP_HOST'] ?? 'smtp.mail.ru',
            'smtp_port' => $_ENV['MAIL_PORT'] ?? $_ENV['SMTP_PORT'] ?? 465,
            'smtp_username' => $_ENV['MAIL_USERNAME'] ?? $_ENV['SMTP_USER'] ?? '',
            'smtp_password' => $_ENV['MAIL_PASSWORD'] ?? $_ENV['SMTP_PASS'] ?? '',
            'from_email' => $_ENV['MAIL_FROM_ADDRESS'] ?? $_ENV['SMTP_FROM'] ?? '',
            'from_name' => $_ENV['MAIL_FROM_NAME'] ?? $_ENV['APP_NAME'] ?? 'Система учета продаж',
            'smtp_secure' => $_ENV['MAIL_ENCRYPTION'] ?? 'ssl', // ssl или tls
            'smtp_auth' => true,
            'smtp_debug' => $_ENV['MAIL_DEBUG'] ?? 0 // 0 = off, 1 = client, 2 = client and server
        ], $config);
    }
    
    /**
     * Отправка email уведомления
     */
    public function send($to, $subject, $message, $isHtml = true)
    {
        try {
            return $this->sendWithPhpMailer($to, $subject, $message, $isHtml);
        } catch (\Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Отправка с использованием PHPMailer
     */
    private function sendWithPhpMailer($to, $subject, $message, $isHtml)
    {
        $mail = new PHPMailer(true);
        
        try {
            // Настройки SMTP
            $mail->isSMTP();
            $mail->Host = $this->config['smtp_host'];
            $mail->SMTPAuth = $this->config['smtp_auth'];
            $mail->Username = $this->config['smtp_username'];
            $mail->Password = $this->config['smtp_password'];
            $mail->SMTPSecure = $this->config['smtp_secure'];
            $mail->Port = $this->config['smtp_port'];
            $mail->CharSet = 'UTF-8';
            
            // Отладка (по умолчанию выключена)
            $mail->SMTPDebug = $this->config['smtp_debug'];
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer [$level]: $str");
            };
            
            // Отправитель
            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addReplyTo($this->config['from_email'], $this->config['from_name']);
            
            // Получатель
            $mail->addAddress($to);
            
            // Контент
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            if ($isHtml) {
                $mail->AltBody = strip_tags($message);
            }
            
            return $mail->send();
            
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            throw $e;
        }
    }
    
    /**
     * Отправка уведомления о новом промокоде
     */
    public function sendPromoCodeCreated($doctorEmail, $doctorName, $promoCode)
    {
        $subject = "Ваш промокод готов к использованию";
        $message = $this->getPromoCodeCreatedTemplate($doctorName, $promoCode);
        
        return $this->send($doctorEmail, $subject, $message, true);
    }
    
    /**
     * Отправка уведомления о продажах
     */
    public function sendSalesReport($doctorEmail, $doctorName, $salesData)
    {
        $subject = "Отчет по вашим продажам";
        $message = $this->getSalesReportTemplate($doctorName, $salesData);
        
        return $this->send($doctorEmail, $subject, $message, true);
    }
    
    /**
     * Отправка уведомления администратору
     */
    public function sendAdminNotification($adminEmail, $subject, $message)
    {
        $message = $this->getAdminNotificationTemplate($message);
        
        return $this->send($adminEmail, $subject, $message, true);
    }
    
    /**
     * Шаблон для уведомления о создании промокода
     */
    private function getPromoCodeCreatedTemplate($doctorName, $promoCode)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #667eea; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .promo-code { background: #fff; border: 2px solid #667eea; padding: 20px; text-align: center; margin: 20px 0; }
                .promo-code h2 { color: #667eea; margin: 0; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Система РЕПРО</h1>
                </div>
                <div class='content'>
                    <p>Здравствуйте, <strong>{$doctorName}</strong>!</p>
                    <p>Ваш промокод успешно создан и готов к использованию:</p>
                    
                    <div class='promo-code'>
                        <h2>{$promoCode}</h2>
                    </div>
                    
                    <p>Используйте этот промокод для отслеживания ваших продаж в системе.</p>
                    <p>Для входа в систему перейдите по ссылке: <a href='" . ($_ENV['APP_URL'] ?? 'http://r-check.ru') . "/doctor_panel.php'>Система РЕПРО</a></p>
                </div>
                <div class='footer'>
                    <p>Это автоматическое сообщение. Пожалуйста, не отвечайте на него.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Шаблон для отчета о продажах
     */
    private function getSalesReportTemplate($doctorName, $salesData)
    {
        $totalSales = count($salesData);
        $totalQuantity = array_sum(array_column($salesData, 'quantity'));
        
        $salesTable = '<table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">';
        $salesTable .= '<tr style="background: #f0f0f0;"><th>Продукт</th><th>Дата</th><th>Количество</th></tr>';
        
        foreach ($salesData as $sale) {
            $salesTable .= "<tr><td>{$sale['product_name']}</td><td>{$sale['sale_date']}</td><td>{$sale['quantity']}</td></tr>";
        }
        
        $salesTable .= '</table>';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .stats { background: #fff; padding: 15px; margin: 15px 0; border-left: 4px solid #28a745; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Отчет по продажам</h1>
                </div>
                <div class='content'>
                    <p>Здравствуйте, <strong>{$doctorName}</strong>!</p>
                    <p>Ваш еженедельный отчет по продажам:</p>
                    
                    <div class='stats'>
                        <h3>Статистика:</h3>
                        <p><strong>Всего продаж:</strong> {$totalSales}</p>
                        <p><strong>Общее количество товаров:</strong> {$totalQuantity}</p>
                    </div>
                    
                    <h3>Детализация продаж:</h3>
                    {$salesTable}
                    
                    <p>Для просмотра полной статистики перейдите в систему: <a href='" . ($_ENV['APP_URL'] ?? 'http://r-check.ru') . "/user_dashboard.php'>Дашборд пользователя</a></p>
                </div>
                <div class='footer'>
                    <p>Это автоматическое сообщение. Пожалуйста, не отвечайте на него.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Шаблон для уведомлений администратора
     */
    private function getAdminNotificationTemplate($message)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .alert { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 15px 0; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Система РЕПРО - Уведомление</h1>
                </div>
                <div class='content'>
                    <div class='alert'>
                        {$message}
                    </div>
                    <p>Для просмотра подробной информации перейдите в административную панель: <a href='" . ($_ENV['APP_URL'] ?? 'http://r-check.ru') . "/admin_working.php'>Админ панель</a></p>
                </div>
                <div class='footer'>
                    <p>Это автоматическое сообщение системы РЕПРО.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Проверка конфигурации email
     */
    public function testConfiguration()
    {
        $testEmail = 'test@example.com';
        $subject = 'Тест системы уведомлений';
        $message = '<p>Это тестовое сообщение для проверки работы системы уведомлений.</p>';
        
        try {
            $result = $this->send($testEmail, $subject, $message, true);
            return [
                'success' => $result,
                'message' => $result ? 'Email конфигурация работает корректно' : 'Ошибка отправки email'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Ошибка конфигурации: ' . $e->getMessage()
            ];
        }
    }
}
?>
