<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Role.php';
require_once dirname(__DIR__, 2) . '/app/models/User.php';

if (empty($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ADMIN_URL . '/roles/index.php');
    exit;
}

$roleId = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
if ($roleId <= 0) {
    $_SESSION['role_flash'] = [
        'type' => 'danger',
        'message' => 'Thiếu mã vai trò để xoá.'
    ];
    header('Location: ' . ADMIN_URL . '/roles/index.php');
    exit;
}

if (in_array($roleId, [1, 2, 3], true)) {
    $_SESSION['role_flash'] = [
        'type' => 'warning',
        'message' => 'Không thể xoá các vai trò hệ thống mặc định.'
    ];
    header('Location: ' . ADMIN_URL . '/roles/index.php');
    exit;
}

$userModel = new User();
$roleModel = new Role();

$usage = $userModel->countByRole($roleId);
if ($usage > 0) {
    $_SESSION['role_flash'] = [
        'type' => 'warning',
        'message' => 'Không thể xoá vai trò đang được ' . number_format($usage) . ' người dùng sử dụng.'
    ];
    header('Location: ' . ADMIN_URL . '/roles/index.php');
    exit;
}

$roleModel->syncPermissions($roleId, []);
$deleted = $roleModel->deleteRole($roleId);

$_SESSION['role_flash'] = [
    'type' => $deleted ? 'success' : 'danger',
    'message' => $deleted ? 'Đã xoá vai trò thành công.' : 'Không thể xoá vai trò. Vui lòng thử lại.'
];

header('Location: ' . ADMIN_URL . '/roles/index.php');
exit;
?>
