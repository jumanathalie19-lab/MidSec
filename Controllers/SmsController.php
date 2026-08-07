<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  controllers/SmsController.php
//
//  Handles: SMS communication logging, per-incident SMS
//           retrieval, full SMS audit log, SMS statistics.
//  Model:   SmsModel
//
//  Role access summary:
//    log()           — guard, admin
//    getByIncident() — guard (no phone), admin (phone visible)
//    index()         — admin only (contains recipient phones)
//    statistics()    — admin only
// ============================================================

require_once __DIR__ . '/../models/SmsModel.php';
require_once __DIR__ . '/../config/JwtHandler.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Request.php';

class SmsController {

    private SmsModel   $smsModel;
    private JwtHandler $jwt;

    // Allowed delivery_status ENUM values from Section 1 schema
    private const ALLOWED_DELIVERY_STATUSES = ['sent', 'delivered', 'failed'];

    public function __construct() {
        $this->smsModel = new SmsModel();
        $this->jwt      = new JwtHandler();
    }

    // ----------------------------------------------------------
    //  POST /api/sms/log
    //  Protected — guard, admin only.
    //
    //  Creates an audit record of an SMS sent to a resident
    //  in connection with a security incident.
    //  The procedure:
    //    - Verifies caller is a verified active guard or admin.
    //    - Confirms incident exists and is not soft-deleted.
    //    - Records the SMS log and creates a meta audit_log entry.
    //  recipient_phone is stored in sms_logs but excluded from
    //  the audit_log entry (data minimization).
    //
    //  NOTE: This controller only LOGS that an SMS was sent.
    //  The actual SMS delivery is handled by a separate
    //  Safaricom SMS / Africa's Talking service class.
    //  That service calls this endpoint after a successful send.
    // ----------------------------------------------------------
    public function log(): void {
        Request::requireMethod('POST');

        $caller = $this->requireAuth(['guard', 'admin']);
        $body   = Request::json();

        // ---- Input validation --------------------------------
        $incident_id     = filter_var(
            $body['incident_id'] ?? null, FILTER_VALIDATE_INT
        );
        $recipient_phone = Request::sanitizeString($body['recipient_phone'] ?? '');
        $message         = Request::sanitizeString($body['message']         ?? '');
        $delivery_status = Request::sanitizeString($body['delivery_status'] ?? 'sent');

        if ($incident_id === false || $incident_id === null || $incident_id <= 0) {
            Response::error('A valid incident ID is required.', 400);
        }

        if (empty($recipient_phone)) {
            Response::error('Recipient phone number is required.', 400);
        }

        // Kenyan mobile phone number validation
        // Accepts: 07XXXXXXXX, 01XXXXXXXX (10 digits starting with 07 or 01)
        if (!preg_match('/^0[17][0-9]{8}$/', $recipient_phone)) {
            Response::error(
                'Invalid phone number format. Expected Kenyan mobile format (e.g. 0712345678).',
                400
            );
        }

        if (empty($message)) {
            Response::error('SMS message content is required.', 400);
        }

        if (!in_array($delivery_status, self::ALLOWED_DELIVERY_STATUSES, true)) {
            Response::error(
                "delivery_status must be one of: " .
                implode(', ', self::ALLOWED_DELIVERY_STATUSES) . '.',
                400
            );
        }

        // ---- Model call --------------------------------------
        $result = $this->smsModel->logSms(
            (int)$incident_id,
            $caller['user_id'],   // guard_id — procedure verifies role
            $recipient_phone,
            $message,
            $delivery_status
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied') ? 403
                  : (str_contains($result['message'], 'not found')    ? 404 : 422);
            Response::error($result['message'], $code);
        }

        Response::success(
            $result['data'],
            'SMS communication logged successfully.',
            201
        );
    }

    // ----------------------------------------------------------
    //  GET /api/sms/incident/{incident_id}
    //  Protected — guard, admin only.
    //
    //  Returns SMS audit records for a specific incident.
    //  Role-aware field disclosure enforced by the procedure:
    //    guard → recipient_phone returned as NULL
    //            (guards verify delivery without seeing phone)
    //    admin → recipient_phone returned
    //
    //  $caller['user_id'] passed as $requesting_user_id to
    //  enable the procedure's CASE expression for disclosure.
    // ----------------------------------------------------------
    public function getByIncident(int $incident_id): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['guard', 'admin']);

        if ($incident_id <= 0) {
            Response::error('A valid incident ID is required.', 400);
        }

        $result = $this->smsModel->getSmsByIncident(
            $caller['user_id'],   // requesting_user_id — drives phone disclosure
            $incident_id
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied') ? 403
                  : (str_contains($result['message'], 'not found')    ? 404 : 500);
            Response::error($result['message'], $code);
        }

        Response::success($result['data'], 'SMS logs for incident retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/sms
    //  Protected — admin only.
    //
    //  Returns all SMS audit records across all incidents.
    //  Contains recipient_phone — MUST be admin-only.
    //  This endpoint must NEVER be accessible to guards or
    //  residents. Role enforced at controller AND procedure.
    //  Optional filters:
    //    ?status=sent|delivered|failed
    //    ?date_from=YYYY-MM-DD
    //    ?date_to=YYYY-MM-DD
    // ----------------------------------------------------------
    public function index(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['admin']);

        $delivery_status = $this->optionalEnum(
            $_GET['status']    ?? null,
            self::ALLOWED_DELIVERY_STATUSES
        );
        $date_from = Request::optionalDate($_GET['date_from'] ?? null);
        $date_to   = Request::optionalDate($_GET['date_to']   ?? null);

        if ($date_from && $date_to && $date_from > $date_to) {
            Response::error('date_from must be before or equal to date_to.', 400);
        }

        $result = $this->smsModel->getAllSmsLogs(
            $caller['user_id'],
            $delivery_status,
            $date_from,
            $date_to
        );

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        Response::success($result['data'], 'SMS logs retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/sms/statistics
    //  Protected — admin only.
    //
    //  Returns SMS delivery performance statistics.
    //
    //  MULTIPLE RESULT SETS:
    //  SmsModel::getSmsStatistics() returns fetchMultiple()
    //  format: ['data' => [ [set1rows], [set2rows] ]]
    //    [0] Overall delivery summary:
    //        total_sms_logged, total_sent, total_delivered,
    //        total_failed, delivery_success_rate_pct,
    //        incidents_with_sms, guards_who_sent_sms
    //    [1] Per-guard SMS activity:
    //        guard_name, total_sms_sent, delivered_count,
    //        failed_count, failure_rate_pct,
    //        unique_incidents_handled
    //  No recipient_phone in either result set — aggregate only.
    //  $date_from / $date_to: NULL = all time.
    // ----------------------------------------------------------
    public function statistics(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['admin']);

        $date_from = Request::optionalDate($_GET['date_from'] ?? null);
        $date_to   = Request::optionalDate($_GET['date_to']   ?? null);

        if ($date_from && $date_to && $date_from > $date_to) {
            Response::error('date_from must be before or equal to date_to.', 400);
        }

        $result = $this->smsModel->getSmsStatistics(
            $caller['user_id'],
            $date_from,
            $date_to
        );

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        // Destructure both result sets into named keys
        Response::success([
            'overall_delivery'   => $result['data'][0][0] ?? [], // single summary row
            'guard_activity'     => $result['data'][1]    ?? [], // per-guard rows
        ], 'SMS statistics retrieved.');
    }

    // ----------------------------------------------------------
    //  Private: requireAuth()
    // ----------------------------------------------------------
    private function requireAuth(array $allowedRoles): array {
        $headers    = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!str_starts_with($authHeader, 'Bearer ')) {
            Response::error('Authentication token required.', 401);
        }

        $token   = trim(substr($authHeader, 7));
        $payload = $this->jwt->decode($token);

        if (!$payload) {
            Response::error('Invalid or expired token.', 401);
        }

        if (!in_array($payload['role'], $allowedRoles, true)) {
            Response::error(
                'You do not have permission to perform this action.',
                403
            );
        }

        return $payload;
    }

    // ----------------------------------------------------------
    //  Private: optionalEnum()
    // ----------------------------------------------------------
    private function optionalEnum(?string $value, array $allowed): ?string {
        if ($value === null || $value === '') return null;
        $clean = Request::sanitizeString($value);
        if (!in_array($clean, $allowed, true)) {
            Response::error(
                "Invalid value '{$clean}'. Allowed: " . implode(', ', $allowed) . '.',
                400
            );
        }
        return $clean;
    }
}