<?php
require_once dirname(__DIR__, 3) . '/config/config.php';
require_once dirname(__DIR__, 3) . '/app/controllers/JobController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/employer/admin/jobs.php');
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$roleId = $_SESSION['role_id'] ?? null;
if (!$userId || (int)$roleId !== 2) {
    header('Location: ' . BASE_URL . '/account/login.php');
    exit;
}

$jobId = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
if ($jobId <= 0) {
    $_SESSION['employer_job_flash'] = [
        'type' => 'danger',
        'message' => 'Tin tuyển dụng không hợp lệ.'
    ];
    header('Location: ' . BASE_URL . '/employer/admin/jobs.php');
    exit;
}

$jobController = new JobController();
$deleted = $jobController->deleteJob((int)$userId, $jobId);

if ($deleted) {
    $_SESSION['employer_job_flash'] = [
        'type' => 'success',
        'message' => 'Tin tuyển dụng đã được xoá.'
    ];
} else {
    $_SESSION['employer_job_flash'] = [
        'type' => 'danger',
        'message' => 'Không thể xoá tin tuyển dụng. Vui lòng thử lại.'
    ];
}

header('Location: ' . BASE_URL . '/employer/admin/jobs.php');
exit;
