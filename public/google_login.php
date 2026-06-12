<?php
// Khởi tạo luồng đăng nhập Google OAuth2.
//   - Nếu config/google_oauth.php đã có client_id/secret thật => redirect
//     sang Google.
//   - Nếu vẫn là placeholder => quay lại trang đăng nhập với flash message.
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/app/helpers/google_oauth.php';

$config = require dirname(__DIR__) . '/config/google_oauth.php';

$placeholder = !$config
    || !isset($config['client_id'])
    || $config['client_id'] === ''
    || strpos($config['client_id'], 'YOUR_') === 0;

if ($placeholder) {
    $_SESSION['flash_error'] = 'Đăng nhập Google chưa được cấu hình. '
        . 'Vui lòng cập nhật config/google_oauth.php với client_id và '
        . 'client_secret thật, sau đó thử lại.';
    header('Location: ' . BASE_URL . '/account/login.php');
    exit;
}

$requestedRole = (int)($_GET['role'] ?? 3);
$_SESSION['google_oauth_role'] = in_array($requestedRole, [2, 3], true) ? $requestedRole : 3;

$redirectUri = google_oauth_redirect_uri($config);

$params = [
    'response_type' => 'code',
    'client_id'     => $config['client_id'],
    'redirect_uri'  => $redirectUri,
    'scope'         => $config['scope'] ?? 'email profile',
    'access_type'   => 'offline',
    'prompt'        => 'select_account',
];
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit;
