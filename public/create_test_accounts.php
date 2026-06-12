<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/models/User.php';

$userModel = new User();

// Tạo 3 tài khoản test đơn giản
$accounts = [
    ['email' => 'user@test.com', 'password' => '123456', 'name' => 'Test User', 'role' => 3],
    ['email' => 'employer@test.com', 'password' => '123456', 'name' => 'Test Employer', 'role' => 2],
    ['email' => 'admin@test.com', 'password' => '123456', 'name' => 'Test Admin', 'role' => 1]
];

echo "<h2>Tạo Tài Khoản Test</h2>";
echo "<p>Tất cả tài khoản có password: <strong>123456</strong></p>";
echo "<hr>";

foreach ($accounts as $acc) {
    // Check if exists
    $existing = $userModel->findByEmail($acc['email']);
    
    if ($existing) {
        echo "<p style='color: orange;'>⚠ Email <strong>{$acc['email']}</strong> đã tồn tại (ID: {$existing['id']})</p>";
    } else {
        $user_id = $userModel->create($acc['email'], $acc['password'], $acc['role'], $acc['name']);
        if ($user_id) {
            $role_name = $acc['role'] == 1 ? 'Admin' : ($acc['role'] == 2 ? 'Nhà tuyển dụng' : 'Ứng viên');
            echo "<p style='color: green;'>✓ Tạo thành công: <strong>{$acc['email']}</strong> - {$acc['name']} ({$role_name}) - ID: $user_id</p>";
        } else {
            echo "<p style='color: red;'>✗ Không thể tạo: {$acc['email']}</p>";
        }
    }
}

echo "<hr>";
echo "<h3>Danh sách tài khoản test:</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr style='background: #00b14f; color: white;'>
        <th>Email</th>
        <th>Password</th>
        <th>Vai trò</th>
        <th>Link đăng nhập</th>
      </tr>";

foreach ($accounts as $acc) {
    $role_name = $acc['role'] == 1 ? 'Admin' : ($acc['role'] == 2 ? 'Nhà tuyển dụng' : 'Ứng viên');
    echo "<tr>";
    echo "<td><strong>{$acc['email']}</strong></td>";
    echo "<td>{$acc['password']}</td>";
    echo "<td>{$role_name}</td>";
    echo "<td><a href='" . BASE_URL . "/account/login.php' target='_blank'>Đăng nhập</a></td>";
    echo "</tr>";
}

echo "</table>";

$conn->close();
?>

<style>
    body {
        font-family: Arial, sans-serif;
        padding: 20px;
        background: #f5f5f5;
    }
    table {
        background: white;
        width: 100%;
        margin-top: 20px;
    }
    th, td {
        text-align: left;
        padding: 12px;
    }
    tr:nth-child(even) {
        background: #f9f9f9;
    }
</style>
