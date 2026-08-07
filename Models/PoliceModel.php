<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  models/PoliceModel.php
//  Calls: sp_escalate_to_police, sp_get_escalations,
//         sp_get_escalation_by_id, sp_update_escalation_status,
//         sp_close_escalation, sp_get_escalations_by_incident
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class PoliceModel extends BaseModel {

    // ----------------------------------------------------------
    //  [E1] escalate()
    //  Calls: sp_escalate_to_police
    //
    //  Guard or admin only — enforced inside the procedure.
    //  Procedure enforces:
    //    - Incident must exist and not be soft-deleted
    //    - One escalation per incident (duplicate prevention)
    //    - $notes mandatory — guard must document why police
    //      involvement is required (procedure SIGNALs if empty)
    //    - Non-predictable reference code generated:
    //      ESC-YYYY-[4 random hex]-[4 random hex]
    //    - Linked incident status auto-updated to 'under_review'
    //    - Audit log entry created
    //  Returns: escalation_id, reference_code, station_name,
    //           incident_id, confirmation message.
    // ----------------------------------------------------------
    public function escalate(
        int    $incident_id,
        int    $escalated_by,
        string $station_name,
        string $notes           // mandatory: why police involvement needed
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_escalate_to_police(?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'iiss',
            $incident_id,
            $escalated_by,
            $station_name,
            $notes
        );

        return $this->fetchOne($stmt);
    }

    // ----------------------------------------------------------
    //  [E2] getAllEscalations()
    //  Calls: sp_get_escalations
    //
    //  FIX: All three parameters added.
    //  Guard or admin only — enforced inside the procedure.
    //  resident_phone excluded from list view (data minimization
    //  — available in getEscalationById() when needed).
    //  cctv_url excluded entirely from escalation list.
    //  $date_from / $date_to: NULL = no date filter.
    // ----------------------------------------------------------
    public function getAllEscalations(
        int     $requesting_user_id,
        ?string $date_from = null,
        ?string $date_to   = null
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_escalations(?, ?, ?)"
        );
        $stmt->bind_param(
            'iss',
            $requesting_user_id,
            $date_from,
            $date_to
        );

        return $this->fetchAll($stmt);
    }

    // ----------------------------------------------------------
    //  [E3] getEscalationById()
    //  Calls: sp_get_escalation_by_id
    //
    //  FIX: $requesting_user_id added as first parameter.
    //  Role-aware field disclosure:
    //    guard → resident_phone visible; cctv_url and
    //            guard_phone suppressed (returned as NULL)
    //    admin → all fields: resident_phone, cctv_url,
    //            guard_phone all returned
    //  Without $requesting_user_id the procedure cannot
    //  apply this disclosure and fails at runtime.
    // ----------------------------------------------------------
    public function getEscalationById(
        int $requesting_user_id,
        int $escalation_id
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_escalation_by_id(?, ?)"
        );
        $stmt->bind_param('ii', $requesting_user_id, $escalation_id);

        return $this->fetchOne($stmt);
    }

    // ----------------------------------------------------------
    //  [E4] updateEscalationStatus()  — NEW
    //  Calls: sp_update_escalation_status
    //
    //  Admin only — enforced inside the procedure.
    //  Tracks police response progress after escalation.
    //  $new_status options:
    //    'acknowledged' — police confirmed receipt of report
    //    'dispatched'   — officers have been dispatched
    //    'closed'       — case closed by police
    //    'no_action'    — police took no action
    //  $update_notes mandatory — admin must document the
    //  police response detail (procedure SIGNALs if empty).
    //  When status is 'closed' or 'no_action', the procedure
    //  automatically updates the linked incident to 'resolved'.
    //  Status change recorded in audit_log.
    // ----------------------------------------------------------
    public function updateEscalationStatus(
        int    $admin_id,
        int    $escalation_id,
        string $new_status,      // 'acknowledged'|'dispatched'|'closed'|'no_action'
        string $update_notes     // mandatory: documented police response detail
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_update_escalation_status(?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'iiss',
            $admin_id,
            $escalation_id,
            $new_status,
            $update_notes
        );

        return $this->fetchOne($stmt);
    }

    // ----------------------------------------------------------
    //  [E5] closeEscalation()  — NEW
    //  Calls: sp_close_escalation
    //
    //  Admin only — enforced inside the procedure.
    //  Formally closes a police escalation with a documented
    //  outcome. Separate from updateEscalationStatus() to
    //  enforce a clear distinction between progress updates
    //  and final closure.
    //  $outcome_notes mandatory — admin must document the
    //  final police response outcome before closure
    //  (procedure SIGNALs if empty).
    //  On closure:
    //    - Linked incident automatically set to 'resolved'
    //    - Two audit_log entries written atomically:
    //      one for escalation closure, one for incident resolution
    // ----------------------------------------------------------
    public function closeEscalation(
        int    $admin_id,
        int    $escalation_id,
        string $outcome_notes    // mandatory: final police outcome
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_close_escalation(?, ?, ?)"
        );
        $stmt->bind_param(
            'iis',
            $admin_id,
            $escalation_id,
            $outcome_notes
        );

        return $this->fetchOne($stmt);
    }

    // ----------------------------------------------------------
    //  [E6] getEscalationsByIncident()  — NEW
    //  Calls: sp_get_escalations_by_incident
    //
    //  Guard or admin only — enforced inside the procedure.
    //  Returns escalation records linked to a specific incident.
    //  Used by the incident detail view to show whether an
    //  incident has been escalated and what the reference code is.
    //  resident_phone excluded (available in getEscalationById).
    //  cctv_url excluded — not relevant to escalation view.
    // ----------------------------------------------------------
    public function getEscalationsByIncident(
        int $requesting_user_id,
        int $incident_id
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_escalations_by_incident(?, ?)"
        );
        $stmt->bind_param('ii', $requesting_user_id, $incident_id);

        return $this->fetchAll($stmt);
    }
}