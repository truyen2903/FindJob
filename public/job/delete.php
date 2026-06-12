<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/controllers/JobController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/job/index.php');
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$roleId = $_SESSION['role_id'] ?? null;
if (!$userId) {
    header('Location: ' . BASE_URL . '/account/login.php');
    exit;
}
if ((int)$roleId !== 2) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$jobId = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
if ($jobId <= 0) {
    $_SESSION['job_flash'] = [
        'type' => 'danger',
        'message' => 'Tin tuyển dụng không hợp lệ.'
    ];
    header('Location: ' . BASE_URL . '/job/index.php');
    exit;
}

$jobController = new JobController();
$deleted = $jobController->deleteJob((int)$userId, $jobId);

if ($deleted) {
    $_SESSION['job_flash'] = [
        'type' => 'success',
        'message' => 'Tin tuyển dụng đã được xoá.'
    ];
} else {
    $_SESSION['job_flash'] = [
        'type' => 'danger',
        'message' => 'Không thể xoá tin tuyển dụng. Vui lòng thử lại.'
    ];
}

header('Location: ' . BASE_URL . '/job/index.php');
exit;
