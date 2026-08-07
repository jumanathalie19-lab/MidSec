<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  controllers/PaymentController.php
//
//  Handles: M-Pesa payment initiation, Safaricom callback,
//           subscription status, payment history, admin views,
//           overdue subscriptions, payment statistics.
//  Model:   PaymentModel
//
//  Role access summary:
//    initiate()         — resident only
//    callback()         — Safaricom server (IP validation only)
//    checkSubscription()— resident only
//    history()          — resident (own), admin
//    index()            — admin only
//    show()             — resident (own), admin
//    overdue()          — admin only
//    statistics()       — admin only
//
//  Guards are explicitly blocked from ALL payment endpoints.
// ============================================================

require_once __DIR__ . '/../models/PaymentModel.php';
require_once __DIR__ . '/../config/JwtHandler.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Request.php';

class PaymentController {

    private PaymentModel $paymentModel;
    private JwtHandler   $jwt;

    // Allowed status values for index() filter
    private const ALLOWED_STATUS_FILTERS = ['pending', 'completed', 'failed'];

    // Allowed status values for the Safaricom callback
    private const ALLOWED_CALLBACK_STATUSES = ['completed', 'failed'];

    // Safaricom M-Pesa callback IP ranges (CIDR notation).
    // Source: Safaricom Daraja API documentation.
    // Update these if Safaricom publishes new ranges.
    // In development/testing, add '127.0.0.1' to this list.
    private const SAFARICOM_IP_RANGES = [
        '196.201.214.200/29',
        '196.201.214.216/29',
        '196.201.214.136/29',
        '196.201.213.0/25',
        '196.201.214.128/25',
        '196.201.212.128/25',
        // Development only — remove in production:
        // '127.0.0.1/32',
    ];

    public function __construct() {
        $this->paymentModel = new PaymentModel();
        $this->jwt          = new JwtHandler();
    }

    // ----------------------------------------------------------
    //  POST /api/payments/initiate
    //  Protected — resident only.
    //
    //  M-PESA TWO-STEP FLOW — STEP 1:
    //  ─────────────────────────────────────────────────────────
    //  1. Resident calls this endpoint with amount and mpesa_ref
    //     (the CheckoutRequestID returned by the controller's
    //     prior call to the Safaricom STK Push API).
    //  2. Payment created as payment_status = 'pending'.
    //     Subscription NOT yet activated.
    //  3. Safaricom sends callback to /api/payments/callback.
    //  4. callback() sets status to 'completed' and activates
    //     subscription dates.
    //
    //  The controller is responsible for triggering the
    //  Safaricom STK Push API call and passing the resulting
    //  CheckoutRequestID here as mpesa_ref. That API call is
    //  outside the scope of this controller — it is handled
    //  by a dedicated Safaricom service class.
    //  ─────────────────────────────────────────────────────────
    //  Procedure also enforces:
    //    - Maximum one pending payment per resident at a time.
    //    - Duplicate mpesa_ref rejection (replay prevention).
    // ----------------------------------------------------------
    public function initiate(): void {
        Request::requireMethod('POST');

        $caller = $this->requireAuth(['resident']);
        $body   = Request::json();

        // ---- Input validation --------------------------------
        $amount    = filter_var($body['amount']    ?? null, FILTER_VALIDATE_FLOAT);
        $mpesa_ref = trim($body['mpesa_ref'] ?? '');

        if ($amount === false || $amount === null || $amount <= 0) {
            Response::error('A valid positive payment amount is required.', 400);
        }

        if (empty($mpesa_ref)) {
            Response::error('M-Pesa reference (CheckoutRequestID) is required.', 400);
        }

        // mpesa_ref length check — Safaricom CheckoutRequestIDs
        // are typically up to 50 characters (matching DB column)
        if (strlen($mpesa_ref) > 50) {
            Response::error('M-Pesa reference must not exceed 50 characters.', 400);
        }

        // ---- Model call --------------------------------------
        $result = $this->paymentModel->createPayment(
            $caller['user_id'],
            (float)$amount,
            $mpesa_ref
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied')           ? 403
                  : (str_contains($result['message'], 'already been recorded')  ? 409
                  : (str_contains($result['message'], 'pending payment already') ? 409 : 422));
            Response::error($result['message'], $code);
        }

        // Explicitly communicate pending state — subscription
        // is NOT yet active. Client must wait for M-Pesa prompt.
        Response::success([
            'payment'            => $result['data'],
            'subscription_active'=> false,
            'next_step'          => 'Complete the M-Pesa prompt on your phone. Your subscription will activate once payment is confirmed by Safaricom.',
        ], 'Payment initiated.', 201);
    }

    // ----------------------------------------------------------
    //  POST /api/payments/callback
    //  NOT protected by JWT — called by Safaricom servers.
    //
    //  M-PESA TWO-STEP FLOW — STEP 2:
    //  ─────────────────────────────────────────────────────────
    //  Called by Safaricom after the resident completes (or
    //  fails) the STK Push prompt on their phone.
    //
    //  SECURITY:
    //  This endpoint must NEVER require a user JWT (Safaricom
    //  servers do not authenticate as application users).
    //  Instead, it validates:
    //    (a) The request originates from a Safaricom IP range.
    //    (b) The ResultCode and required fields are present.
    //
    //  On 'completed':
    //    - subscription_start set to today (or extended from
    //      current active subscription end — no days lost).
    //    - subscription_end set to start + 30 days.
    //  On 'failed':
    //    - payment_status set to 'failed'.
    //    - Resident may initiate a new payment attempt.
    //
    //  IDEMPOTENCY: Safaricom may deliver the same callback
    //  multiple times. sp_update_payment_status handles this
    //  gracefully — returns current state without error on
    //  duplicate callbacks. The controller returns 200 in
    //  both first-processing and duplicate cases.
    //
    //  Safaricom expects a specific JSON acknowledgement
    //  response (ResultCode: 0) to stop retrying.
    // ----------------------------------------------------------
    public function callback(): void {
        Request::requireMethod('POST');

        // ---- Safaricom IP validation -------------------------
        // CRITICAL: Validate before processing any payload.
        // An attacker who knows this URL can fabricate callbacks
        // if IP validation is absent or bypassed.
        $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR']
                  ?? $_SERVER['REMOTE_ADDR']
                  ?? '';

        // Strip port if present (e.g. '196.201.214.201:54321')
        $client_ip = explode(':', $client_ip)[0];
        $client_ip = trim(explode(',', $client_ip)[0]);

        if (!$this->isAllowedSafaricomIp($client_ip)) {
            // Log the rejected IP for security monitoring.
            error_log("M-Pesa callback rejected from unauthorized IP: {$client_ip}");

            // Return Safaricom-format acknowledgement even on
            // rejection to prevent Safaricom retry loops while
            // still blocking the fraudulent callback.
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode([
                'ResultCode'        => 1,
                'ResultDesc'        => 'Rejected.',
            ]);
            exit;
        }

        // ---- Parse Safaricom callback payload ----------------
        // Safaricom sends nested JSON — parse from php://input
        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($body)) {
            $this->safaricomAck(1, 'Invalid payload.');
        }

        // Extract from Safaricom's STKCallback structure
        $stkCallback    = $body['Body']['stkCallback']              ?? [];
        $result_code    = $stkCallback['ResultCode']                ?? null;
        $checkout_id    = $stkCallback['CheckoutRequestID']         ?? '';
        $callback_items = $stkCallback['CallbackMetadata']['Item']  ?? [];

        if (empty($checkout_id)) {
            $this->safaricomAck(1, 'Missing CheckoutRequestID.');
        }

        // Extract MpesaReceiptNumber from callback metadata
        $mpesa_receipt = '';
        foreach ($callback_items as $item) {
            if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                $mpesa_receipt = $item['Value'] ?? '';
                break;
            }
        }

        // Determine payment outcome from Safaricom ResultCode
        // ResultCode 0 = success, any other = failure
        $new_status = ($result_code === 0) ? 'completed' : 'failed';

        // ---- Model call --------------------------------------
        // p_mpesa_ref    = CheckoutRequestID (used at creation)
        // p_new_status   = 'completed' or 'failed'
        // p_callback_ref = MpesaReceiptNumber (actual receipt)
        //   The procedure updates mpesa_ref to the receipt number
        //   so the stored reference matches the resident's M-Pesa
        //   statement for reconciliation.
        $result = $this->paymentModel->updatePaymentStatus(
            $checkout_id,
            $new_status,
            $mpesa_receipt
        );

        if (!$result['success']) {
            // Log for investigation — do not expose details
            // to Safaricom in the acknowledgement.
            error_log("M-Pesa callback processing error: " . $result['message']);
            $this->safaricomAck(1, 'Processing error.');
        }

        // Acknowledge to Safaricom — ResultCode 0 stops retries.
        // Idempotent: duplicate callbacks also return 0 since
        // the model returns success with "no changes made" message.
        $this->safaricomAck(0, 'Accepted.');
    }

    // ----------------------------------------------------------
    //  GET /api/payments/subscription
    //  Protected — resident only.
    //
    //  Returns the resident's current subscription status.
    //  Data minimization: mpesa_ref excluded from response.
    //  Resident only needs: status, dates, days remaining.
    //  For full payment history use history().
    // ----------------------------------------------------------
    public function checkSubscription(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['resident']);

        $result = $this->paymentModel->checkSubscription($caller['user_id']);

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        // Procedure returns null data if no payment exists yet
        if (empty($result['data'])) {
            Response::success(
                ['subscription_status' => 'no_subscription'],
                'No payment record found. Please initiate a payment to activate your subscription.'
            );
        }

        Response::success($result['data'], 'Subscription status retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/payments/history
    //  GET /api/payments/history/{user_id}  (admin only)
    //  Protected — resident (own), admin.
    //  Guards explicitly blocked.
    //
    //  Without {user_id}: resident views their own history.
    //  With {user_id}: admin may view any resident's history.
    //  mpesa_ref included — resident has right to see own refs.
    //  Ownership enforced in procedure AND at controller layer.
    // ----------------------------------------------------------
    public function history(?int $target_user_id = null): void {
        Request::requireMethod('GET');

        // Guards blocked at controller layer before hitting DB
        $caller = $this->requireAuth(['resident', 'admin']);

        $target = $target_user_id ?? $caller['user_id'];

        if ($target <= 0) {
            Response::error('A valid user ID is required.', 400);
        }

        // Early resident IDOR block — also enforced in procedure
        if ($caller['role'] === 'resident' && $target !== $caller['user_id']) {
            Response::error(
                'Residents can only view their own payment history.',
                403
            );
        }

        $result = $this->paymentModel->getPaymentHistory(
            $caller['user_id'],  // requesting_user_id — ownership check
            $target
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied') ? 403
                  : (str_contains($result['message'], 'not found')    ? 404 : 500);
            Response::error($result['message'], $code);
        }

        Response::success($result['data'], 'Payment history retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/payments
    //  Protected — admin only.
    //
    //  Returns all payment records across all residents.
    //  Contains M-Pesa references, amounts, resident phone
    //  numbers — must be admin-only at controller AND DB layer.
    //  Optional filters:
    //    ?status=pending|completed|failed
    //    ?date_from=YYYY-MM-DD
    //    ?date_to=YYYY-MM-DD
    // ----------------------------------------------------------
    public function index(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['admin']);

        $status_filter = $this->optionalEnum(
            $_GET['status']    ?? null,
            self::ALLOWED_STATUS_FILTERS
        );
        $date_from = Request::optionalDate($_GET['date_from'] ?? null);
        $date_to   = Request::optionalDate($_GET['date_to']   ?? null);

        if ($date_from && $date_to && $date_from > $date_to) {
            Response::error('date_from must be before or equal to date_to.', 400);
        }

        $result = $this->paymentModel->getAllPayments(
            $caller['user_id'],
            $status_filter,
            $date_from,
            $date_to
        );

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        Response::success($result['data'], 'Payments retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/payments/{id}
    //  Protected — resident (own), admin.
    //  Guards explicitly blocked.
    //
    //  IDOR protection: $caller['user_id'] passed as
    //  $requesting_user_id — procedure blocks residents from
    //  accessing other residents' payment records.
    //  resident_phone returned to admin only (CASE in procedure).
    // ----------------------------------------------------------
    public function show(int $payment_id): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['resident', 'admin']);

        if ($payment_id <= 0) {
            Response::error('A valid payment ID is required.', 400);
        }

        $result = $this->paymentModel->getPaymentById(
            $caller['user_id'],  // requesting_user_id — RBAC + ownership
            $payment_id
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied') ? 403
                  : (str_contains($result['message'], 'not found')    ? 404 : 422);
            Response::error($result['message'], $code);
        }

        Response::success($result['data'], 'Payment record retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/payments/overdue
    //  Protected — admin only.
    //
    //  Identifies verified residents whose subscriptions have
    //  lapsed or who have never made a payment.
    //  Returns resident_phone for admin follow-up contact.
    //  mpesa_ref NOT included — not relevant to overdue view.
    //  Results ordered: most overdue first, never-paid at end.
    // ----------------------------------------------------------
    public function overdue(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['admin']);

        $result = $this->paymentModel->getOverdueSubscriptions(
            $caller['user_id']
        );

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        Response::success($result['data'], 'Overdue subscriptions retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/payments/statistics
    //  Protected — admin only.
    //
    //  Returns financial summary statistics.
    //
    //  MULTIPLE RESULT SETS:
    //  PaymentModel::getPaymentStatistics() returns fetchMultiple()
    //  format: ['data' => [ [set1rows], [set2rows] ]]
    //    [0] Overall summary: totals, revenue, avg payment,
    //        active/expired subscription counts.
    //    [1] Monthly revenue breakdown: year, month, payment
    //        count, revenue, unique paying residents.
    //  No mpesa_ref or personal data in either result set.
    // ----------------------------------------------------------
    public function statistics(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['admin']);

        $date_from = Request::optionalDate($_GET['date_from'] ?? null);
        $date_to   = Request::optionalDate($_GET['date_to']   ?? null);

        if ($date_from && $date_to && $date_from > $date_to) {
            Response::error('date_from must be before or equal to date_to.', 400);
        }

        $result = $this->paymentModel->getPaymentStatistics(
            $caller['user_id'],
            $date_from,
            $date_to
        );

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        // Destructure both result sets into named keys
        Response::success([
            'overall_summary'    => $result['data'][0][0] ?? [],  // single summary row
            'monthly_breakdown'  => $result['data'][1]    ?? [],  // monthly rows
        ], 'Payment statistics retrieved.');
    }

    // ----------------------------------------------------------
    //  Private: isAllowedSafaricomIp()
    //  Validates whether the given IP address falls within
    //  any of the allowed Safaricom callback CIDR ranges.
    //  Pure PHP implementation — no external library required.
    // ----------------------------------------------------------
    private function isAllowedSafaricomIp(string $ip): bool {
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        foreach (self::SAFARICOM_IP_RANGES as $cidr) {
            [$subnet, $bits] = explode('/', $cidr);
            $bits = (int)$bits;

            $ip_long     = ip2long($ip);
            $subnet_long = ip2long($subnet);

            if ($ip_long === false || $subnet_long === false) {
                continue;
            }

            $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));

            if (($ip_long & $mask) === ($subnet_long & $mask)) {
                return true;
            }
        }

        return false;
    }

    // ----------------------------------------------------------
    //  Private: safaricomAck()
    //  Sends Safaricom-format JSON acknowledgement and exits.
    //  ResultCode 0 = success (stops Safaricom retry loop).
    //  ResultCode 1 = failure (Safaricom will retry).
    // ----------------------------------------------------------
    private function safaricomAck(int $code, string $desc): never {
        http_response_code(200); // Always 200 for Safaricom callbacks
        header('Content-Type: application/json');
        echo json_encode([
            'ResultCode' => $code,
            'ResultDesc' => $desc,
        ]);
        exit;
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