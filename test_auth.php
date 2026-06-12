<?php
// Test tạo user mẫu
require_once 'config/config.php';
require_once 'app/models/User.php';

$userModel = new User();

echo "<h2>Test Tạo Tài Khoản</h2>";

// Tạo user test
$email = "test" . time() . "@jobfind.vn";
$password = "123456";
$name = "Nguyễn Văn Test";
$role_id = 3; // Ứng viên

echo "<p>Đang tạo user mới...</p>";
echo "<ul>";
echo "<li>Email: $email</li>";
echo "<li>Password: $password</li>";
echo "<li>Name: $name</li>";
echo "<li>Role: Ứng viên (3)</li>";
echo "</ul>";

$user_id = $userModel->create($email, $password, $role_id, $name);

if ($user_id) {
    echo "<p style='color: green;'>✓ Tạo user thành công! ID: $user_id</p>";
    
    // Test đăng nhập
    echo "<h3>Test Đăng Nhập</h3>";
    $verified = $userModel->verifyPassword($email, $password);
    
    if ($verified) {
        echo "<p style='color: green;'>✓ Đăng nhập thành công!</p>";
        echo "<pre>";
        print_r($verified);
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>✗ Đăng nhập thất bại!</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Không thể tạo user!</p>";
}

echo "<hr>";
echo "<h3>Users hiện tại trong database:</h3>";
$result = $conn->query("SELECT id, email, name, role_id, created_at FROM users ORDER BY id DESC LIMIT 5");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Email</th><th>Name</th><th>Role</th><th>Created</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['email']}</td>";
    echo "<td>{$row['name']}</td>";
    echo "<td>{$row['role_id']}</td>";
    echo "<td>{$row['created_at']}</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>
