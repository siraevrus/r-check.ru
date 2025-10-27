<?php

// Проверка авторизации администратора
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin.php');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use ReproCRM\Config\Config;
use ReproCRM\Utils\Validator;

Config::load();
$_ENV['DB_TYPE'] = 'sqlite';

// Подключаемся к базе данных
$dbPath = __DIR__ . '/database/reprocrm.db';
$pdo = new PDO("sqlite:" . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$message = '';
$messageType = '';

// Обработка действий
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validator = new Validator();

    switch ($action) {
        case 'add_employee':
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($validator->validateEmail($email) &&
                $validator->validatePassword($password) &&
                $password === $confirmPassword) {

                try {
                    // Проверяем, не существует ли уже администратор с таким email
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ?");
                    $stmt->execute([$email]);

                    if ($stmt->fetchColumn() > 0) {
                        $message = 'Администратор с таким email уже существует';
                        $messageType = 'error';
                    } else {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO admins (email, password_hash, created_at, updated_at) VALUES (?, ?, datetime('now'), datetime('now'))");
                        $stmt->execute([$email, $passwordHash]);

                        $message = 'Сотрудник успешно добавлен';
                        $messageType = 'success';
                    }
                } catch (Exception $e) {
                    $message = 'Ошибка при добавлении сотрудника: ' . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = implode(' ', array_merge(...array_values($validator->getErrors())));
                $messageType = 'error';
            }
            break;

        case 'update_employee':
            $employeeId = (int)($_POST['employee_id'] ?? 0);
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($employeeId > 0 && $validator->validateEmail($email)) {
                try {
                    // Проверяем, не существует ли уже другой администратор с таким email
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $employeeId]);

                    if ($stmt->fetchColumn() > 0) {
                        $message = 'Администратор с таким email уже существует';
                        $messageType = 'error';
                    } else {
                        // Если указан пароль, обновляем его тоже
                        if (!empty($password) && !empty($confirmPassword)) {
                            if ($validator->validatePassword($password) && $password === $confirmPassword) {
                                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                                $stmt = $pdo->prepare("UPDATE admins SET email = ?, password_hash = ?, updated_at = datetime('now') WHERE id = ?");
                                $stmt->execute([$email, $passwordHash, $employeeId]);
                                $message = 'Данные сотрудника и пароль обновлены';
                            } else {
                                $message = 'Пароли не совпадают или не соответствуют требованиям';
                                $messageType = 'error';
                                break;
                            }
                        } else {
                            // Обновляем только email
                            $stmt = $pdo->prepare("UPDATE admins SET email = ?, updated_at = datetime('now') WHERE id = ?");
                            $stmt->execute([$email, $employeeId]);
                            $message = 'Данные сотрудника обновлены';
                        }
                        $messageType = 'success';
                    }
                } catch (Exception $e) {
                    $message = 'Ошибка при обновлении сотрудника: ' . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = 'Неверные данные';
                $messageType = 'error';
            }
            break;

        case 'delete_employee':
            $employeeId = (int)($_POST['employee_id'] ?? 0);

            if ($employeeId > 0) {
                try {
                    // Проверяем, что это не текущий администратор (если есть сессия)
                    $currentAdminEmail = $_SESSION['admin_email'] ?? '';
                    $stmt = $pdo->prepare("SELECT email FROM admins WHERE id = ?");
                    $stmt->execute([$employeeId]);
                    $employeeEmail = $stmt->fetchColumn();

                    if ($employeeEmail === $currentAdminEmail) {
                        $message = 'Нельзя удалить текущего администратора';
                        $messageType = 'error';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
                        $stmt->execute([$employeeId]);

                        $message = 'Сотрудник удален';
                        $messageType = 'success';
                    }
                } catch (Exception $e) {
                    $message = 'Ошибка при удалении сотрудника: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
            break;
    }
}

// Получаем список администраторов
$stmt = $pdo->query("SELECT id, email, created_at, updated_at FROM admins ORDER BY created_at DESC");
$employees = $stmt->fetchAll();

// Получаем данные для редактирования
$editingEmployee = null;
if (isset($_GET['edit']) && $_GET['edit'] > 0) {
    $stmt = $pdo->prepare("SELECT id, email FROM admins WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editingEmployee = $stmt->fetch();
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление сотрудниками - Система РЕПРО</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-gray-100">
    <?php include 'admin_navigation.php'; ?>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Уведомления -->
        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-100 text-green-800 border-green-400' : 'bg-red-100 text-red-800 border-red-400' ?> border-l-4">
            <div class="flex items-center">
                <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Форма добавления/редактирования -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        <?= $editingEmployee ? 'Редактировать сотрудника' : 'Добавить администратора' ?>
                    </h3>

                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="<?= $editingEmployee ? 'update_employee' : 'add_employee' ?>">
                        <?php if ($editingEmployee): ?>
                        <input type="hidden" name="employee_id" value="<?= $editingEmployee['id'] ?>">
                        <?php endif; ?>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" name="email" required
                                   value="<?= htmlspecialchars($editingEmployee['email'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="admin@example.com">
                        </div>

                        <?php if (!$editingEmployee): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Пароль</label>
                            <div class="relative">
                                <input type="password" name="password" id="addPassword" required minlength="6"
                                       class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="Минимум 6 символов">
                                <button type="button" id="toggleAddPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Повторить пароль</label>
                            <div class="relative">
                                <input type="password" name="confirm_password" id="addConfirmPassword" required minlength="6"
                                       class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="Повторите пароль">
                                <button type="button" id="toggleAddConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                                </button>
                            </div>
                        </div>
                        <?php else: ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Новый пароль <span class="text-sm text-gray-500">(оставьте пустым, чтобы не менять)</span>
                            </label>
                            <div class="relative">
                                <input type="password" name="password" id="editPassword" minlength="6"
                                       class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="Новый пароль (минимум 6 символов)">
                                <button type="button" id="toggleEditPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Повторить новый пароль</label>
                            <div class="relative">
                                <input type="password" name="confirm_password" id="editConfirmPassword" minlength="6"
                                       class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="Повторите новый пароль">
                                <button type="button" id="toggleEditConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="flex space-x-3">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-<?= $editingEmployee ? 'save' : 'plus' ?> mr-2"></i>
                                <?= $editingEmployee ? 'Обновить' : 'Добавить' ?>
                            </button>

                            <?php if ($editingEmployee): ?>
                            <a href="/employees.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700">
                                <i class="fas fa-times mr-2"></i>Отмена
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Список администраторов -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Список администраторов (<?= count($employees) ?>)
                    </h3>

                    <?php if (empty($employees)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-users text-4xl mb-4"></i>
                        <p>Сотрудники не найдены</p>
                    </div>
                    <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($employees as $employee): ?>
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                                            <i class="fas fa-user text-white"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($employee['email']) ?>
                                        </h4>
                                        <p class="text-sm text-gray-500">
                                            Добавлен: <?= date('d.m.Y H:i', strtotime($employee['created_at'])) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex space-x-2">
                                <a href="/employees.php?edit=<?= $employee['id'] ?>"
                                   class="text-blue-600 hover:text-blue-800" title="Редактировать">
                                    <i class="fas fa-edit"></i>
                                </a>

                                <?php if (count($employees) > 1): ?>
                                <form method="POST" class="inline" onsubmit="return confirm('Вы уверены, что хотите удалить сотрудника?')">
                                    <input type="hidden" name="action" value="delete_employee">
                                    <input type="hidden" name="employee_id" value="<?= $employee['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800" title="Удалить">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Функциональность показа/скрытия пароля при добавлении сотрудника
        const toggleAddPassword = document.getElementById('toggleAddPassword');
        const addPasswordField = document.getElementById('addPassword');

        if (toggleAddPassword && addPasswordField) {
            toggleAddPassword.addEventListener('click', function() {
                const type = addPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
                addPasswordField.setAttribute('type', type);

                const icon = this.querySelector('i');
                if (type === 'text') {
                    icon.className = 'fas fa-eye-slash text-gray-600';
                } else {
                    icon.className = 'fas fa-eye text-gray-400 hover:text-gray-600';
                }
            });
        }

        // Функциональность показа/скрытия подтверждения пароля при добавлении
        const toggleAddConfirmPassword = document.getElementById('toggleAddConfirmPassword');
        const addConfirmPasswordField = document.getElementById('addConfirmPassword');

        if (toggleAddConfirmPassword && addConfirmPasswordField) {
            toggleAddConfirmPassword.addEventListener('click', function() {
                const type = addConfirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
                addConfirmPasswordField.setAttribute('type', type);

                const icon = this.querySelector('i');
                if (type === 'text') {
                    icon.className = 'fas fa-eye-slash text-gray-600';
                } else {
                    icon.className = 'fas fa-eye text-gray-400 hover:text-gray-600';
                }
            });
        }

        // Функциональность показа/скрытия пароля при редактировании сотрудника
        const toggleEditPassword = document.getElementById('toggleEditPassword');
        const editPasswordField = document.getElementById('editPassword');

        if (toggleEditPassword && editPasswordField) {
            toggleEditPassword.addEventListener('click', function() {
                const type = editPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
                editPasswordField.setAttribute('type', type);

                const icon = this.querySelector('i');
                if (type === 'text') {
                    icon.className = 'fas fa-eye-slash text-gray-600';
                } else {
                    icon.className = 'fas fa-eye text-gray-400 hover:text-gray-600';
                }
            });
        }

        // Функциональность показа/скрытия подтверждения пароля при редактировании
        const toggleEditConfirmPassword = document.getElementById('toggleEditConfirmPassword');
        const editConfirmPasswordField = document.getElementById('editConfirmPassword');

        if (toggleEditConfirmPassword && editConfirmPasswordField) {
            toggleEditConfirmPassword.addEventListener('click', function() {
                const type = editConfirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
                editConfirmPasswordField.setAttribute('type', type);

                const icon = this.querySelector('i');
                if (type === 'text') {
                    icon.className = 'fas fa-eye-slash text-gray-600';
                } else {
                    icon.className = 'fas fa-eye text-gray-400 hover:text-gray-600';
                }
            });
        }
    </script>
</body>
</html>
