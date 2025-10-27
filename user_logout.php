<?php

// Выход пользователя из системы
session_start();

// Очищаем сессию пользователя
unset($_SESSION['user_id']);
unset($_SESSION['user_email']);
unset($_SESSION['user_logged_in']);
unset($_SESSION['user_token']);
unset($_SESSION['user_name']);

// Уничтожаем сессию полностью
session_destroy();

// Перенаправляем на страницу входа
header('Location: /user.php');
exit;





