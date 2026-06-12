<?php
require_once '../../config/config.php';
require_once '../../app/controllers/AuthMiddleware.php';

$_SESSION['role_id'] = 1; // 1=admin, 2=nhà tuyển dụng, 3=ứng viên

checkPermission('view_admin_dashboard');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Trang Admin</title>
</head>
<body>
    <h2>Chào mừng đến trang quản trị!</h2>
    <p>Bạn có quyền xem trang này.</p>
</body>
</html>
