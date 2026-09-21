<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  controllers/AuthController.php
//
//  Handles: registration, login, staff creation, resident
//           verification, account status, profile retrieval.
//  Model:   UserModel
//  Auth:    Public: register(), login()
//           Protected: all others (valid JWT required)
//           Admin-only: createStaffUser(), verifyResident(),
//                       getPendingResidents(), updateUserStatus()
// ============================================================

require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../config/JwtHandler.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Request.php';

class AuthController {

    private UserModel  $userModel;
    private JwtHandler $jwt;

    public function __construct() {
        $this->userModel = new UserModel();
        $this->jwt       = new JwtHandler();
    }

    // ----------------------------------------------------------
    //  POST /api/auth/register
    //  Public endpoint — no JWT required.
    //
    //  Registers a new resident. All self-registrations are
    //  fixed to role='resident' — enforced in UserModel and
    //  in sp_register_user (role parameter removed in Section 2
    //  of the database refactoring).
    //  New accounts start as verification_status='pending' and
    //  cannot log in until an admin calls verifyResident().
    // ----------------------------------------------------------
    public function register(): void {
        Request::requireMethod('POST');

        $body = Request::json();

        // ---- Input validation --------------------------------
        $first_name = Request::sanitizeString($body['first_name'] ?? '');
        $last_name  = Request::sanitizeString($body['last_name']  ?? '');
        $email      = filter_var($body['email']    ?? '', FILTER_SANITIZE_EMAIL);
        $phone_no   = Request::sanitizeString($body['phone_no']   ?? '');
        $password   = $body['password']  ?? '';
        $house_no   = Request::sanitizeString($body['house_no']   ?? '');

        if (empty($first_name) || empty($last_name)) {
            Response::error('First name and last name are required.', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('A valid email address is required.', 400);
        }

        if (empty($phone_no)) {
            Response::error('Phone number is required.', 400);
        }

        if (strlen($password) < 6) {
            Response::error('Password must be at least 6 characters.', 400);
        }

        if (empty($house_no)) {
            Response::error('House number is required.', 400);
        }

        // ---- Model call --------------------------------------
        $result = $this->userModel->register(
            $first_name,
            $last_name,
            $email,
            $phone_no,
            $password,    // UserModel hashes with PASSWORD_BCRYPT
            $house_no
        );

        if (!$result['success']) {
            // SIGNAL messages (e.g. "Email already registered") are
            // forwarded. System errors return generic message.
            Response::error($result['message'], 409);
        }

        Response::success(
            $result['data'],
            'Registration submitted. Awaiting admin verification.',
            201
        );
    }


        // ----------------------------------------------------------
    //  POST /api/auth/bootstrap
    //  Public, but self-disabling and secret-gated.
    //
    //  Creates the FIRST admin account. Only works once — the
    //  stored procedure refuses to run once any admin exists
    //  (checked at the DB layer, not just here, so it can't be
    //  bypassed even if this PHP check were somehow skipped).
    //  Requires a BOOTSTRAP_SECRET from config/secrets.php to
    //  match, so it can't be triggered by an anonymous visitor
    //  even before the first admin exists.
    // ----------------------------------------------------------
    public function bootstrapFirstAdmin(): void {
        Request::requireMethod('POST');

        $body = Request::json();

        $secrets     = require __DIR__ . '/../config/secrets.php';
        $expectedKey = $secrets['BOOTSTRAP_SECRET'] ?? '';
        $providedKey = $body['bootstrap_key'] ?? '';

        if (empty($expectedKey) || !hash_equals($expectedKey, $providedKey)) {
            error_log('Bootstrap attempt with invalid key from '
                . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            Response::error('Unauthorized.', 403);
        }

        if ($this->userModel->adminExists()) {
            Response::error('Setup already completed. An admin account already exists.', 409);
        }

        $first_name = Request::sanitizeString($body['first_name'] ?? '');
        $last_name  = Request::sanitizeString($body['last_name']  ?? '');
        $email      = filter_var($body['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $phone_no   = Request::sanitizeString($body['phone_no']   ?? '');
        $password   = $body['password'] ?? '';
        $house_no   = Request::sanitizeString($body['house_no']   ?? '');

        if (empty($first_name) || empty($last_name)) {
            Response::error('First name and last name are required.', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('A valid email address is required.', 400);
        }

        if (empty($phone_no)) {
            Response::error('Phone number is required.', 400);
        }

        if (strlen($password) < 8) {
            Response::error('Password must be at least 8 characters.', 400);
        }

        if (empty($house_no)) {
            Response::error('House number is required.', 400);
        }

        $result = $this->userModel->bootstrapFirstAdmin(
            $first_name, $last_name, $email, $phone_no, $password, $house_no
        );

        if (!$result['success']) {
            Response::error($result['message'], 409);
        }

        
        error_log("Bootstrap: first admin account created — {$email}");
        Response::success($result['data'], 'First admin account created successfully.', 201);
    }
    // ----------------------------------------------------------
    //  POST /api/auth/login
    //  Public endpoint — no JWT required.
    //
    //  Authenticates a verified active user and returns a JWT.
    //  UserModel::login() calls sp_get_user_for_login (not the
    //  dropped sp_login_user), performs password_verify() in PHP,
    //  checks status and verification_status, and returns the
    //  signed JWT if all checks pass.
    //  password_hash is never returned to the client.
    // ----------------------------------------------------------
    public function login(): void {
        Request::requireMethod('POST');

        $body = Request::json();

        $email    = filter_var($body['email']    ?? '', FILTER_SANITIZE_EMAIL);
        $password = $body['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('A valid email address is required.', 400);
        }

        if (empty($password)) {
            Response::error('Password is required.', 400);
        }

        $result = $this->userModel->login($email, $password);

        if (!$result['success']) {
            // Use 401 for authentication failures.
            // The model returns a generic "Invalid email or password"
            // message for both unknown email and wrong password —
            // preventing email enumeration.
            $code = str_contains($result['message'], 'deactivated')
                 || str_contains($result['message'], 'pending')
                    ? 403 : 401;
            Response::error($result['message'], $code);
        }

        Response::success([
            'token' => $result['token'],
            'user'  => $result['user'],
        ], 'Login successful.');
    }

    // ----------------------------------------------------------
    //  POST /api/auth/staff
    //  Protected — admin only.
    //
    //  Creates a guard or admin account. Only admins can create
    //  non-resident accounts — residents self-register via
    //  register(). sp_create_staff_user verifies p_admin_id is
    //  a valid admin before inserting.
    // ----------------------------------------------------------
    public function createStaffUser(): void {
        Request::requireMethod('POST');

        $caller = $this->requireAuth(['admin']);

        $body = Request::json();

        $first_name    = Request::sanitizeString($body['first_name']    ?? '');
        $last_name     = Request::sanitizeString($body['last_name']     ?? '');
        $email         = filter_var($body['email']         ?? '', FILTER_SANITIZE_EMAIL);
        $phone_no      = Request::sanitizeString($body['phone_no']      ?? '');
        $password      = $body['password'] ?? '';
        $role          = Request::sanitizeString($body['role']          ?? '');
        $house_no      = Request::sanitizeString($body['house_no']      ?? '');

        if (empty($first_name) || empty($last_name)) {
            Response::error('First name and last name are required.', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('A valid email address is required.', 400);
        }

        if (empty($phone_no)) {
            Response::error('Phone number is required.', 400);
        }

        if (strlen($password) < 6) {
            Response::error('Password must be at least 6 characters.', 400);
        }

        if (!in_array($role, ['guard', 'admin'], true)) {
            Response::error("Role must be 'guard' or 'admin'.", 400);
        }

        $result = $this->userModel->createStaffUser(
            $caller['user_id'],
            $first_name,
            $last_name,
            $email,
            $phone_no,
            $password,
            $role,
            $house_no
        );

        if (!$result['success']) {
            Response::error($result['message'], 409);
        }

        Response::success($result['data'], 'Staff account created successfully.', 201);
    }

    // ----------------------------------------------------------
    //  PATCH /api/auth/verify/{id}
    //  Protected — admin only.
    //
    //  Approves or rejects a pending resident registration.
    //  $id is the target user_id from the URL segment.
    //  $status must be 'verified' or 'rejected'.
    //  sp_verify_resident blocks actioning a non-pending account.
    // ----------------------------------------------------------
    public function verifyResident(int $target_user_id): void {
        Request::requireMethod('PATCH');

        $caller = $this->requireAuth(['admin']);

        $body   = Request::json();
        $status = Request::sanitizeString($body['status'] ?? '');

        if (!in_array($status, ['verified', 'rejected'], true)) {
            Response::error("Status must be 'verified' or 'rejected'.", 400);
        }

        if ($target_user_id <= 0) {
            Response::error('A valid user ID is required.', 400);
        }

        $result = $this->userModel->verifyResident(
            $caller['user_id'],
            $target_user_id,
            $status
        );

        if (!$result['success']) {
            Response::error($result['message'], 422);
        }

        Response::success($result['data'], "Resident account has been {$status}.");
    }

    // ----------------------------------------------------------
    //  GET /api/auth/pending
    //  Protected — admin only.
    //
    //  Returns the list of residents awaiting verification.
    //  Data minimization: returns only fields needed for the
    //  admin review — no password_hash, no status flags.
    // ----------------------------------------------------------
    public function getPendingResidents(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['admin']);

        $result = $this->userModel->getPendingResidents($caller['user_id']);

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        Response::success($result['data'], 'Pending residents retrieved.');
    }

    # AuthController.php — add these two methods
# (a natural spot: right after getPendingResidents())

    // ----------------------------------------------------------
    //  GET /api/auth/residents
    //  Protected — admin only.
    //
    //  Returns ALL residents (any verification_status), unlike
    //  getPendingResidents() which only returns pending ones.
    //  Optional ?search= filters by name, email, or house_no.
    // ----------------------------------------------------------
    public function getAllResidents(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['admin']);

        $search = Request::sanitizeString($_GET['search'] ?? '');

        $result = $this->userModel->getAllResidents(
            $caller['user_id'],
            $search
        );

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        Response::success($result['data'], 'Residents retrieved.');
    }

    // ----------------------------------------------------------
    //  GET /api/auth/staff
    //  Protected — admin only.
    //
    //  Returns ALL staff (guard + admin accounts).
    //  Shares the /auth/staff URL with POST createStaffUser() —
    //  differentiated by HTTP method in the router.
    //  Optional ?search= filters by name, email, or role.
    // ----------------------------------------------------------
    public function getAllStaff(): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['admin']);

        $search = Request::sanitizeString($_GET['search'] ?? '');

        $result = $this->userModel->getAllStaff(
            $caller['user_id'],
            $search
        );

        if (!$result['success']) {
            Response::error($result['message'], 500);
        }

        Response::success($result['data'], 'Staff retrieved.');
    }




    // ----------------------------------------------------------
    //  PATCH /api/auth/status/{id}
    //  Protected — admin only.
    //
    //  Activates or deactivates any account.
    //  sp_update_user_status blocks self-deactivation (an admin
    //  cannot deactivate their own account).
    // ----------------------------------------------------------
    public function updateUserStatus(int $target_user_id): void {
        Request::requireMethod('PATCH');

        $caller = $this->requireAuth(['admin']);

        $body   = Request::json();
        $status = Request::sanitizeString($body['status'] ?? '');

        if (!in_array($status, ['active', 'inactive'], true)) {
            Response::error("Status must be 'active' or 'inactive'.", 400);
        }

        if ($target_user_id <= 0) {
            Response::error('A valid user ID is required.', 400);
        }

        $result = $this->userModel->updateUserStatus(
            $caller['user_id'],
            $target_user_id,
            $status
        );

        if (!$result['success']) {
            Response::error($result['message'], 422);
        }

        Response::success($result['data'], "Account set to {$status}.");
    }

    // ----------------------------------------------------------
    //  GET /api/auth/profile
    //  GET /api/auth/profile/{id}   (guard/admin only)
    //  Protected — any verified authenticated user.
    //
    //  With no {id}: returns the caller's own profile.
    //  With {id}:    guard/admin may retrieve any profile;
    //                resident requesting another user's profile
    //                is blocked inside sp_get_user_profile.
    //  password_hash is never returned by the procedure.
    // ----------------------------------------------------------
    public function getProfile(?int $target_user_id = null): void {
        Request::requireMethod('GET');

        $caller = $this->requireAuth(['resident', 'guard', 'admin']);

        // Default: user retrieves their own profile
        $target = $target_user_id ?? $caller['user_id'];

        if ($target <= 0) {
            Response::error('A valid user ID is required.', 400);
        }

        $result = $this->userModel->getUserProfile(
            $caller['user_id'],
            $target
        );

        if (!$result['success']) {
            $code = str_contains($result['message'], 'Access denied') ? 403 : 404;
            Response::error($result['message'], $code);
        }

        Response::success($result['data'], 'Profile retrieved.');
    }

    // ----------------------------------------------------------
    //  Private: requireAuth()
    //  Extracts and validates the JWT from the Authorization
    //  header. Verifies the caller's role is in $allowedRoles.
    //  Returns the decoded JWT payload on success.
    //  Terminates the request with 401 or 403 on failure.
    //
    //  Defence in depth: role is checked here at the controller
    //  layer AND inside the stored procedure. If either check
    //  fails the request is blocked.
    // ----------------------------------------------------------
    private function requireAuth(array $allowedRoles): array {
        $headers = getallheaders();
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
            Response::error('You do not have permission to perform this action.', 403);
        }

        return $payload;
    }
}