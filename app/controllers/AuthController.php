<?php
// app/controllers/AuthController.php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Role.php';

class AuthController {
    protected $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    public function register($email, $password, $name = null, $role_id = 3) {
        // basic validation
        if (empty($email) || empty($password)) return ['success' => false, 'message' => 'Email và mật khẩu là bắt buộc'];
        if ($this->userModel->findByEmail($email)) return ['success' => false, 'message' => 'Email đã tồn tại'];

        $id = $this->userModel->create($email, $password, $role_id, $name);
        if ($id) return ['success' => true, 'user_id' => $id];
        return ['success' => false, 'message' => 'Không thể tạo người dùng'];
    }

    public function login($email, $password) {
        $user = $this->userModel->verifyPassword($email, $password);
        if ($user) {
            // initialize session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'] ?? null;
            if (!empty($user['avatar_path'])) {
                $_SESSION['avatar_url'] = BASE_URL . '/' . ltrim($user['avatar_path'], '/');
            } else {
                $_SESSION['avatar_url'] = null;
            }
            $_SESSION['avatar_checked'] = true;
            return ['success' => true, 'user' => $user];
        }
        return ['success' => false, 'message' => 'Email hoặc mật khẩu không đúng'];
    }

    public function logout() {
        session_unset();
        session_destroy();
    }
}

