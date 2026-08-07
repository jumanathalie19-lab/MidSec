<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  controllers/ResidentController.php
//  Handles all resident actions
// ============================================================

session_start();
require_once __DIR__ . '/../models/IncidentModel.php';
require_once __DIR__ . '/../models/PanicModel.php';
require_once __DIR__ . '/../models/PaymentModel.php';

header('Content-Type: application/json');

// Only residents allowed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit;
}

$action        = $_POST['action'] ?? $_GET['action'] ?? '';
$incidentModel = new IncidentModel();
$panicModel    = new PanicModel();
$paymentModel  = new PaymentModel();
$user_id       = $_SESSION['user_id'];

// PANIC BUTTON
if ($action === 'trigger_panic') {
    $latitude  = floatval($_POST['latitude']  ?? 0);
    $longitude = floatval($_POST['longitude'] ?? 0);
    if (!$latitude || !$longitude) {
        echo json_encode(['success' => false, 'message' => 'GPS location is required.']); exit;
    }
    echo json_encode($panicModel->triggerPanic($user_id, $latitude, $longitude)); exit;
}

// SUBMIT INCIDENT REPORT
if ($action === 'create_incident') {
    $incident_type = trim($_POST['incident_type'] ?? '');
    $description   = trim($_POST['description']   ?? '');
    $latitude      = floatval($_POST['latitude']   ?? 0);
    $longitude     = floatval($_POST['longitude']  ?? 0);
    $valid_types   = ['suspicious_activity', 'theft_breakin', 'property_damage'];
    if (empty($incident_type) || !in_array($incident_type, $valid_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid incident type.']); exit;
    }
    if (empty($description)) {
        echo json_encode(['success' => false, 'message' => 'Description is required.']); exit;
    }
    echo json_encode($incidentModel->createIncident($user_id, $incident_type, $description, $latitude, $longitude)); exit;
}

// GET MY INCIDENTS
if ($action === 'get_my_incidents') {
    echo json_encode($incidentModel->getIncidentsByUser($user_id)); exit;
}

// CHECK SUBSCRIPTION
if ($action === 'check_subscription') {
    echo json_encode($paymentModel->checkSubscription($user_id)); exit;
}

// GET PAYMENT HISTORY
if ($action === 'get_payment_history') {
    echo json_encode($paymentModel->getPaymentHistory($user_id)); exit;
}

// CREATE PAYMENT
if ($action === 'create_payment') {
    $amount    = floatval($_POST['amount']    ?? 500);
    $mpesa_ref = trim($_POST['mpesa_ref']     ?? '');
    if (empty($mpesa_ref)) {
        echo json_encode(['success' => false, 'message' => 'M-Pesa reference is required.']); exit;
    }
    echo json_encode($paymentModel->createPayment($user_id, $amount, $mpesa_ref)); exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
?>
<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  controllers/ReportController.php
//
//  Handles: monthly incident reports, crime trend analysis,
//           panic statistics, resident activity, guard
//           performance, payment summary, full security
//           summary, report record generation.
//  Model:   ReportModel
//
//  ALL endpoints: admin only.
//  ALL endpoints: date range required (procedures SIGNAL
//                 if date_from or date_to is NULL).
//  MULTIPLE RESULT SETS: monthlyIncidents (2), crimeTrends (3),
//    panicStatistics (3), paymentSummary (3),
//    fullSecuritySummary (6) — all use fetchMultiple().
// ============================================================

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/ReportModel.php';

class ReportController extends BaseController {

    private ReportModel $reportModel;

    public function __construct() {
        parent::__construct();
        $this->reportModel = new ReportModel();
    }

    // ----------------------------------------------------------
    //  GET /api/reports/monthly-incidents
    //  Protected — admin only.
    //
    //  Returns TWO result sets:
    //    [0] Monthly incident volume and resolution rates
    //    [1] Incident type breakdown for full period
    //  No individual personal data — aggregate counts only.
    // ----------------------------------------------------------
    public function monthlyIncidents(): void {
        Request::requireMethod('GET');

        $caller    = $this->requireAuth(['admin']);
        [$from, $to] = $this->requireDateRange();

        $result = $this->reportModel->monthlyIncidents(
            $caller['user_id'],
            $from,
            $to
        );

        if (!$result['success']) {
            Response::error($result['message'], $this->errorCode($result['message'], 500));
        }

        Response::success([
            'monthly_volume'    => $result['data'][0] ?? [],
            'type_breakdown'    => $result['data'][1] ?? [],
            'period_from'       => $from,
            'period_to'         => $to,
        ], 'Monthly incident report generated.');
    }

    // ----------------------------------------------------------
    //  GET /api/reports/crime-trends
    //  Protected — admin only.
    //
    //  Returns THREE result sets:
    //    [0] Incidents by hour and time period
    //    [1] CCTV coverage effectiveness comparison
    //    [2] Incident count by estate block (block level only —
    //        individual house numbers not returned)
    // ----------------------------------------------------------
    public function crimeTrends(): void {
        Request::requireMethod('GET');

        $caller    = $this->requireAuth(['admin']);
        [$from, $to] = $this->requireDateRange();

        $result = $this->reportModel->crimeTrends(
            $caller['user_id'],
            $from,
            $to
        );

        if (!$result['success']) {
            Response::error($result['message'], $this->errorCode($result['message'], 500));
        }

        Response::success([
            'by_time_period'     => $result['data'][0] ?? [],
            'cctv_effectiveness' => $result['data'][1] ?? [],
            'by_estate_block'    => $result['data'][2] ?? [],
            'period_from'        => $from,
            'period_to'          => $to,
        ], 'Crime trends report generated.');
    }

    // ----------------------------------------------------------
    //  GET /api/reports/panic-statistics
    //  Protected — admin only.
    //
    //  Optional: ?response_target_mins=15 (default: 15)
    //
    //  Returns THREE result sets:
    //    [0] Overall performance summary (single row)
    //    [1] Monthly panic volume trend
    //    [2] Per-guard response performance
    //  Measures performance against the Kenya National Police
    //  Service emergency response benchmark (15 minutes).
    // ----------------------------------------------------------
    public function panicStatistics(): void {
        Request::requireMethod('GET');

        $caller    = $this->requireAuth(['admin']);
        [$from, $to] = $this->requireDateRange();

        // Validate optional response target
        $target_raw  = $_GET['response_target_mins'] ?? null;
        $target_mins = 15;

        if ($target_raw !== null) {
            $target_mins = filter_var($target_raw, FILTER_VALIDATE_INT);
            if ($target_mins === false || $target_mins <= 0) {
                Response::error(
                    'response_target_mins must be a positive integer.',
                    400
                );
            }
        }

        $result = $this->reportModel->panicStatistics(
            $caller['user_id'],
            $from,
            $to,
            (int)$target_mins
        );

        if (!$result['success']) {
            Response::error($result['message'], $this->errorCode($result['message'], 500));
        }

        Response::success([
            'overall_performance'  => $result['data'][0][0] ?? [], // single row
            'monthly_trend'        => $result['data'][1]    ?? [],
            'guard_performance'    => $result['data'][2]    ?? [],
            'response_target_mins' => $target_mins,
            'period_from'          => $from,
            'period_to'            => $to,
        ], 'Panic statistics report generated.');
    }

    // ----------------------------------------------------------
    //  GET /api/reports/resident-activity
    //  Protected — admin only.
    //
    //  Returns ONE result set: per-resident security activity
    //  summary. phone_no excluded (data minimization).
    //  Used to identify residents with frequent incidents or
    //  persistent subscription non-compliance.
    // ----------------------------------------------------------
    public function residentActivity(): void {
        Request::requireMethod('GET');

        $caller    = $this->requireAuth(['admin']);
        [$from, $to] = $this->requireDateRange();

        $result = $this->reportModel->residentActivity(
            $caller['user_id'],
            $from,
            $to
        );

        if (!$result['success']) {
            Response::error($result['message'], $this->errorCode($result['message'], 500));
        }

        Response::success([
            'residents'    => $result['data'],
            'period_from'  => $from,
            'period_to'    => $to,
        ], 'Resident activity report generated.');
    }

    // ----------------------------------------------------------
    //  GET /api/reports/guard-performance
    //  Protected — admin only.
    //
    //  Returns ONE result set: per-guard operational metrics.
    //  Includes weighted activity_score.
    //  No resident data — guard operational metrics only.
    // ----------------------------------------------------------
    public function guardPerformance(): void {
        Request::requireMethod('GET');

        $caller    = $this->requireAuth(['admin']);
        [$from, $to] = $this->requireDateRange();

        $result = $this->reportModel->guardPerformance(
            $caller['user_id'],
            $from,
            $to
        );

        if (!$result['success']) {
            Response::error($result['message'], $this->errorCode($result['message'], 500));
        }

        Response::success([
            'guards'       => $result['data'],
            'period_from'  => $from,
            'period_to'    => $to,
        ], 'Guard performance report generated.');
    }

    // ----------------------------------------------------------
    //  GET /api/reports/payment-summary
    //  Protected — admin only.
    //
    //  Returns THREE result sets:
    //    [0] Revenue summary for period (single row)
    //    [1] Monthly revenue breakdown
    //    [2] Overdue residents (name + house_no, no phone)
    //  No mpesa_ref in any result set (data minimization).
    // ----------------------------------------------------------
    public function paymentSummary(): void {
        Request::requireMethod('GET');

        $caller    = $this->requireAuth(['admin']);
        [$from, $to] = $this->requireDateRange();

        $result = $this->reportModel->paymentSummary(
            $caller['user_id'],
            $from,
            $to
        );

        if (!$result['success']) {
            Response::error($result['message'], $this->errorCode($result['message'], 500));
        }

        Response::success([
            'revenue_summary'     => $result['data'][0][0] ?? [], // single row
            'monthly_breakdown'   => $result['data'][1]    ?? [],
            'overdue_residents'   => $result['data'][2]    ?? [],
            'period_from'         => $from,
            'period_to'           => $to,
        ], 'Payment summary report generated.');
    }

    // ----------------------------------------------------------
    //  GET /api/reports/full-summary
    //  Protected — admin only.
    //
    //  Consolidated estate security report — safe to present
    //  to the management committee without redaction.
    //
    //  Returns SIX result sets:
    //    [0] Estate overview (single row)
    //    [1] Incident overview
    //    [2] Panic alert performance
    //    [3] Payment collection
    //    [4] Police escalation summary
    //    [5] SMS communication summary
    //  No personal data in any result set — aggregate only.
    //  fetchMultiple() handles all 6 sets via next_result() loop.
    // ----------------------------------------------------------
    public function fullSecuritySummary(): void {
        Request::requireMethod('GET');

        $caller    = $this->requireAuth(['admin']);
        [$from, $to] = $this->requireDateRange();

        $result = $this->reportModel->fullSecuritySummary(
            $caller['user_id'],
            $from,
            $to
        );

        if (!$result['success']) {
            Response::error($result['message'], $this->errorCode($result['message'], 500));
        }

        Response::success([
            'estate_overview'        => $result['data'][0][0] ?? [], // single row
            'incident_overview'      => $result['data'][1][0] ?? [], // single row
            'panic_performance'      => $result['data'][2][0] ?? [], // single row
            'payment_collection'     => $result['data'][3][0] ?? [], // single row
            'escalation_summary'     => $result['data'][4][0] ?? [], // single row
            'sms_summary'            => $result['data'][5][0] ?? [], // single row
            'period_from'            => $from,
            'period_to'              => $to,
        ], 'Full security summary report generated.');
    }

    // ----------------------------------------------------------
    //  POST /api/reports/record
    //  Protected — admin only.
    //
    //  Records a report generation event in the reports table.
    //  Called after a report is successfully generated and
    //  optionally exported to a file.
    //  Creates KDPA 2019 audit trail: who generated which
    //  report and when.
    //  $report_type must match the ENUM in the database:
    //    'incident_summary' | 'crime_trend' |
    //    'panic_statistics' | 'resident_activity' |
    //    'guard_performance' | 'subscription_summary' |
    //    'full_security_summary'
    // ----------------------------------------------------------
    public function generateReportRecord(): void {
        Request::requireMethod('POST');

        $caller = $this->requireAuth(['admin']);
        $body   = Request::json();

        $allowed_types = [
            'incident_summary',
            'crime_trend',
            'panic_statistics',
            'resident_activity',
            'guard_performance',
            'subscription_summary',
            'full_security_summary',
        ];

        $report_type = Request::sanitizeString($body['report_type'] ?? '');
        $date_from   = Request::optionalDate($body['date_from']     ?? null);
        $date_to     = Request::optionalDate($body['date_to']       ?? null);
        $file_path   = isset($body['file_path'])
                       ? Request::sanitizeString($body['file_path'])
                       : null;

        if (!in_array($report_type, $allowed_types, true)) {
            Response::error(
                "report_type must be one of: " . implode(', ', $allowed_types) . '.',
                400
            );
        }

        if (!$date_from || !$date_to) {
            Response::error('date_from and date_to are required.', 400);
        }

        if ($date_from > $date_to) {
            Response::error('date_from must be before or equal to date_to.', 400);
        }

        $result = $this->reportModel->generateReportRecord(
            $caller['user_id'],
            $report_type,
            $date_from,
            $date_to,
            $file_path ?: null
        );

        if (!$result['success']) {
            Response::error($result['message'], $this->errorCode($result['message'], 500));
        }

        Response::success(
            $result['data'],
            'Report generation event logged.',
            201
        );
    }

    // ----------------------------------------------------------
    //  Private: requireDateRange()
    //  All reporting procedures require both date_from and
    //  date_to (they SIGNAL on NULL). Validated and returned
    //  as an array [$from, $to] for destructuring.
    // ----------------------------------------------------------
    private function requireDateRange(): array {
        $from = Request::optionalDate($_GET['date_from'] ?? null);
        $to   = Request::optionalDate($_GET['date_to']   ?? null);

        if (!$from || !$to) {
            Response::error(
                'date_from and date_to are required for report generation (format: YYYY-MM-DD).',
                400
            );
        }

        if ($from > $to) {
            Response::error('date_from must be before or equal to date_to.', 400);
        }

        return [$from, $to];
    }
}