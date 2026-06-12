<?php
require_once dirname(__DIR__, 3) . '/config/config.php';
require_once dirname(__DIR__, 3) . '/app/models/SavedJob.php';
require_once dirname(__DIR__, 3) . '/app/models/Job.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ' . BASE_URL . '/job/share/index.php');
	exit;
}

$redirectTarget = BASE_URL . '/job/share/index.php';
$returnUrl = isset($_POST['return']) ? trim((string)$_POST['return']) : '';
if ($returnUrl !== '') {
	if (strpos($returnUrl, BASE_URL) === 0) {
		$redirectTarget = $returnUrl;
	} elseif ($returnUrl[0] === '/') {
		$redirectTarget = $returnUrl;
	}
}

$sendRedirect = function (string $url) {
	header('Location: ' . $url);
	exit;
};

$userId = (int)($_SESSION['user_id'] ?? 0);
$roleId = (int)($_SESSION['role_id'] ?? 0);
if ($userId <= 0 || $roleId !== 3) {
	$_SESSION['job_share_flash'] = [
		'type' => 'warning',
		'message' => 'Vui lòng đăng nhập bằng tài khoản ứng viên để lưu việc làm.'
	];
	$sendRedirect(BASE_URL . '/account/login.php');
}

$jobId = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
if ($jobId <= 0) {
	$_SESSION['job_share_flash'] = [
		'type' => 'danger',
		'message' => 'Tin tuyển dụng không hợp lệ.'
	];
	$sendRedirect($redirectTarget);
}

$jobModel = new Job();
$job = $jobModel->getById($jobId);
if (!$job || !Job::isActive($job)) {
	$_SESSION['job_share_flash'] = [
		'type' => 'danger',
		'message' => 'Tin tuyển dụng không còn khả dụng.'
	];
	$sendRedirect($redirectTarget);
}

$action = isset($_POST['action']) ? strtolower(trim((string)$_POST['action'])) : 'save';
$savedJobModel = new SavedJob();
$result = false;
$messageType = 'success';
$messageText = '';

if ($action === 'remove') {
	$result = $savedJobModel->removeForUser($userId, $jobId);
	$messageType = $result ? 'info' : 'warning';
	$messageText = $result ? 'Đã bỏ lưu việc làm khỏi danh sách.' : 'Không thể bỏ lưu việc làm. Vui lòng thử lại sau.';
} else {
	$result = $savedJobModel->saveForUser($userId, $jobId);
	$messageType = $result ? 'success' : 'danger';
	$messageText = $result ? 'Đã lưu việc làm vào danh sách yêu thích.' : 'Không thể lưu việc làm. Vui lòng thử lại.';
}

$_SESSION['job_share_flash'] = [
	'type' => $messageType,
	'message' => $messageText
];

$sendRedirect($redirectTarget);
