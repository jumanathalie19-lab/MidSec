<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  helpers/Response.php
//  Standardised JSON response helper used by all controllers.
// ============================================================

class Response {

    public static function success(
        mixed  $data    = null,
        string $message = 'Success',
        int    $code    = 200
    ): never {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ]);
        exit;
    }

    public static function error(
        string $message = 'An error occurred.',
        int    $code    = 400
    ): never {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'data'    => null,
        ]);
        exit;
    }
}