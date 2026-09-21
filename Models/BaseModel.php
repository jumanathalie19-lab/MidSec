<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  models/BaseModel.php  — FINAL (PHP 8.1+ mysqli exception fix)
//
//  Cross-model fix: error handling distinguishes SIGNAL
//  (application-level, safe to forward) from system-level
//  MySQLi errors (logged server-side, generic message returned).
//
//  IMPORTANT: since PHP 8.1, mysqli reports errors by throwing
//  mysqli_sql_exception instead of returning false from
//  execute(). All three fetch* methods below wrap execute()
//  in a try/catch so SIGNAL messages from stored procedures
//  (e.g. "Email already registered") are still returned to the
//  client cleanly as ['success' => false, 'message' => ...]
//  instead of bubbling up as an uncaught exception and hitting
//  the generic 500 handler in index.php.
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
        try {
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $row    = $result ? $result->fetch_assoc() : [];
                $stmt->close();
                return ['success' => true, 'data' => $row];
            }
            return $this->handleError($stmt);
        } catch (mysqli_sql_exception $e) {
            return $this->handleMysqliException($e);
        }
    }

    // ----------------------------------------------------------
    //  fetchAll()
    //  Execute a procedure returning multiple rows.
    // ----------------------------------------------------------
    protected function fetchAll(mysqli_stmt $stmt): array {
        try {
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $rows   = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                $stmt->close();
                return ['success' => true, 'data' => $rows];
            }
            return $this->handleError($stmt);
        } catch (mysqli_sql_exception $e) {
            return $this->handleMysqliException($e);
        }
    }

    // ----------------------------------------------------------
    //  fetchMultiple()
    //  Execute a procedure returning 2 or more result sets.
    //  Safe for any number of result sets (2, 3, 4, 5, 6+).
    // ----------------------------------------------------------
    protected function fetchMultiple(mysqli_stmt $stmt): array {
        try {
            if (!$stmt->execute()) {
                return $this->handleError($stmt);
            }

            // IMPORTANT: stay on the $stmt object for every result set.
            // Mixing $stmt->get_result() (for the first set) with
            // $conn->next_result()/$conn->store_result() (for later
            // sets) causes mysqli to misalign column metadata on the
            // second and subsequent result sets — values come back
            // real, but shifted onto the wrong column names. Using
            // $stmt->more_results()/$stmt->next_result() consistently,
            // and re-calling $stmt->get_result() each time, keeps
            // metadata correctly bound to each set.
            $allSets = [];

            do {
                $result = $stmt->get_result();
                if ($result) {
                    $allSets[] = $result->fetch_all(MYSQLI_ASSOC);
                    $result->free();
                } else {
                    $allSets[] = [];
                }
            } while ($stmt->more_results() && $stmt->next_result());

            $stmt->close();
            return ['success' => true, 'data' => $allSets];
        } catch (mysqli_sql_exception $e) {
            return $this->handleMysqliException($e);
        }
    }

    // ----------------------------------------------------------
    //  handleError()
    //  Legacy path — kept in case execute() ever returns false
    //  without throwing (e.g. certain warning-level conditions).
    //  Distinguishes SIGNAL SQLSTATE '45000' errors (application-
    //  level, safe to return to the client) from MySQLi system
    //  errors (logged server-side, generic message returned).
    //
    //  MySQL/MariaDB error number 1644 = SIGNAL SQLSTATE '45000'
    // ----------------------------------------------------------
    private function handleError(mysqli_stmt $stmt): array {
        $errno   = $stmt->errno;
        $message = $stmt->error;
        $stmt->close();

        if ($errno === 1644) {
            return ['success' => false, 'message' => $message];
        }

        error_log("MySQLi error [{$errno}]: {$message}");
        return [
            'success' => false,
            'message' => 'A system error occurred. Please try again or contact support.',
        ];
    }

    // ----------------------------------------------------------
    //  handleMysqliException()
    //  Primary path on PHP 8.1+, where mysqli throws
    //  mysqli_sql_exception instead of returning false.
    //  Same SIGNAL-vs-system distinction as handleError(), but
    //  reads the error code/message from the exception object.
    // ----------------------------------------------------------
    private function handleMysqliException(mysqli_sql_exception $e): array {
        $errno   = $e->getCode();
        $message = $e->getMessage();

        // 1644 = SIGNAL SQLSTATE '45000' — intentional app error
        // from a stored procedure (e.g. "Email already registered").
        // Safe to forward to the client as-is.
        if ($errno === 1644) {
            return ['success' => false, 'message' => $message];
        }

        // Any other error code is a genuine system-level failure
        // (bad SQL, connection issue, constraint violation, etc.)
        // Log full detail server-side, return generic message only.
        error_log("MySQLi exception [{$errno}]: {$message}");
        return [
            'success' => false,
            'message' => 'A system error occurred. Please try again or contact support.',
        ];
    }
}