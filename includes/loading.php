<?php
/**
 * Система индикаторов загрузки
 */

class LoadingSystem {
    
    /**
     * Рендерить индикатор загрузки
     */
    public static function render($text = 'Загрузка...', $size = 'medium') {
        $sizeClass = self::getSizeClass($size);
        
        $html = '<div id="loading-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50" style="display: none;">';
        $html .= '<div class="bg-white rounded-lg p-6 shadow-xl max-w-sm w-full mx-4">';
        $html .= '<div class="flex items-center justify-center mb-4">';
        $html .= '<div class="' . $sizeClass . ' animate-spin rounded-full border-b-2 border-blue-600"></div>';
        $html .= '</div>';
        $html .= '<div class="text-center">';
        $html .= '<p class="text-gray-700 font-medium">' . htmlspecialchars($text) . '</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Добавляем JavaScript для управления
        $html .= '<script>
            function showLoading(text = "' . htmlspecialchars($text) . '") {
                const overlay = document.getElementById("loading-overlay");
                const textElement = overlay.querySelector("p");
                if (textElement) {
                    textElement.textContent = text;
                }
                overlay.style.display = "flex";
                document.body.style.overflow = "hidden";
            }
            
            function hideLoading() {
                const overlay = document.getElementById("loading-overlay");
                overlay.style.display = "none";
                document.body.style.overflow = "auto";
            }
            
            // Автоматическое скрытие через 30 секунд (защита от зависания)
            let loadingTimeout;
            const originalShowLoading = showLoading;
            showLoading = function(text) {
                originalShowLoading(text);
                clearTimeout(loadingTimeout);
                loadingTimeout = setTimeout(() => {
                    hideLoading();
                    console.warn("Loading timeout reached");
                }, 30000);
            };
        </script>';
        
        return $html;
    }
    
    /**
     * Рендерить маленький индикатор загрузки для кнопок
     */
    public static function renderButton($text = 'Загрузка...') {
        return '<span class="inline-flex items-center">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    ' . htmlspecialchars($text) . '
                </span>';
    }
    
    /**
     * Рендерить индикатор загрузки для таблиц
     */
    public static function renderTable() {
        return '<tr id="table-loading">
                    <td colspan="100%" class="px-6 py-12 text-center">
                        <div class="flex flex-col items-center">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-4"></div>
                            <p class="text-gray-500">Загрузка данных...</p>
                        </div>
                    </td>
                </tr>';
    }
    
    private static function getSizeClass($size) {
        switch ($size) {
            case 'small': return 'h-6 w-6';
            case 'medium': return 'h-12 w-12';
            case 'large': return 'h-16 w-16';
            default: return 'h-12 w-12';
        }
    }
}

// Функции-помощники
function show_loading($text = 'Загрузка...') {
    echo '<script>showLoading("' . htmlspecialchars($text) . '");</script>';
}

function hide_loading() {
    echo '<script>hideLoading();</script>';
}
?>
