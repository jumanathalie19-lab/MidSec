<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  models/PaymentModel.php
//  Calls: sp_create_payment, sp_update_payment_status,
//         sp_check_subscription, sp_get_payment_history,
//         sp_get_all_payments, sp_get_payment_by_id,
//         sp_get_overdue_subscriptions, sp_get_payment_statistics
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class PaymentModel extends BaseModel {

    // ----------------------------------------------------------
    //  [PAY1] createPayment()
    //  Calls: sp_create_payment
    //
    //  M-PESA TWO-STEP FLOW CONTRACT:
    //  ─────────────────────────────────────────────────────────
    //  Step 1 (this method): Called when the PHP controller
    //    triggers a Safaricom STK Push and receives a
    //    CheckoutRequestID. The payment is created as
    //    payment_status = 'pending'. Subscription dates are
    //    NOT set. No subscription is activated yet.
    //
    //  Step 2 (updatePaymentStatus()): Called by the server-side
    //    Safaricom callback handler after Safaricom confirms or
    //    rejects the transaction. Only then is the status set to
    //    'completed' and subscription dates calculated.
    //
    //  NEVER assume a subscription is active after createPayment()
    //  returns. Always wait for the callback confirmation.
    //  ─────────────────────────────────────────────────────────
    //  Procedure enforces:
    //    - Verified active resident only
    //    - Duplicate mpesa_ref rejection (replay attack prevention)
    //    - Maximum one pending payment per resident at a time
    // ----------------------------------------------------------
    public function createPayment(
        int    $user_id,
        float  $amount,
        string $mpesa_ref    // Safaricom CheckoutRequestID
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_create_payment(?, ?, ?)"
        );
        $stmt->bind_param('ids', $user_id, $amount, $mpesa_ref);

        return $this->fetchOne($stmt);
    }

    // ----------------------------------------------------------
    //  [PAY2] updatePaymentStatus()
    //  Calls: sp_update_payment_status
    //
    //  FIX: $callback_ref added as third parameter.
    //  $callback_ref is the Safaricom MpesaReceiptNumber from
    //  the callback — different from the CheckoutRequestID
    //  stored at creation. The procedure updates mpesa_ref
    //  to this definitive receipt number for reconciliation.
    //
    //  SECURITY: This method must ONLY be called from the
    //  server-side Safaricom callback controller. That
    //  controller must:
    //    (a) Validate the request originates from Safaricom
    //        IP ranges (whitelist).
    //    (b) Validate the Safaricom security credential.
    //    (c) Only then call this method.
    //  This method must NEVER be exposed as a user-callable
    //  API endpoint.
    //
    //  Idempotent: if the payment is already 'completed' or
    //  'failed', the procedure returns the current state
    //  without error (handles duplicate Safaricom callbacks).
    //
    //  On 'completed':
    //    - Sets subscription_start to today (or extends from
    //      current active subscription end date if resident
    //      is renewing early — no subscription days lost).
    //    - Sets subscription_end to start + 30 days.
    //  On 'failed':
    //    - Sets payment_status to 'failed'.
    //    - No subscription dates set.
    //    - Resident may initiate a new payment.
    // ----------------------------------------------------------
    public function updatePaymentStatus(
        string $mpesa_ref,      // CheckoutRequestID used at creation
        string $new_status,     // 'completed' or 'failed'
        string $callback_ref    // Safaricom MpesaReceiptNumber from callback
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_update_payment_status(?, ?, ?)"
        );
        $stmt->bind_param('sss', $mpesa_ref, $new_status, $callback_ref);

        return $this->fetchOne($stmt);
    }

    // ----------------------------------------------------------
    //  [PAY3] checkSubscription()
    //  Calls: sp_check_subscription
    //
    //  Resident only — enforced inside the procedure.
    //  Returns minimum fields for subscription status display:
    //    payment_id, payment_status, subscription_start,
    //    subscription_end, subscription_status (derived),
    //    days_remaining, paid_at.
    //  mpesa_ref intentionally excluded (data minimization).
    //  amount intentionally excluded (use getPaymentHistory()).
    // ----------------------------------------------------------
    public function checkSubscription(int $user_id): array {
        $stmt = $this->conn->prepare(
            "CALL sp_check_subscription(?)"
        );
        $stmt->bind_param('i', $user_id);

        return $this->fetchOne($stmt);
    }

    // ----------------------------------------------------------
    //  [PAY4] getPaymentHistory()
    //  Calls: sp_get_payment_history
    //
    //  FIX: $requesting_user_id added as first parameter.
    //  Procedure enforces:
    //    - Residents see only their own history
    //      ($requesting_user_id must equal $target_user_id)
    //    - Guards are blocked entirely (no access to payments)
    //    - Admins may view any resident's history
    //  mpesa_ref included — residents have the right to see
    //  their own transaction references.
    // ----------------------------------------------------------
    public function getPaymentHistory(
        int $requesting_user_id,
        int $target_user_id
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_payment_history(?, ?)"
        );
        $stmt->bind_param('ii', $requesting_user_id, $target_user_id);

        return $this->fetchAll($stmt);
    }

    // ----------------------------------------------------------
    //  [PAY5] getAllPayments()
    //  Calls: sp_get_all_payments
    //
    //  FIX: All four parameters added.
    //  Admin only — enforced inside the procedure.
    //  Returns all payment records with M-Pesa references,
    //  amounts, resident names, house numbers, and phone numbers.
    //  This endpoint must be restricted to admin JWT tokens
    //  at the controller layer.
    //  $status_filter: NULL = all statuses
    //  $date_from / $date_to: NULL = no date filter
    //  Pass null for optional parameters to disable filtering.
    // ----------------------------------------------------------
    public function getAllPayments(
        int     $admin_id,
        ?string $status_filter = null,
        ?string $date_from     = null,
        ?string $date_to       = null
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_all_payments(?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'isss',
            $admin_id,
            $status_filter,
            $date_from,
            $date_to
        );

        return $this->fetchAll($stmt);
    }

    // ----------------------------------------------------------
    //  [PAY6] getPaymentById()  — NEW
    //  Calls: sp_get_payment_by_id
    //
    //  Role-aware single payment retrieval.
    //  Procedure enforces:
    //    - Guards blocked entirely
    //    - Residents see only their own payments
    //    - Admins see any payment
    //  resident_phone returned only to admin callers
    //  (enforced by CASE expression inside the procedure).
    //  Used for: resident receipt view, admin support queries.
    // ----------------------------------------------------------
    public function getPaymentById(
        int $requesting_user_id,
        int $payment_id
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_payment_by_id(?, ?)"
        );
        $stmt->bind_param('ii', $requesting_user_id, $payment_id);

        return $this->fetchOne($stmt);
    }

    // ----------------------------------------------------------
    //  [PAY7] getOverdueSubscriptions()  — NEW
    //  Calls: sp_get_overdue_subscriptions
    //
    //  Admin only — enforced inside the procedure.
    //  Returns all verified active residents whose subscriptions
    //  have lapsed or who have never made a payment, including:
    //    resident_name, house_no, resident_phone, resident_email,
    //    last_subscription_end, days_overdue, overdue_category.
    //  resident_phone included — admin needs this for follow-up.
    //  mpesa_ref excluded — not relevant to overdue view.
    //  Results ordered: most overdue first, never-paid at end.
    // ----------------------------------------------------------
    public function getOverdueSubscriptions(int $admin_id): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_overdue_subscriptions(?)"
        );
        $stmt->bind_param('i', $admin_id);

        return $this->fetchAll($stmt);
    }

    // ----------------------------------------------------------
    //  [PAY8] getPaymentStatistics()  — NEW
    //  Calls: sp_get_payment_statistics
    //
    //  Admin only — enforced inside the procedure.
    //  Returns TWO result sets — uses fetchMultiple():
    //    [0] Overall summary: total records, completed, pending,
    //        failed, total revenue, avg payment, active and
    //        expired subscription counts.
    //    [1] Monthly revenue breakdown: year, month, payment
    //        count, monthly revenue, unique paying residents.
    //  No personal data or mpesa_ref in response —
    //  aggregate financial figures only.
    //  $date_from / $date_to: NULL = all time (no date filter).
    // ----------------------------------------------------------
    public function getPaymentStatistics(
        int     $admin_id,
        ?string $date_from = null,
        ?string $date_to   = null
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_payment_statistics(?, ?, ?)"
        );
        $stmt->bind_param('iss', $admin_id, $date_from, $date_to);

        // Returns two result sets: overall summary + monthly breakdown
        return $this->fetchMultiple($stmt);
    }
}