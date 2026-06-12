<?php
// config/config.php
session_start();

$host   = getenv('DB_HOST') ?: 'localhost';
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 'jobfinder';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Thiết lập bộ ký tự UTF-8
$conn->set_charset("utf8");

// Base URL configuration:
// - Docker (DocumentRoot=public/) => ''
// - XAMPP/subfolder install => auto-detected, e.g. /FindJob/public
$baseUrl = getenv('BASE_URL');
if ($baseUrl === false) {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $publicPos = strpos($scriptName, '/public/');

    if ($publicPos !== false) {
        $baseUrl = substr($scriptName, 0, $publicPos + strlen('/public'));
    } else {
        $baseUrl = getenv('DB_HOST') ? '' : '';
    }
}
$baseUrl = rtrim($baseUrl, '/');
define('BASE_URL', $baseUrl);
define('ASSETS_URL', BASE_URL . '/assets');
// ADMIN_URL: trang /admin/ là sibling của /public/, không nằm trong DocumentRoot.
// Docker (Alias /admin) => "/admin", XAMPP => "/FindJob/admin".
$adminUrl = preg_match('#/public$#', BASE_URL) ? preg_replace('#/public$#', '/admin', BASE_URL) : '/admin';
define('ADMIN_URL', $adminUrl);
 
// Avatar / upload configuration
define('AVATAR_UPLOAD_DIR', 'uploads/avatars'); // relative to public/
define('AVATAR_UPLOAD_PATH', __DIR__ . '/../public/' . AVATAR_UPLOAD_DIR);
define('AVATAR_MAX_SIZE', 2 * 1024 * 1024); // 2 MB
// allowed MIME types for avatars
define('AVATAR_ALLOWED_MIME', serialize(['image/png','image/jpeg','image/gif','image/webp','image/pjpeg','image/jpg']));
define('AVATAR_MAX_WIDTH', 800);
define('AVATAR_MAX_HEIGHT', 800);
define('AVATAR_THUMB_SIZE', 96);

// Company logo upload configuration
define('COMPANY_LOGO_UPLOAD_DIR', 'uploads/company-logos');
define('COMPANY_LOGO_UPLOAD_PATH', __DIR__ . '/../public/' . COMPANY_LOGO_UPLOAD_DIR);
define('COMPANY_LOGO_MAX_SIZE', 3 * 1024 * 1024); // 3 MB
define('COMPANY_LOGO_ALLOWED_MIME', serialize(['image/png','image/jpeg','image/gif','image/webp','image/pjpeg','image/jpg']));
define('COMPANY_LOGO_MAX_WIDTH', 600);
define('COMPANY_LOGO_MAX_HEIGHT', 600);
