<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  models/ReportModel.php  — NEW
//  Calls: sp_report_monthly_incidents, sp_report_crime_trends,
//         sp_report_panic_statistics, sp_report_resident_activity,
//         sp_report_guard_performance, sp_report_payment_summary,
//         sp_report_full_security_summary, sp_generate_report_record
//
//  ALL methods are admin-only — enforced inside each procedure.
//  ALL reporting methods require a date range — the procedures
//  SIGNAL an error if p_date_from or p_date_to is NULL.
//  Date format: 'YYYY-MM-DD' string passed as 's' bind type.
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class ReportModel extends BaseModel {

    // ----------------------------------------------------------
    //  [R1] monthlyIncidents()
    //  Calls: sp_report_monthly_incidents
    //
    //  Returns TWO result sets via fetchMultiple():
    //    [0] Monthly incident volume and resolution rates
    //        grouped by year and month.
    //    [1] Incident type breakdown for the full period —
    //        which types are most common, with resolution counts
    //        and percentage of total.
    //  No individual personal data — aggregate counts only.
    // ----------------------------------------------------------
    public function monthlyIncidents(
        int    $admin_id,
        string $date_from,
        string $date_to
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_report_monthly_incidents(?, ?, ?)"
        );
        $stmt->bind_param('iss', $admin_id, $date_from, $date_to);

        return $this->fetchMultiple($stmt);
    }

    // ----------------------------------------------------------
    //  [R2] crimeTrends()
    //  Calls: sp_report_crime_trends
    //
    //  Returns THREE result sets via fetchMultiple():
    //    [0] Incidents by hour of day and time period —
    //        identifies peak risk periods for patrol scheduling.
    //    [1] CCTV coverage effectiveness — compares resolution
    //        rates for incidents with vs without CCTV coverage.
    //    [2] Incident count by estate block (house number prefix)
    //        — identifies highest-risk residential blocks.
    //        Individual house numbers NOT returned (data
    //        minimization — block level only).
    // ----------------------------------------------------------
    public function crimeTrends(
        int    $admin_id,
        string $date_from,
        string $date_to
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_report_crime_trends(?, ?, ?)"
        );
        $stmt->bind_param('iss', $admin_id, $date_from, $date_to);

        return $this->fetchMultiple($stmt);
    }

    // ----------------------------------------------------------
    //  [R3] panicStatistics()
    //  Calls: sp_report_panic_statistics
    //
    //  $response_target_mins: target response time in minutes.
    //  Defaults to 15 inside the procedure if NULL or <= 0.
    //  Aligned with Kenya National Police Service emergency
    //  response benchmark.
    //
    //  Returns THREE result sets via fetchMultiple():
    //    [0] Overall performance summary — total alerts,
    //        avg/fastest/slowest response times, within-target
    //        count and percentage.
    //    [1] Monthly panic volume trend — count and avg
    //        response time per month.
    //    [2] Per-guard response performance — names included
    //        (operational accountability), no resident data.
    // ----------------------------------------------------------
    public function panicStatistics(
        int    $admin_id,
        string $date_from,
        string $date_to,
        int    $response_target_mins = 15
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_report_panic_statistics(?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'issi',
            $admin_id,
            $date_from,
            $date_to,
            $response_target_mins
        );

        return $this->fetchMultiple($stmt);
    }

    // ----------------------------------------------------------
    //  [R4] residentActivity()
    //  Calls: sp_report_resident_activity
    //
    //  Returns ONE result set via fetchAll():
    //    Per-resident security activity summary:
    //    resident_name, house_no, total_incidents_reported,
    //    incidents_resolved, total_panic_alerts,
    //    panics_resolved, subscription_status,
    //    subscription_expires.
    //  phone_no intentionally excluded (data minimization).
    //  Used to identify residents with frequent incidents
    //  or persistent subscription non-compliance.
    // ----------------------------------------------------------
    public function residentActivity(
        int    $admin_id,
        string $date_from,
        string $date_to
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_report_resident_activity(?, ?, ?)"
        );
        $stmt->bind_param('iss', $admin_id, $date_from, $date_to);

        return $this->fetchAll($stmt);
    }

    // ----------------------------------------------------------
    //  [R5] guardPerformance()
    //  Calls: sp_report_guard_performance
    //
    //  Returns ONE result set via fetchAll():
    //    Per-guard metrics: guard_name, incidents_handled,
    //    incidents_resolved, panics_responded,
    //    avg_panic_response_mins, sms_sent, sms_delivered,
    //    sms_failed, police_escalations, activity_score.
    //  activity_score = weighted composite
    //    (panic responses × 3) + (incidents × 2) + (SMS × 1).
    //  No resident data — guard operational metrics only.
    // ----------------------------------------------------------
    public function guardPerformance(
        int    $admin_id,
        string $date_from,
        string $date_to
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_report_guard_performance(?, ?, ?)"
        );
        $stmt->bind_param('iss', $admin_id, $date_from, $date_to);

        return $this->fetchAll($stmt);
    }

    // ----------------------------------------------------------
    //  [R6] paymentSummary()
    //  Calls: sp_report_payment_summary
    //
    //  Returns THREE result sets via fetchMultiple():
    //    [0] Revenue summary for the period: total records,
    //        completed/pending/failed counts, total revenue,
    //        expected revenue, collection rate %, unique payers.
    //    [1] Monthly revenue breakdown: year, month,
    //        payment count, monthly revenue, unique payers.
    //    [2] Overdue residents: resident_name, house_no,
    //        last_subscription_end, days_overdue,
    //        overdue_category. phone_no excluded — use
    //        PaymentModel::getOverdueSubscriptions() for
    //        contact details.
    //  No mpesa_ref in any result set (data minimization).
    // ----------------------------------------------------------
    public function paymentSummary(
        int    $admin_id,
        string $date_from,
        string $date_to
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_report_payment_summary(?, ?, ?)"
        );
        $stmt->bind_param('iss', $admin_id, $date_from, $date_to);

        return $this->fetchMultiple($stmt);
    }

    // ----------------------------------------------------------
    //  [R7] fullSecuritySummary()
    //  Calls: sp_report_full_security_summary
    //
    //  The consolidated estate security report — presented to
    //  the estate management committee. Safe to present without
    //  redaction (no personal data in any result set).
    //
    //  Returns SIX result sets via fetchMultiple():
    //    [0] Estate overview: verified residents, pending
    //        verification, active guards, active CCTV feeds,
    //        report period.
    //    [1] Incident overview: total, open, under_review,
    //        resolved, resolution rate %.
    //    [2] Panic alert performance: total, currently active,
    //        closed, avg response time.
    //    [3] Payment collection: completed payments, total
    //        revenue, residents without active subscription.
    //    [4] Police escalation summary: total escalations,
    //        unique incidents escalated, stations contacted.
    //    [5] SMS communication summary: total logged,
    //        delivered, failed, delivery success rate %.
    //
    //  fetchMultiple() handles all 6 result sets via the
    //  next_result() loop — no code change needed.
    // ----------------------------------------------------------
    public function fullSecuritySummary(
        int    $admin_id,
        string $date_from,
        string $date_to
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_report_full_security_summary(?, ?, ?)"
        );
        $stmt->bind_param('iss', $admin_id, $date_from, $date_to);

        return $this->fetchMultiple($stmt);
    }

    // ----------------------------------------------------------
    //  [R8] generateReportRecord()
    //  Calls: sp_generate_report_record
    //
    //  Records a report generation event in the reports table.
    //  Called by the controller after a report is successfully
    //  generated, and optionally after a file export.
    //  Creates an audit trail of report generation —
    //  required by KDPA 2019 (aggregating personal data is
    //  itself a data processing activity that must be logged).
    //  $file_path: NULL if report was not exported to a file.
    //  $report_type must match the ENUM defined in Section 8:
    //    'incident_summary' | 'crime_trend' |
    //    'panic_statistics' | 'resident_activity' |
    //    'guard_performance' | 'subscription_summary' |
    //    'full_security_summary'
    // ----------------------------------------------------------
    public function generateReportRecord(
        int     $admin_id,
        string  $report_type,
        string  $date_from,
        string  $date_to,
        ?string $file_path = null
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_generate_report_record(?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'issss',
            $admin_id,
            $report_type,
            $date_from,
            $date_to,
            $file_path
        );

        return $this->fetchOne($stmt);
    }
}