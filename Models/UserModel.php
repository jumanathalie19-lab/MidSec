<?php
// ============================================================
//  MIDVIEW SECURITY APP
//  models/UserModel.php  — FINAL (extends BaseModel)
//  Cross-model fix: extends BaseModel, __destruct removed,
//  private helpers removed (inherited from BaseModel).
// ============================================================

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../config/JwtHandler.php';

class UserModel extends BaseModel {

    private JwtHandler $jwt;

    public function __construct() {
        parent::__construct();
        $this->jwt = new JwtHandler();
    }

    // [A1] register()
    public function register(
        string $first_name,
        string $last_name,
        string $email,
        string $phone_no,
        string $password,
        string $house_no
    ): array {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare(
            "CALL sp_register_user(?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'ssssss',
            $first_name, $last_name, $email,
            $phone_no, $password_hash, $house_no
        );

        return $this->fetchOne($stmt);
    }

    // [A2] login()
    public function login(string $email, string $password): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_user_for_login(?)"
        );
        $stmt->bind_param('s', $email);

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => $error];
        }

        $result = $stmt->get_result();
        $user   = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Your account has been deactivated. Please contact the estate admin.'];
        }

        if ($user['verification_status'] !== 'verified') {
            return ['success' => false, 'message' => 'Your account is pending admin verification. You will be notified once approved.'];
        }

        $token = $this->jwt->encode([
            'user_id'    => $user['user_id'],
            'role'       => $user['role'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
        ]);

        return [
            'success' => true,
            'token'   => $token,
            'user'    => [
                'user_id'    => $user['user_id'],
                'role'       => $user['role'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
            ],
        ];
    }

    // [A3] createStaffUser()
    public function createStaffUser(
        int    $admin_id,
        string $first_name,
        string $last_name,
        string $email,
        string $phone_no,
        string $password,
        string $role,
        string $house_no = ''
    ): array {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare(
            "CALL sp_create_staff_user(?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'isssssss',
            $admin_id, $first_name, $last_name, $email,
            $phone_no, $password_hash, $role, $house_no
        );

        return $this->fetchOne($stmt);
    }

    // [A4] verifyResident()
    public function verifyResident(
        int    $admin_id,
        int    $target_user_id,
        string $new_status
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_verify_resident(?, ?, ?)"
        );
        $stmt->bind_param('iis', $admin_id, $target_user_id, $new_status);

        return $this->fetchOne($stmt);
    }

    // [A5] getPendingResidents()
    public function getPendingResidents(int $admin_id): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_pending_residents(?)"
        );
        $stmt->bind_param('i', $admin_id);

        return $this->fetchAll($stmt);
    }

    // [A6] updateUserStatus()
    public function updateUserStatus(
        int    $admin_id,
        int    $target_user_id,
        string $new_status
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_update_user_status(?, ?, ?)"
        );
        $stmt->bind_param('iis', $admin_id, $target_user_id, $new_status);

        return $this->fetchOne($stmt);
    }

    // [A7] getUserProfile()
    public function getUserProfile(
        int $requesting_user_id,
        int $target_user_id
    ): array {
        $stmt = $this->conn->prepare(
            "CALL sp_get_user_profile(?, ?)"
        );
        $stmt->bind_param('ii', $requesting_user_id, $target_user_id);

        return $this->fetchOne($stmt);
    }
}