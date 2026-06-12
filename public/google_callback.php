<?php
// Callback nhận code từ Google rồi đưa qua GoogleAuthController để đổi
// thành access_token + userinfo, sau đó đăng nhập (hoặc tạo) user.
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/app/controllers/GoogleAuthController.php';

$code = $_GET['code'] ?? '';
if ($code === '') {
    $_SESSION['flash_error'] = 'Google trả về thiếu authorization code.';
    header('Location: ' . BASE_URL . '/account/login.php');
    exit;
}

$ctrl = new GoogleAuthController();
$user = $ctrl->handleCallback($code);

if (!$user) {
    $_SESSION['flash_error'] = 'Không xác thực được tài khoản Google. '
        . 'Vui lòng thử lại hoặc dùng email/mật khẩu.';
    header('Location: ' . BASE_URL . '/account/login.php');
    exit;
}

// handleCallback() đã set $_SESSION['user_id'], 'role_id', 'email'.
$role = (int)($_SESSION['role_id'] ?? 3);
if ($role === 1) {
    header('Location: ' . ADMIN_URL . '/index.php');
} elseif ($role === 2) {
    header('Location: ' . BASE_URL . '/employer/admin/dashboard.php');
} else {
    header('Location: ' . BASE_URL . '/dashboard.php');
}
exit;
