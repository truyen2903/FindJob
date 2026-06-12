<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Candidate.php';
require_once dirname(__DIR__, 2) . '/app/models/Application.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/job/applications.php'); exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$roleId = (int)($_SESSION['role_id'] ?? 0);
if ($userId <= 0 || $roleId !== 3) {
    header('Location: ' . BASE_URL . '/account/login.php'); exit;
}

$candidateModel = new Candidate();
$candidate = $candidateModel->getByUserId($userId);
$candidateId = $candidate ? (int)$candidate['id'] : 0;

$applicationId = (int)($_POST['application_id'] ?? 0);
// CSRF validation
$postedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (empty($postedToken) || empty($sessionToken) || !is_string($postedToken) || !is_string($sessionToken) || !hash_equals($sessionToken, $postedToken)) {
    $_SESSION['app_flash'] = ['type'=>'danger','message'=>'Yêu cầu không hợp lệ (mã CSRF).'];
    header('Location: ' . BASE_URL . '/job/applications.php'); exit;
}
if ($applicationId <= 0 || $candidateId <= 0) {
    $_SESSION['app_flash'] = ['type'=>'danger','message'=>'Dữ liệu không hợp lệ.'];
    header('Location: ' . BASE_URL . '/job/applications.php'); exit;
}

$applicationModel = new Application();
// Get application details to notify employer (best-effort)
$appDetails = $applicationModel->getForCandidateById($applicationId, $candidateId);

$ok = $applicationModel->withdrawByCandidate($applicationId, $candidateId);
if ($ok) {
    $_SESSION['app_flash'] = ['type'=>'success','message'=>'Bạn đã rút đơn thành công. Bạn có thể nộp lại đơn nếu muốn.'];

    // Send notification email to employer (non-blocking)
    if (!empty($appDetails) && !empty($appDetails['employer_email'])) {
        $to = $appDetails['employer_email'];
        $jobTitle = $appDetails['job_title'] ?? 'Tin tuyển dụng';
        $candidateName = $appDetails['candidate_name'] ?? ($_SESSION['user_name'] ?? 'Ứng viên');
        $subject = "[JobFind] Ứng viên rút đơn cho: " . $jobTitle;
        $messageLines = [
            "Xin chào,",
            "",
            "Thông báo: Ứng viên đã rút đơn ứng tuyển.",
            "",
            "Chi tiết:",
            "- Vị trí: " . $jobTitle,
            "- Ứng viên: " . $candidateName,
            "- Thời gian: " . date('d/m/Y H:i'),
            "",
            "Bạn có thể xem chi tiết tại: " . BASE_URL . "/employer/admin/applications.php",
            "",
            "Trân trọng,",
            "JobFind"
        ];
        $message = implode("\n", $messageLines);
        $headers = "From: no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'jobfind.local') . "\r\n" .
                   "MIME-Version: 1.0\r\n" .
                   "Content-Type: text/plain; charset=UTF-8\r\n";
        @mail($to, $subject, $message, $headers);
    }

} else {
    $_SESSION['app_flash'] = ['type'=>'danger','message'=>'Không thể rút đơn. Vui lòng thử lại.'];
}
// Invalidate the used CSRF token to prevent replay
unset($_SESSION['csrf_token']);

header('Location: ' . BASE_URL . '/job/applications.php'); exit;
