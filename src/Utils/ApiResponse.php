<?php

namespace ReproCRM\Utils;

/**
 * Класс для формирования API ответов
 */
class ApiResponse
{
    /**
     * Успешный ответ
     */
    public function success($data = null, $statusCode = 200)
    {
        http_response_code($statusCode);
        
        $response = [
            'success' => true,
            'data' => $data,
            'timestamp' => date('c')
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Ответ с ошибкой
     */
    public function error($message, $statusCode = 400, $details = null)
    {
        http_response_code($statusCode);
        
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $statusCode
            ],
            'timestamp' => date('c')
        ];
        
        if ($details !== null) {
            $response['error']['details'] = $details;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Ответ с пагинацией
     */
    public function paginated($data, $pagination, $statusCode = 200)
    {
        http_response_code($statusCode);
        
        $response = [
            'success' => true,
            'data' => $data,
            'pagination' => $pagination,
            'timestamp' => date('c')
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
?>
