<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Candidate.php';
require_once dirname(__DIR__, 2) . '/app/models/Application.php';
require_once dirname(__DIR__, 2) . '/app/models/Job.php';
require_once dirname(__DIR__, 2) . '/app/models/Employer.php';
require_once dirname(__DIR__, 2) . '/app/models/User.php';
require_once dirname(__DIR__, 2) . '/app/helpers/cv.php';

function jobfind_send_employer_application_alert(array $payload): void
{
    $to = trim((string)($payload['to'] ?? ''));
    if ($to === '') {
        return;
    }

    $jobTitle = trim((string)($payload['job_title'] ?? 'Tin tuyển dụng'));
    if ($jobTitle === '') {
        $jobTitle = 'Tin tuyển dụng';
    }

    $companyName = trim((string)($payload['company_name'] ?? 'Nhà tuyển dụng JobFind'));
    if ($companyName === '') {
        $companyName = 'Nhà tuyển dụng JobFind';
    }

    $candidateName = trim((string)($payload['candidate_name'] ?? ''));
    if ($candidateName === '') {
        $candidateName = 'Ứng viên JobFind';
    }

    $candidateEmail = trim((string)($payload['candidate_email'] ?? ''));
    $submittedAt = trim((string)($payload['submitted_at'] ?? date('d/m/Y H:i')));
    $applicationUrl = trim((string)($payload['application_url'] ?? ''));
    $coverLetter = trim((string)($payload['cover_letter'] ?? ''));
    $hasCv = !empty($payload['has_cv']);

    $subject = '[JobFind] Ứng viên mới cho vị trí ' . $jobTitle;
    $lines = [
        'Chào ' . $companyName . ',',
        '',
        $candidateName . ($candidateEmail !== '' ? ' (' . $candidateEmail . ')' : '') . ' vừa ứng tuyển vị trí: ' . $jobTitle . '.',
        'Thời gian nộp: ' . $submittedAt . '.',
    ];

    if ($hasCv) {
        $lines[] = 'Ứng viên đã đính kèm CV trên hệ thống.';
    }

    if ($coverLetter !== '') {
        $lines[] = '';
        $lines[] = 'Trích thư giới thiệu:';
        $normalized = str_replace(["\r\n", "\r"], "\n", $coverLetter);
        $snippet = mb_substr($normalized, 0, 280);
        if (mb_strlen($normalized) > 280) {
            $snippet .= '…';
        }
        $lines[] = $snippet;
    }

    if ($applicationUrl !== '') {
        $lines[] = '';
        $lines[] = 'Xem hồ sơ ngay: ' . $applicationUrl;
    }

    $lines[] = '';
    $lines[] = 'Email tự động từ JobFind.';

    $message = implode("\n", $lines);
    $fromDomain = $_SERVER['HTTP_HOST'] ?? 'jobfind.local';
    $headers = 'From: no-reply@' . $fromDomain . "\r\n" .
        "MIME-Version: 1.0\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n";

    @mail($to, $subject, $message, $headers);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/job/share/index.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$roleId = (int)($_SESSION['role_id'] ?? 0);
if ($userId <= 0) {
    $_SESSION['job_application_flash'] = [
        'type' => 'warning',
        'message' => 'Vui lòng đăng nhập trước khi ứng tuyển.'
    ];
    header('Location: ' . BASE_URL . '/account/login.php');
    exit;
}

if ($roleId !== 3) {
    $_SESSION['job_application_flash'] = [
        'type' => 'danger',
        'message' => 'Chỉ ứng viên mới có thể ứng tuyển công việc.'
    ];
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$jobId = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
$redirectUrl = BASE_URL . '/job/share/view.php?id=' . $jobId;
if ($jobId <= 0) {
    $_SESSION['job_application_flash'] = [
        'type' => 'danger',
        'message' => 'Tin tuyển dụng không hợp lệ.'
    ];
    header('Location: ' . BASE_URL . '/job/share/index.php');
    exit;
}

$jobModel = new Job();
$employerModel = new Employer();
$userModel = new User();
$job = $jobModel->getById($jobId);
if (!$job || !Job::isActive($job)) {
    $_SESSION['job_application_flash'] = [
        'type' => 'danger',
        'message' => 'Tin tuyển dụng không còn khả dụng để ứng tuyển.'
    ];
    header('Location: ' . $redirectUrl);
    exit;
}

$jobTitle = trim((string)($job['title'] ?? 'Tin tuyển dụng'));
if ($jobTitle === '') {
    $jobTitle = 'Tin tuyển dụng';
}

$employerContact = null;
$employerCompanyLabel = 'Nhà tuyển dụng JobFind';
if (!empty($job['employer_id'])) {
    $employer = $employerModel->getById((int)$job['employer_id']);
    if ($employer) {
        $companyName = trim((string)($employer['company_name'] ?? ''));
        if ($companyName !== '') {
            $employerCompanyLabel = $companyName;
        }
        $employerUserId = (int)($employer['user_id'] ?? 0);
        if ($employerUserId > 0) {
            $employerUser = $userModel->getById($employerUserId);
            if ($employerUser && !empty($employerUser['email'])) {
                $employerContact = [
                    'email' => trim((string)$employerUser['email']),
                    'name' => trim((string)($employerUser['name'] ?? '')),
                    'company' => $employerCompanyLabel
                ];
            }
        }
    }
}

$candidateUser = $userModel->getById($userId);
$candidateName = trim((string)($candidateUser['name'] ?? ''));
$candidateEmail = trim((string)($candidateUser['email'] ?? ''));

$candidateModel = new Candidate();
$candidate = $candidateModel->getByUserId($userId);
if (!$candidate) {
    $created = $candidateModel->createOrUpdate($userId);
    if (!$created) {
        $_SESSION['job_application_flash'] = [
            'type' => 'danger',
            'message' => 'Không thể khởi tạo hồ sơ ứng viên. Vui lòng thử lại.'
        ];
        header('Location: ' . $redirectUrl);
        exit;
    }
    $candidate = $candidateModel->getByUserId($userId);
}

$candidateId = (int)($candidate['id'] ?? 0);
if ($candidateId <= 0) {
    $_SESSION['job_application_flash'] = [
        'type' => 'danger',
        'message' => 'Không tìm thấy hồ sơ ứng viên phù hợp.'
    ];
    header('Location: ' . $redirectUrl);
    exit;
}

$applicationModel = new Application();

$formData = [
    'cover_letter' => trim((string)($_POST['cover_letter'] ?? '')),
    'cv_option' => isset($_POST['cv_option']) ? trim((string)$_POST['cv_option']) : ''
];

if ($formData['cover_letter'] !== '') {
    $formData['cover_letter'] = mb_substr($formData['cover_letter'], 0, 4000);
}

$candidateCvPath = trim((string)($candidate['cv_path'] ?? ''));
$resumeSnapshot = null;
$updateCvWarning = null;

$cvOption = $formData['cv_option'] !== '' ? $formData['cv_option'] : ($candidateCvPath !== '' ? 'existing' : 'upload');

if ($cvOption === 'upload') {
    if (!isset($_FILES['cv_file']) || !is_array($_FILES['cv_file']) || trim((string)($_FILES['cv_file']['name'] ?? '')) === '') {
        $_SESSION['job_application_flash'] = [
            'type' => 'danger',
            'message' => 'Vui lòng chọn tệp CV trước khi gửi đơn.'
        ];
        $_SESSION['job_application_form'] = $formData;
        header('Location: ' . $redirectUrl);
        exit;
    }

    $uploadError = null;
    $uploadedPath = handle_cv_upload($_FILES['cv_file'], $uploadError);
    if ($uploadedPath === false) {
        $_SESSION['job_application_flash'] = [
            'type' => 'danger',
            'message' => $uploadError ?: 'Không thể tải lên CV. Vui lòng thử lại.'
        ];
        $_SESSION['job_application_form'] = $formData;
        header('Location: ' . $redirectUrl);
        exit;
    }

    if (!$candidateModel->updateCv($userId, $uploadedPath)) {
        $updateCvWarning = 'Ứng tuyển thành công nhưng không thể cập nhật CV của bạn. Hệ thống vẫn sử dụng CV vừa tải lên cho đơn này.';
    }
    $resumeSnapshot = $uploadedPath;
    $candidateCvPath = $uploadedPath;
} elseif ($cvOption === 'existing') {
    if ($candidateCvPath === '') {
        $_SESSION['job_application_flash'] = [
            'type' => 'danger',
            'message' => 'Bạn chưa có CV lưu trên hệ thống. Vui lòng tải CV trước khi gửi đơn.'
        ];
        $_SESSION['job_application_form'] = $formData;
        header('Location: ' . $redirectUrl);
        exit;
    }
    $resumeSnapshot = $candidateCvPath;
} elseif ($cvOption === 'skip') {
    $resumeSnapshot = null;
} else {
    $_SESSION['job_application_flash'] = [
        'type' => 'danger',
        'message' => 'Lựa chọn CV không hợp lệ. Vui lòng thử lại.'
    ];
    $_SESSION['job_application_form'] = $formData;
    header('Location: ' . $redirectUrl);
    exit;
}

// If an application exists for this candidate/job, check status
$existingApp = $applicationModel->getForCandidate($jobId, $candidateId);
if ($existingApp) {
    $existingStatus = trim((string)($existingApp['status'] ?? ''));
    if ($existingStatus === '') {
        // treat legacy empty as withdrawn
        $existingStatus = 'withdrawn';
    }
    if (function_exists('error_log')) {
        error_log('JobFind: apply.php existing application detected -> ' . json_encode([
            'application_id' => (int)$existingApp['id'],
            'job_id' => $jobId,
            'candidate_id' => $candidateId,
            'status' => $existingStatus
        ], JSON_UNESCAPED_UNICODE));
    }
    if ($existingStatus === 'withdrawn') {
        // reactivate existing application
        $reactivated = $applicationModel->reactivateApplication((int)$existingApp['id'], $candidateId, $formData['cover_letter'] ?: null, $resumeSnapshot ?: null);
        if (function_exists('error_log')) {
            error_log('JobFind: apply.php reactivate result -> ' . json_encode([
                'application_id' => (int)$existingApp['id'],
                'reactivated' => $reactivated ? 1 : 0
            ]));
        }
        if ($reactivated) {
            if ($employerContact) {
                jobfind_send_employer_application_alert([
                    'to' => $employerContact['email'],
                    'job_title' => $jobTitle,
                    'company_name' => $employerContact['company'] ?? $employerCompanyLabel,
                    'candidate_name' => $candidateName,
                    'candidate_email' => $candidateEmail,
                    'application_url' => BASE_URL . '/employer/admin/application_view.php?id=' . (int)$existingApp['id'],
                    'cover_letter' => $formData['cover_letter'] ?? '',
                    'has_cv' => $resumeSnapshot !== null,
                    'submitted_at' => date('d/m/Y H:i')
                ]);
            }
            $successMessage = 'Bạn đã nộp lại đơn ứng tuyển thành công! Nhà tuyển dụng sẽ xem xét hồ sơ của bạn.';
            if ($updateCvWarning !== null) $successMessage .= ' ' . $updateCvWarning;
            $_SESSION['job_application_flash'] = ['type' => $updateCvWarning ? 'warning' : 'success', 'message' => $successMessage];
            unset($_SESSION['job_application_form']);
        } else {
            $_SESSION['job_application_flash'] = ['type' => 'danger', 'message' => 'Không thể nộp lại đơn. Vui lòng thử lại sau.'];
            $_SESSION['job_application_form'] = $formData;
        }
    } else {
        // already applied and active
        $_SESSION['job_application_flash'] = [
            'type' => 'info',
            'message' => 'Bạn đã ứng tuyển vị trí này trước đó. Nhà tuyển dụng sẽ phản hồi sớm nhất.'
        ];
    }
} else {
    $created = $applicationModel->createApplication($jobId, $candidateId, $formData['cover_letter'] ?: null, $resumeSnapshot ?: null);
    if ($created) {
        if ($employerContact) {
            jobfind_send_employer_application_alert([
                'to' => $employerContact['email'],
                'job_title' => $jobTitle,
                'company_name' => $employerContact['company'] ?? $employerCompanyLabel,
                'candidate_name' => $candidateName,
                'candidate_email' => $candidateEmail,
                'application_url' => BASE_URL . '/employer/admin/application_view.php?id=' . (int)$created,
                'cover_letter' => $formData['cover_letter'] ?? '',
                'has_cv' => $resumeSnapshot !== null,
                'submitted_at' => date('d/m/Y H:i')
            ]);
        }
        $successMessage = 'Ứng tuyển thành công! Nhà tuyển dụng sẽ xem xét hồ sơ của bạn.';
        if ($updateCvWarning !== null) {
            $successMessage .= ' ' . $updateCvWarning;
        }
        $_SESSION['job_application_flash'] = [
            'type' => $updateCvWarning ? 'warning' : 'success',
            'message' => $successMessage
        ];
        unset($_SESSION['job_application_form']);
    } else {
        $_SESSION['job_application_flash'] = [
            'type' => 'danger',
            'message' => 'Không thể gửi đơn ứng tuyển. Vui lòng thử lại sau.'
        ];
        $_SESSION['job_application_form'] = $formData;
    }
}

header('Location: ' . $redirectUrl);
exit;
