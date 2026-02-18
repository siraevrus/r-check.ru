<?php

// Выход администратора из системы
session_start();

// Очищаем сессию администратора
unset($_SESSION['admin_id']);
unset($_SESSION['admin_email']);
unset($_SESSION['admin_logged_in']);

// Перенаправляем на страницу авторизации
header('Location: /admin.php');
exit;

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выход из системы</title>
</head>
<body>
    <p>Выход из системы...</p>
</body>
</html>


