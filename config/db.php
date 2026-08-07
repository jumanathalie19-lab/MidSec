<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  config/db.php — Singleton MySQLi connection
// ============================================================

function getConnection(): mysqli {
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(
            'localhost',       // DB_HOST
            'root',            // DB_USER — use a limited app user in production
            '',                // DB_PASS
            'midsec'           // DB_NAME
        );

        if ($conn->connect_error) {
            http_response_code(500);
            echo json_encode(['message' => 'Database connection failed.']);
            exit;
        }

        $conn->set_charset('utf8mb4');
    }

    return $conn;
}