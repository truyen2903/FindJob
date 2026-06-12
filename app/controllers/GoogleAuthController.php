<?php
// GoogleAuthController: xử lý callback Google OAuth2
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/google_oauth.php';

class GoogleAuthController {
    public function handleCallback($code) {
        $config = require __DIR__ . '/../../config/google_oauth.php';
        // Lấy access token từ Google
        $token = $this->getAccessToken($code, $config);
        if (!$token) return false;
        // Lấy thông tin user từ Google
        $userInfo = $this->getUserInfo($token['access_token']);
        if (!$userInfo || empty($userInfo['email'])) return false;
        // Tìm hoặc tạo user
        $userModel = new User();
        $user = $userModel->findByEmail($userInfo['email']);
        if (!$user) {
            // Tạo user mới, mặc định role ứng viên
            $user_id = $userModel->create($userInfo['email'], bin2hex(random_bytes(8)), 3, $userInfo['name'] ?? $userInfo['email']);
            $user = $userModel->getById($user_id);
        }
        // Đăng nhập
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['email'] = $user['email'];
        return $user;
    }
    private function getAccessToken($code, $config) {
        $post = [
            'code' => $code,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => google_oauth_redirect_uri($config),
            'grant_type' => 'authorization_code',
        ];
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);
        return json_decode($resp, true);
    }
    private function getUserInfo($access_token) {
        $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);
        return json_decode($resp, true);
    }
}
