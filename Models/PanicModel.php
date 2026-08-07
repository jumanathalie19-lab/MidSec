<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  models/PanicModel.php  — FINAL (extends BaseModel)
//  Cross-model fix: extends BaseModel, local fetchMultiple()
//  removed (inherited from BaseModel), __destruct removed.
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class PanicModel extends BaseModel {

    // [P1] triggerPanic()
    public function triggerPanic(
        int   $user_id,
        float $latitude,
        float $longitude
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_trigger_panic(?, ?, ?)"
        );
        $stmt->bind_param('idd', $user_id, $latitude, $longitude);

        return $this->fetchOne($stmt);
    }

    // [P2] respondToPanic()
    public function respondToPanic(
        int $alert_id,
        int $guard_id
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_respond_to_panic(?, ?)"
        );
        $stmt->bind_param('ii', $alert_id, $guard_id);

        return $this->fetchOne($stmt);
    }

    // [P3] getActivePanics()
    public function getActivePanics(int $requesting_user_id): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_active_panics(?)"
        );
        $stmt->bind_param('i', $requesting_user_id);

        return $this->fetchAll($stmt);
    }

    // [P4] getPanicById()
    public function getPanicById(
        int $requesting_user_id,
        int $alert_id
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_panic_by_id(?, ?)"
        );
        $stmt->bind_param('ii', $requesting_user_id, $alert_id);

        return $this->fetchOne($stmt);
    }

    // [P5] closePanic()
    public function closePanic(
        int    $closed_by,
        int    $alert_id,
        string $resolution_notes
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_close_panic(?, ?, ?)"
        );
        $stmt->bind_param('iis', $closed_by, $alert_id, $resolution_notes);

        return $this->fetchOne($stmt);
    }

    // [P6] getPanicHistory()
    public function getPanicHistory(
        int     $requesting_user_id,
        ?string $status    = null,
        ?string $date_from = null,
        ?string $date_to   = null
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_panic_history(?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'isss',
            $requesting_user_id, $status, $date_from, $date_to
        );

        return $this->fetchAll($stmt);
    }

    // [P7] getMyPanics()
    public function getMyPanics(int $user_id): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_my_panics(?)"
        );
        $stmt->bind_param('i', $user_id);

        return $this->fetchAll($stmt);
    }

    // [P8] getPanicStatistics()
    public function getPanicStatistics(
        int     $requesting_user_id,
        ?string $date_from = null,
        ?string $date_to   = null
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_panic_statistics(?, ?, ?)"
        );
        $stmt->bind_param('iss', $requesting_user_id, $date_from, $date_to);

        return $this->fetchMultiple($stmt);
    }
}