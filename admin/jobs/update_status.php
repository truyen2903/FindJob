<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Job.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ADMIN_URL . '/jobs/index.php');
    exit;
}

if (!isset($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$jobId = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
$status = trim($_POST['status'] ?? '');

$allowedStatuses = ['draft', 'published', 'closed'];
if ($jobId <= 0 || !in_array($status, $allowedStatuses, true)) {
    $_SESSION['admin_job_flash'] = [
        'type' => 'danger',
        'message' => 'Yêu cầu không hợp lệ.'
    ];
    header('Location: ' . ADMIN_URL . '/jobs/index.php');
    exit;
}

$jobModel = new Job();
$updated = $jobModel->updateStatus($jobId, $status);

if ($updated) {
    $messages = [
        'draft' => 'Tin tuyển dụng đã được chuyển về nháp.',
        'published' => 'Tin tuyển dụng đã được duyệt và hiển thị.',
        'closed' => 'Tin tuyển dụng đã được đóng.'
    ];
    $_SESSION['admin_job_flash'] = [
        'type' => 'success',
        'message' => $messages[$status] ?? 'Cập nhật trạng thái thành công.'
    ];
} else {
    $_SESSION['admin_job_flash'] = [
        'type' => 'danger',
        'message' => 'Không thể cập nhật trạng thái tin tuyển dụng.'
    ];
}

header('Location: ' . ADMIN_URL . '/jobs/index.php');
exit;
