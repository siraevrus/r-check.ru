<?php

require_once __DIR__ . '/vendor/autoload.php';

use ReproCRM\Config\Config;

// Загружаем конфигурацию
Config::load();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(Config::getAppName()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .hover-scale {
            transition: transform 0.2s ease-in-out;
        }
        .hover-scale:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body class="min-h-screen gradient-bg">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="w-full max-w-md">
            <div class="glass-effect rounded-2xl p-8 shadow-2xl">
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-white mb-2">
                        <?= htmlspecialchars(Config::getAppName()) ?>
                    </h1>
                </div>

                <div class="space-y-4">
                    <a href="/user.php" class="w-full bg-white text-gray-800 py-3 px-4 rounded-lg font-medium hover-scale shadow-lg flex items-center justify-center">
                        <i class="fas fa-user-md mr-2"></i>
                        Вход для пользователей
                    </a>
                    
                    <a href="/admin.php" class="w-full bg-white bg-opacity-20 text-white py-3 px-4 rounded-lg font-medium hover-scale shadow-lg flex items-center justify-center">
                        <i class="fas fa-user-shield mr-2"></i>
                        Вход для администратора
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
