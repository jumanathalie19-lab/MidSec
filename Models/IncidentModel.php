<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  models/IncidentModel.php  — FINAL (extends BaseModel)
//  Cross-model fix: extends BaseModel, __destruct removed,
//  private helpers removed (inherited from BaseModel).
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class IncidentModel extends BaseModel {

    // [I1] createIncident()
    public function createIncident(
        int    $user_id,
        string $incident_type,
        string $description,
        float  $latitude,
        float  $longitude
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_create_incident(?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'issdd',
            $user_id, $incident_type, $description,
            $latitude, $longitude
        );

        return $this->fetchOne($stmt);
    }

    // [I2] getAllIncidents()
    public function getAllIncidents(
        int     $requesting_user_id,
        ?string $status        = null,
        ?string $incident_type = null
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_all_incidents(?, ?, ?)"
        );
        $stmt->bind_param(
            'iss',
            $requesting_user_id, $status, $incident_type
        );

        return $this->fetchAll($stmt);
    }

    // [I3] getIncidentsByUser()
    public function getIncidentsByUser(
        int $requesting_user_id,
        int $target_user_id
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_incidents_by_user(?, ?)"
        );
        $stmt->bind_param('ii', $requesting_user_id, $target_user_id);

        return $this->fetchAll($stmt);
    }

    // [I4] getIncidentById()
    public function getIncidentById(
        int $requesting_user_id,
        int $incident_id
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_incident_by_id(?, ?)"
        );
        $stmt->bind_param('ii', $requesting_user_id, $incident_id);

        return $this->fetchOne($stmt);
    }

    // [I5] updateIncidentStatus()
    public function updateIncidentStatus(
        int    $incident_id,
        int    $guard_id,
        string $new_status,
        string $guard_note
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_update_incident_status(?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'iiss',
            $incident_id, $guard_id, $new_status, $guard_note
        );

        return $this->fetchOne($stmt);
    }

    // [I6] deleteIncident()
    public function deleteIncident(
        int    $admin_id,
        int    $incident_id,
        string $reason
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_delete_incident(?, ?, ?)"
        );
        $stmt->bind_param('iis', $admin_id, $incident_id, $reason);

        return $this->fetchOne($stmt);
    }

    // [I7] getDeletedIncidents()
    public function getDeletedIncidents(int $admin_id): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_deleted_incidents(?)"
        );
        $stmt->bind_param('i', $admin_id);

        return $this->fetchAll($stmt);
    }

    // [I8] restoreIncident()
    public function restoreIncident(
        int    $admin_id,
        int    $incident_id,
        string $reason
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_restore_incident(?, ?, ?)"
        );
        $stmt->bind_param('iis', $admin_id, $incident_id, $reason);

        return $this->fetchOne($stmt);
    }
}