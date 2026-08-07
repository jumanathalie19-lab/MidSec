<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  models/BaseModel.php  — FINAL
//  Cross-model fix: error handling distinguishes SIGNAL
//  (application-level, safe to forward) from system-level
//  MySQLi errors (logged server-side, generic message returned).
// ============================================================

require_once __DIR__ . '/../config/db.php';

abstract class BaseModel {

    protected mysqli $conn;

    public function __construct() {
        $this->conn = getConnection();
    }

    // ----------------------------------------------------------
    //  fetchOne()
    //  Execute a procedure returning a single row or confirmation.
    // ----------------------------------------------------------
    protected function fetchOne(mysqli_stmt $stmt): array {
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $row    = $result ? $result->fetch_assoc() : [];
            $stmt->close();
            return ['success' => true, 'data' => $row];
        }
        return $this->handleError($stmt);
    }

    // ----------------------------------------------------------
    //  fetchAll()
    //  Execute a procedure returning multiple rows.
    // ----------------------------------------------------------
    protected function fetchAll(mysqli_stmt $stmt): array {
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $rows   = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
            return ['success' => true, 'data' => $rows];
        }
        return $this->handleError($stmt);
    }

    // ----------------------------------------------------------
    //  fetchMultiple()
    //  Execute a procedure returning 2 or more result sets.
    //  Safe for any number of result sets (2, 3, 4, 5, 6+).
    // ----------------------------------------------------------
    protected function fetchMultiple(mysqli_stmt $stmt): array {
        if (!$stmt->execute()) {
            return $this->handleError($stmt);
        }

        $allSets = [];

        $result = $stmt->get_result();
        if ($result) {
            $allSets[] = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        }

        while ($this->conn->next_result()) {
            $result = $this->conn->store_result();
            if ($result) {
                $allSets[] = $result->fetch_all(MYSQLI_ASSOC);
                $result->free();
            }
        }

        $stmt->close();
        return ['success' => true, 'data' => $allSets];
    }

    // ----------------------------------------------------------
    //  handleError()
    //  Distinguishes SIGNAL SQLSTATE '45000' errors (application-
    //  level, safe to return to the client) from MySQLi system
    //  errors (logged server-side, generic message returned).
    //
    //  MySQL/MariaDB error number 1644 = SIGNAL SQLSTATE '45000'
    //  These are intentional user-facing messages from the
    //  stored procedures (e.g. 'Access denied', 'User not found').
    //  All other error numbers are system-level and must not
    //  expose internal details to the client.
    // ----------------------------------------------------------
    private function handleError(mysqli_stmt $stmt): array {
        $errno   = $stmt->errno;
        $message = $stmt->error;
        $stmt->close();

        // 1644 = SIGNAL SQLSTATE '45000' — intentional app error
        if ($errno === 1644) {
            return ['success' => false, 'message' => $message];
        }

        // System-level error — log internally, return generic message
        error_log("MySQLi error [{$errno}]: {$message}");
        return [
            'success' => false,
            'message' => 'A system error occurred. Please try again or contact support.',
        ];
    }
}