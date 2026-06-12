<?php
// GoogleAuthController: xử lý callback Google OAuth2
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Candidate.php';
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
        $candidateModel = new Candidate();
        $user = $userModel->findByEmail($userInfo['email']);
        if (!$user) {
            $requestedRole = (int)($_SESSION['google_oauth_role'] ?? 3);
            $roleId = in_array($requestedRole, [2, 3], true) ? $requestedRole : 3;
            // Tạo user mới theo vai trò đã chọn trước khi chuyển sang Google.
            $user_id = $userModel->create($userInfo['email'], bin2hex(random_bytes(8)), $roleId, $userInfo['name'] ?? $userInfo['email']);
            $user = $userModel->getById($user_id);
        }
        unset($_SESSION['google_oauth_role']);
        if ($user && (int)$user['role_id'] === 3 && !$candidateModel->getByUserId((int)$user['id'])) {
            $candidateModel->createOrUpdate((int)$user['id']);
        }
        if ($user && !empty($userInfo['picture']) && empty($user['avatar_path'])) {
            $userModel->setAvatar((int)$user['id'], $userInfo['picture']);
            $user['avatar_path'] = $userInfo['picture'];
        }
        // Đăng nhập
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'] ?? null;
        $_SESSION['avatar_url'] = !empty($user['avatar_path']) ? $this->resolveAvatarUrl($user['avatar_path']) : null;
        $_SESSION['avatar_checked'] = true;
        return $user;
    }

    private function resolveAvatarUrl($avatarPath) {
        if (filter_var($avatarPath, FILTER_VALIDATE_URL)) {
            return $avatarPath;
        }
        return BASE_URL . '/' . ltrim($avatarPath, '/');
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
