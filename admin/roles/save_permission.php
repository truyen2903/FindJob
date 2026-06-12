<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Permission.php';

if (empty($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ADMIN_URL . '/roles/index.php');
    exit;
}

$name = trim((string)($_POST['name'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$permissionId = isset($_POST['permission_id']) ? (int)$_POST['permission_id'] : 0;

if ($name === '') {
    $_SESSION['role_flash'] = [
        'type' => 'danger',
        'message' => 'Tên quyền không được để trống.'
    ];
    header('Location: ' . ADMIN_URL . '/roles/index.php');
    exit;
}

$permissionModel = new Permission();
if ($permissionId > 0) {
    $ok = $permissionModel->update($permissionId, $name, $description === '' ? null : $description);
    $message = $ok ? 'Cập nhật quyền thành công.' : 'Không thể cập nhật quyền.';
} else {
    $newId = $permissionModel->create($name, $description === '' ? null : $description);
    $ok = $newId !== null;
    $message = $ok ? 'Thêm quyền mới thành công.' : 'Không thể tạo quyền mới.';
}

$_SESSION['role_flash'] = [
    'type' => $ok ? 'success' : 'danger',
    'message' => $message
];

header('Location: ' . ADMIN_URL . '/roles/index.php');
exit;
?>
