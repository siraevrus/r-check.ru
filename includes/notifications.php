<?php
/**
 * Система уведомлений для системы РЕПРО
 */

class NotificationSystem {
    private static $notifications = [];
    
    /**
     * Добавить уведомление
     */
    public static function add($message, $type = 'info', $dismissible = true) {
        self::$notifications[] = [
            'message' => $message,
            'type' => $type,
            'dismissible' => $dismissible,
            'timestamp' => time()
        ];
    }
    
    /**
     * Добавить уведомление об успехе
     */
    public static function success($message) {
        self::add($message, 'success');
    }
    
    /**
     * Добавить уведомление об ошибке
     */
    public static function error($message) {
        self::add($message, 'error');
    }
    
    /**
     * Добавить предупреждение
     */
    public static function warning($message) {
        self::add($message, 'warning');
    }
    
    /**
     * Добавить информационное уведомление
     */
    public static function info($message) {
        self::add($message, 'info');
    }
    
    /**
     * Получить все уведомления
     */
    public static function getAll() {
        return self::$notifications;
    }
    
    /**
     * Очистить уведомления
     */
    public static function clear() {
        self::$notifications = [];
    }
    
    /**
     * Рендерить уведомления в HTML
     */
    public static function render() {
        $notifications = self::getAll();
        if (empty($notifications)) {
            return '';
        }
        
        $html = '<div id="notifications-container" class="fixed top-4 right-4 z-50 space-y-2">';
        
        foreach ($notifications as $notification) {
            $bgColor = self::getBgColor($notification['type']);
            $textColor = self::getTextColor($notification['type']);
            $icon = self::getIcon($notification['type']);
            
            $html .= '<div class="' . $bgColor . ' ' . $textColor . ' px-6 py-4 rounded-lg shadow-lg border-l-4 ' . self::getBorderColor($notification['type']) . ' max-w-md transform transition-all duration-300 ease-in-out" role="alert">';
            
            $html .= '<div class="flex items-center">';
            $html .= '<div class="flex-shrink-0">';
            $html .= '<i class="' . $icon . ' text-xl"></i>';
            $html .= '</div>';
            $html .= '<div class="ml-3 flex-1">';
            $html .= '<p class="text-sm font-medium">' . htmlspecialchars($notification['message']) . '</p>';
            $html .= '</div>';
            
            if ($notification['dismissible']) {
                $html .= '<div class="ml-4 flex-shrink-0">';
                $html .= '<button type="button" class="inline-flex rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2" onclick="dismissNotification(this)">';
                $html .= '<span class="sr-only">Закрыть</span>';
                $html .= '<i class="fas fa-times"></i>';
                $html .= '</button>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        // Добавляем JavaScript для автоматического скрытия
        $html .= '<script>
            function dismissNotification(button) {
                const notification = button.closest(".transform");
                notification.style.transform = "translateX(100%)";
                notification.style.opacity = "0";
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }
            
            // Автоматическое скрытие через 5 секунд
            setTimeout(() => {
                const notifications = document.querySelectorAll("#notifications-container > div");
                notifications.forEach(notification => {
                    notification.style.transform = "translateX(100%)";
                    notification.style.opacity = "0";
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                });
            }, 5000);
        </script>';
        
        return $html;
    }
    
    private static function getBgColor($type) {
        switch ($type) {
            case 'success': return 'bg-green-50';
            case 'error': return 'bg-red-50';
            case 'warning': return 'bg-yellow-50';
            case 'info': return 'bg-blue-50';
            default: return 'bg-gray-50';
        }
    }
    
    private static function getTextColor($type) {
        switch ($type) {
            case 'success': return 'text-green-800';
            case 'error': return 'text-red-800';
            case 'warning': return 'text-yellow-800';
            case 'info': return 'text-blue-800';
            default: return 'text-gray-800';
        }
    }
    
    private static function getBorderColor($type) {
        switch ($type) {
            case 'success': return 'border-green-400';
            case 'error': return 'border-red-400';
            case 'warning': return 'border-yellow-400';
            case 'info': return 'border-blue-400';
            default: return 'border-gray-400';
        }
    }
    
    private static function getIcon($type) {
        switch ($type) {
            case 'success': return 'fas fa-check-circle';
            case 'error': return 'fas fa-exclamation-circle';
            case 'warning': return 'fas fa-exclamation-triangle';
            case 'info': return 'fas fa-info-circle';
            default: return 'fas fa-bell';
        }
    }
}

// Функции-помощники для быстрого использования
function notify_success($message) {
    NotificationSystem::success($message);
}

function notify_error($message) {
    NotificationSystem::error($message);
}

function notify_warning($message) {
    NotificationSystem::warning($message);
}

function notify_info($message) {
    NotificationSystem::info($message);
}
?>
