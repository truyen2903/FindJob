<?php
require_once 'config/config.php';

echo "✓ Kết nối database thành công!\n";
echo "Database: " . $dbname . "\n\n";

// Check users table
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows > 0) {
    echo "✓ Bảng 'users' tồn tại\n\n";
    
    // Count users
    $count = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc();
    echo "Số lượng users hiện tại: " . $count['total'] . "\n";
    
    // Show structure
    echo "\nCấu trúc bảng users:\n";
    $result = $conn->query("DESCRIBE users");
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "✗ Bảng 'users' không tồn tại!\n";
}

$conn->close();
?>
