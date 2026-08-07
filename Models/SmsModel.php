<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  models/SmsModel.php
//  Calls: sp_log_sms, sp_get_sms_by_incident,
//         sp_get_all_sms_logs, sp_get_sms_statistics
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class SmsModel extends BaseModel {

    // ----------------------------------------------------------
    //  [S1] logSms()
    //  Calls: sp_log_sms
    //
    //  Guard or admin only — enforced inside the procedure.
    //  Creates an audit record of an SMS sent to a resident
    //  in connection with a security incident.
    //  Procedure enforces:
    //    - Incident must exist and not be soft-deleted
    //    - Guard must be active and verified
    //    - All fields mandatory (procedure SIGNALs on empty)
    //    - Audit log meta-entry created (logs the act of logging)
    //  $delivery_status: 'sent' | 'delivered' | 'failed'
    //  recipient_phone stored in sms_logs but excluded from
    //  the audit_log entry (data minimization).
    // ----------------------------------------------------------
    public function logSms(
        int    $incident_id,
        int    $guard_id,
        string $recipient_phone,
        string $message,
        string $delivery_status = 'sent'  // 'sent'|'delivered'|'failed'
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_log_sms(?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'iisss',
            $incident_id,
            $guard_id,
            $recipient_phone,
            $message,
            $delivery_status
        );

        return $this->fetchOne($stmt);
    }

    // ----------------------------------------------------------
    //  [S2] getSmsByIncident()
    //  Calls: sp_get_sms_by_incident
    //
    //  FIX: $requesting_user_id added as first parameter.
    //  Guard or admin only — enforced inside the procedure.
    //  Role-aware field disclosure:
    //    guard → recipient_phone suppressed (returned as NULL)
    //            guards see delivery status and message only
    //    admin → recipient_phone returned
    //  Used by guards to verify a resident was notified
    //  during an active incident, without exposing phone
    //  numbers unnecessarily.
    // ----------------------------------------------------------
    public function getSmsByIncident(
        int $requesting_user_id,
        int $incident_id
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_sms_by_incident(?, ?)"
        );
        $stmt->bind_param('ii', $requesting_user_id, $incident_id);

        return $this->fetchAll($stmt);
    }

    // ----------------------------------------------------------
    //  [S3] getAllSmsLogs()
    //  Calls: sp_get_all_sms_logs
    //
    //  FIX: All four parameters added.
    //  Admin only — enforced inside the procedure.
    //  Returns full SMS audit records including recipient_phone —
    //  personal data restricted to admin access only.
    //  This endpoint must be restricted to admin JWT tokens
    //  at the controller layer.
    //  $delivery_status: NULL = all statuses
    //  $date_from / $date_to: NULL = no date filter
    // ----------------------------------------------------------
    public function getAllSmsLogs(
        int     $admin_id,
        ?string $delivery_status = null,
        ?string $date_from       = null,
        ?string $date_to         = null
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_all_sms_logs(?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'isss',
            $admin_id,
            $delivery_status,
            $date_from,
            $date_to
        );

        return $this->fetchAll($stmt);
    }

    // ----------------------------------------------------------
    //  [S4] getSmsStatistics()  — NEW
    //  Calls: sp_get_sms_statistics
    //
    //  Admin only — enforced inside the procedure.
    //  Returns TWO result sets — uses fetchMultiple():
    //    [0] Overall delivery summary:
    //        total_sms_logged, total_sent, total_delivered,
    //        total_failed, delivery_success_rate_pct,
    //        incidents_with_sms, guards_who_sent_sms
    //    [1] Per-guard SMS activity:
    //        guard_name, total_sms_sent, delivered_count,
    //        failed_count, failure_rate_pct,
    //        unique_incidents_handled
    //  No recipient_phone or personal data in response —
    //  aggregate statistics only.
    //  Used by admin dashboard and Section 8 reporting.
    //  $date_from / $date_to: NULL = all time.
    // ----------------------------------------------------------
    public function getSmsStatistics(
        int     $admin_id,
        ?string $date_from = null,
        ?string $date_to   = null
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_sms_statistics(?, ?, ?)"
        );
        $stmt->bind_param('iss', $admin_id, $date_from, $date_to);

        // Returns two result sets:
        // [0] overall delivery summary
        // [1] per-guard activity breakdown
        return $this->fetchMultiple($stmt);
    }
}