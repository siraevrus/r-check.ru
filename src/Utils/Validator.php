<?php

namespace ReproCRM\Utils;

/**
 * Класс валидации данных для системы РЕПРО
 */
class Validator
{
    private $errors = [];
    
    /**
     * Очистить ошибки
     */
    public function clearErrors()
    {
        $this->errors = [];
    }
    
    /**
     * Получить все ошибки
     */
    public function getErrors()
    {
        return $this->errors;
    }
    
    /**
     * Проверить, есть ли ошибки
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }
    
    /**
     * Добавить ошибку
     */
    public function addError($field, $message)
    {
        $this->errors[$field][] = $message;
    }
    
    /**
     * Получить ошибки для конкретного поля
     */
    public function getFieldErrors($field)
    {
        return $this->errors[$field] ?? [];
    }
    
    /**
     * Валидация ФИО
     */
    public function validateFullName($fullName)
    {
        if (empty($fullName)) {
            $this->addError('full_name', 'ФИО не может быть пустым');
            return false;
        }
        
        if (!preg_match('/^[а-яёА-ЯЁ\s\-]+$/u', $fullName)) {
            $this->addError('full_name', 'ФИО должно содержать только буквы, пробелы и дефисы');
            return false;
        }
        
        if (strlen(trim($fullName)) < 2) {
            $this->addError('full_name', 'ФИО должно содержать минимум 2 символа');
            return false;
        }
        
        if (strlen($fullName) > 255) {
            $this->addError('full_name', 'ФИО слишком длинное (максимум 255 символов)');
            return false;
        }
        
        return true;
    }
    
    /**
     * Валидация промокода
     */
    public function validatePromoCode($code)
    {
        $this->clearErrors();
        
        if (empty($code)) {
            $this->addError('code', 'Промокод не может быть пустым');
            return false;
        }
        
        $length = strlen($code);
        if ($length < 5 || $length > 10) {
            $this->addError('code', 'Промокод должен содержать от 5 до 10 символов');
            return false;
        }
        
        if (!preg_match('/^[A-Z0-9!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]+$/', $code)) {
            $this->addError('code', 'Промокод должен содержать только заглавные буквы, цифры и символы');
            return false;
        }
        
        return true;
    }
    
    /**
     * Валидация email
     */
    public function validateEmail($email)
    {
        if (empty($email)) {
            $this->addError('email', 'Email не может быть пустым');
            return false;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError('email', 'Некорректный формат email');
            return false;
        }
        
        if (strlen($email) > 255) {
            $this->addError('email', 'Email слишком длинный (максимум 255 символов)');
            return false;
        }
        
        return true;
    }
    
    /**
     * Валидация пароля с мягкими требованиями безопасности
     */
    public function validatePassword($password, $minLength = 6)
    {
        if (empty($password)) {
            $this->addError('password', 'Пароль не может быть пустым');
            return false;
        }
        
        if (strlen($password) < $minLength) {
            $this->addError('password', "Пароль должен содержать минимум {$minLength} символов");
            return false;
        }
        
        if (strlen($password) > 128) {
            $this->addError('password', 'Пароль слишком длинный (максимум 128 символов)');
            return false;
        }
        
        // Проверка, что пароль содержит только разрешенные символы
        if (!preg_match('/^[a-zA-Z0-9!@#$%^&*()_+\-=\[\]{}|;:,.<>?]+$/', $password)) {
            $this->addError('password', 'Пароль может содержать только английские буквы, цифры и специальные символы (!@#$%^&*()_+-=[]{}|;:,.<>?)');
            return false;
        }
        
        return true;
    }
    
    /**
     * Валидация имени врача
     */
    public function validateDoctorName($name)
    {
        if (empty($name)) {
            $this->addError('full_name', 'Имя врача не может быть пустым');
            return false;
        }
        
        if (strlen($name) < 2) {
            $this->addError('full_name', 'Имя врача должно содержать минимум 2 символа');
            return false;
        }
        
        if (strlen($name) > 255) {
            $this->addError('full_name', 'Имя врача слишком длинное (максимум 255 символов)');
            return false;
        }
        
        if (!preg_match('/^[а-яёА-ЯЁa-zA-Z\s\-\.]+$/u', $name)) {
            $this->addError('full_name', 'Имя врача может содержать только буквы, пробелы, дефисы и точки');
            return false;
        }
        
        return true;
    }
    
    /**
     * Валидация города
     */
    public function validateCity($city)
    {
        if (empty($city)) {
            $this->addError('city', 'Город не может быть пустым');
            return false;
        }
        
        if (strlen($city) < 2) {
            $this->addError('city', 'Название города должно содержать минимум 2 символа');
            return false;
        }
        
        if (strlen($city) > 100) {
            $this->addError('city', 'Название города слишком длинное (максимум 100 символов)');
            return false;
        }
        
        if (!preg_match('/^[а-яёА-ЯЁa-zA-Z\s\-\.]+$/u', $city)) {
            $this->addError('city', 'Название города может содержать только буквы, пробелы, дефисы и точки');
            return false;
        }
        
        return true;
    }
    
    /**
     * Валидация названия продукта
     */
    public function validateProductName($productName)
    {
        if (empty($productName)) {
            $this->addError('product_name', 'Название продукта не может быть пустым');
            return false;
        }
        
        if (strlen($productName) < 2) {
            $this->addError('product_name', 'Название продукта должно содержать минимум 2 символа');
            return false;
        }
        
        if (strlen($productName) > 255) {
            $this->addError('product_name', 'Название продукта слишком длинное (максимум 255 символов)');
            return false;
        }
        
        return true;
    }
    
    /**
     * Валидация количества товара
     */
    public function validateQuantity($quantity)
    {
        if (empty($quantity)) {
            $this->addError('quantity', 'Количество не может быть пустым');
            return false;
        }
        
        if (!is_numeric($quantity)) {
            $this->addError('quantity', 'Количество должно быть числом');
            return false;
        }
        
        $quantity = (int)$quantity;
        
        if ($quantity <= 0) {
            $this->addError('quantity', 'Количество должно быть больше 0');
            return false;
        }
        
        if ($quantity > 10000) {
            $this->addError('quantity', 'Количество слишком большое (максимум 10000)');
            return false;
        }
        
        return true;
    }
    
    /**
     * Валидация даты
     */
    public function validateDate($date, $format = 'Y-m-d')
    {
        if (empty($date)) {
            $this->addError('date', 'Дата не может быть пустой');
            return false;
        }
        
        $d = \DateTime::createFromFormat($format, $date);
        if (!$d || $d->format($format) !== $date) {
            $this->addError('date', 'Некорректный формат даты');
            return false;
        }
        
        // Проверяем, что дата не в будущем (для продаж)
        if ($d > new \DateTime()) {
            $this->addError('date', 'Дата не может быть в будущем');
            return false;
        }
        
        // Проверяем, что дата не слишком старая (старше 10 лет)
        if ($d < new \DateTime('-10 years')) {
            $this->addError('date', 'Дата слишком старая (старше 10 лет)');
            return false;
        }
        
        return true;
    }
    
    /**
     * Валидация файла
     */
    public function validateFile($file, $allowedExtensions = ['csv', 'xls', 'xlsx'], $maxSize = 10485760) // 10MB
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->addError('file', 'Ошибка загрузки файла');
            return false;
        }
        
        if ($file['size'] > $maxSize) {
            $this->addError('file', 'Размер файла превышает ' . ($maxSize / 1024 / 1024) . 'MB');
            return false;
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            $this->addError('file', 'Недопустимый тип файла. Разрешены: ' . implode(', ', $allowedExtensions));
            return false;
        }
        
        return true;
    }
    
    /**
     * Санитизация строки
     */
    public static function sanitizeString($string, $maxLength = null)
    {
        $string = trim($string);
        $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        
        if ($maxLength && strlen($string) > $maxLength) {
            $string = substr($string, 0, $maxLength);
        }
        
        return $string;
    }
    
    /**
     * Санитизация email
     */
    public static function sanitizeEmail($email)
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
    
    /**
     * Проверка уникальности промокода в базе данных
     */
    public function validatePromoCodeUnique($code, $pdo, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) FROM promo_codes WHERE code = ?";
        $params = [$code];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->fetchColumn() > 0) {
            $this->addError('code', 'Промокод уже существует');
            return false;
        }
        
        return true;
    }
    
    /**
     * Проверка уникальности email в базе данных
     */
    public function validateEmailUnique($email, $pdo, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->fetchColumn() > 0) {
            $this->addError('email', 'Email уже используется');
            return false;
        }
        
        return true;
    }
}
?>
