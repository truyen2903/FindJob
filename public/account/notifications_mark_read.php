<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Notification.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/account/login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$notificationModel = new Notification();

$redirectParam = isset($_GET['redirect']) ? rawurldecode((string)$_GET['redirect']) : '';
$redirectTarget = BASE_URL . '/dashboard.php';
if ($redirectParam !== '') {
    $trimmed = trim($redirectParam);
    if (strpos($trimmed, BASE_URL) === 0) {
        $redirectTarget = $trimmed;
    } elseif (isset($trimmed[0]) && $trimmed[0] === '/') {
        $redirectTarget = $trimmed;
    }
}

$notificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($notificationId > 0) {
    $notificationModel->markAsRead($userId, $notificationId);
} else {
    $notificationModel->markAllRead($userId);
}

header('Location: ' . $redirectTarget);
exit;
