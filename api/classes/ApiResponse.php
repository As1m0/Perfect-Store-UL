<?php
class ApiResponse {
    public static function success($data, $message = 'Success') {
        return json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    public static function error($message, $code = 400, $details = null) {
        http_response_code($code);
        return json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => $code,
            'details' => $details
        ]);
    }

    public static function sendHeaders() {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
}