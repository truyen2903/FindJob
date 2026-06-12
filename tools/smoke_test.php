<?php
// tools/smoke_test.php - quick CLI smoke test for DB connection and user creation
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/models/User.php';

$userModel = new User();
$res = $userModel->conn->query("SELECT 1");
if ($res) echo "DB connection OK\n";
else { echo "DB connection FAILED\n"; exit(1); }

// create temp user
$email = 'smoketest'.time().'@local';
$id = $userModel->create($email, 'testpass123', 3, 'Smoke Test');
if ($id) echo "Created test user id=$id email=$email\n";
else echo "Failed to create test user\n";

?>
