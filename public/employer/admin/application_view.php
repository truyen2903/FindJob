<?php
require_once dirname(__DIR__, 3) . '/config/config.php';
require_once dirname(__DIR__, 3) . '/app/models/Employer.php';
require_once dirname(__DIR__, 3) . '/app/models/Application.php';
require_once dirname(__DIR__, 3) . '/app/models/Notification.php';

if (empty($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 2) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$employerModel = new Employer();
$applicationModel = new Application();

$employer = $employerModel->getByUserId($userId);
if (!$employer) {
    header('Location: ' . BASE_URL . '/employer/edit.php');
    exit;
}

// Centralized status labels (Vietnamese)
$statusLabels = $applicationModel->getStatusLabels();

$statusIcons = [
  'applied' => 'fa-solid fa-briefcase',
  'viewed' => 'fa-solid fa-eye text-info',
  'shortlisted' => 'fa-solid fa-star text-warning',
  'rejected' => 'fa-solid fa-circle-xmark text-danger',
  'hired' => 'fa-solid fa-check-circle text-success',
];

$applicationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($applicationId <= 0) {
    header('Location: ' . BASE_URL . '/employer/admin/applications.php');
    exit;
}

$employerId = (int)$employer['id'];
$_SESSION['employer_company_name'] = $employer['company_name'];
$_SESSION['employer_profile_url'] = BASE_URL . '/employer/show.php?id=' . $employerId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $currentApplication = $applicationModel->getForEmployer($applicationId, (int)$employer['id']);
  if (!$currentApplication) {
    $_SESSION['application_flash'] = [
      'type' => 'danger',
      'message' => 'Không tìm thấy hồ sơ ứng viên để cập nhật.'
    ];
    header('Location: ' . BASE_URL . '/employer/admin/applications.php');
    exit;
  }

    $newStatus = isset($_POST['status']) ? trim((string)$_POST['status']) : '';
  $note = isset($_POST['note']) ? trim((string)$_POST['note']) : '';
  $_SESSION['application_form'] = [
    'status' => $newStatus,
    'note' => $note
  ];

  if ($newStatus === '') {
    $_SESSION['application_flash'] = [
      'type' => 'danger',
      'message' => 'Vui lòng chọn trạng thái trước khi cập nhật.'
    ];
    header('Location: ' . BASE_URL . '/employer/admin/application_view.php?id=' . $applicationId);
    exit;
  }

  if ($newStatus === 'rejected' && $note === '') {
    $_SESSION['application_flash'] = [
      'type' => 'danger',
      'message' => 'Bạn cần ghi chú lý do từ chối để phản hồi ứng viên.'
    ];
    header('Location: ' . BASE_URL . '/employer/admin/application_view.php?id=' . $applicationId);
    exit;
  }

  $updated = $applicationModel->updateStatus($applicationId, $employerId, $newStatus, $note === '' ? null : $note);
  if ($updated) {
    unset($_SESSION['application_form']);
    $_SESSION['application_flash'] = [
      'type' => 'success',
      'message' => 'Cập nhật trạng thái hồ sơ thành công.'
    ];

    $statusChanged = ($currentApplication['status'] ?? '') !== $newStatus;
    $noteChanged = trim((string)($currentApplication['decision_note'] ?? '')) !== $note;
    $candidateUserId = (int)($currentApplication['user_id'] ?? 0);

    if (($statusChanged || $noteChanged) && $candidateUserId > 0) {
        $notificationModel = new Notification();
        $jobTitle = $currentApplication['job_title'] ?? 'Tin tuyển dụng';
        $statusLabel = $statusLabels[$newStatus] ?? ucfirst($newStatus);
        $lines = [
            'Tin tuyển dụng: ' . $jobTitle,
            'Trạng thái mới: ' . $statusLabel
        ];
        if ($note !== '') {
            $lines[] = "Phản hồi từ nhà tuyển dụng:\n" . $note;
        }
        $message = implode("\n\n", $lines);
        $notificationModel->create(
            $candidateUserId,
            'Cập nhật hồ sơ ứng tuyển',
            $message,
            [
                'icon' => $statusIcons[$newStatus] ?? 'fa-solid fa-bell',
                'action_url' => BASE_URL . '/job/share/view.php?id=' . (int)$currentApplication['job_id']
            ]
        );
    }

        if ($statusChanged && $newStatus === 'shortlisted' && !empty($currentApplication['candidate_email'])) {
          $candidateName = trim((string)($currentApplication['candidate_name'] ?? ''));
          $jobTitle = $currentApplication['job_title'] ?? 'Tin tuyển dụng';
          $companyName = trim((string)($employer['company_name'] ?? 'Nhà tuyển dụng JobFind'));
          $subject = '[JobFind] Thư mời phỏng vấn - ' . $jobTitle;
          $messageLines = [
            'Chào ' . ($candidateName !== '' ? $candidateName : 'bạn') . ',',
            '',
            $companyName . ' đã chuyển hồ sơ của bạn sang bước phỏng vấn cho vị trí: ' . $jobTitle . '.',
            'Vui lòng đăng nhập JobFind để xem chi tiết và xác nhận lịch: ' . BASE_URL . '/job/applications.php'
          ];
          if ($note !== '') {
            $cleanNote = str_replace(["\r\n", "\r"], "\n", $note);
            $messageLines[] = '';
            $messageLines[] = "Ghi chú từ nhà tuyển dụng:\n" . $cleanNote;
          }
          $messageLines[] = '';
          $messageLines[] = 'Chúc bạn phỏng vấn thành công!';
          $messageLines[] = 'JobFind';

          $message = implode("\n", $messageLines);
          $fromDomain = $_SERVER['HTTP_HOST'] ?? 'jobfind.local';
          $headers = 'From: no-reply@' . $fromDomain . "\r\n" .
                 "MIME-Version: 1.0\r\n" .
                 "Content-Type: text/plain; charset=UTF-8\r\n";
          @mail($currentApplication['candidate_email'], $subject, $message, $headers);
        }
  } else {
    $_SESSION['application_flash'] = [
      'type' => 'danger',
      'message' => 'Không thể cập nhật trạng thái. Vui lòng thử lại.'
    ];
  }
    header('Location: ' . BASE_URL . '/employer/admin/application_view.php?id=' . $applicationId);
    exit;
}

$application = $applicationModel->getForEmployer($applicationId, $employerId);
if (!$application) {
    header('Location: ' . BASE_URL . '/employer/admin/applications.php');
    exit;
}

$applicationModel->markViewed($applicationId, $employerId);
$application['status'] = $application['status'] === 'applied' ? 'viewed' : $application['status'];

$skillTags = [];
if (!empty($application['skills'])) {
    $decodedSkills = json_decode($application['skills'], true);
    if (is_array($decodedSkills)) {
        $skillTags = array_slice(array_filter(array_map('trim', $decodedSkills)), 0, 12);
    }
}

$pageTitle = 'Chi tiết hồ sơ ứng viên | JobFind';
$employerNavActive = 'applications';
$employerCompanyName = $employer['company_name'];
$employerProfileUrl = BASE_URL . '/employer/show.php?id=' . $employerId;
require_once __DIR__ . '/includes/header.php';

$flash = $_SESSION['application_flash'] ?? null;
unset($_SESSION['application_flash']);
$formData = $_SESSION['application_form'] ?? null;
unset($_SESSION['application_form']);
$currentFormStatus = $formData['status'] ?? ($application['status'] ?? 'applied');
$currentFormNote = $formData['note'] ?? ($application['decision_note'] ?? '');
?>

<a class="btn btn-link text-decoration-none ps-0 mb-3" href="<?= BASE_URL ?>/employer/admin/applications.php">
  <i class="fa-solid fa-arrow-left-long me-2"></i>Quay về danh sách
</a>

<?php if ($flash): ?>
  <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-xl-8">
    <div class="ea-card mb-4">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
          <span class="badge-status <?= htmlspecialchars($application['status']) ?>">
            <?= $statusLabels[$application['status']] ?? ucfirst($application['status']) ?>
          </span>
          <h2 class="h4 mt-3 mb-1"><?= htmlspecialchars($application['candidate_name'] ?? 'Ứng viên') ?></h2>
          <div class="text-muted">
            <i class="fa-regular fa-envelope me-2"></i><?= htmlspecialchars($application['candidate_email'] ?? '') ?>
            <?php if (!empty($application['candidate_phone'])): ?>
              <span class="ms-3"><i class="fa-solid fa-phone me-2"></i><?= htmlspecialchars($application['candidate_phone']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="<?= BASE_URL ?>/candidate/profile.php?candidate=<?= (int)$application['candidate_id'] ?>">
          Xem hồ sơ đầy đủ
        </a>
      </div>

      <?php if (!empty($application['headline'])): ?>
        <p class="mb-3 text-secondary"><?= htmlspecialchars($application['headline']) ?></p>
      <?php endif; ?>

      <div class="mb-3">
        <h3 class="h6 text-uppercase text-muted fw-semibold">Thư giới thiệu</h3>
        <div class="bg-light rounded-3 p-3">
          <?= nl2br(htmlspecialchars($application['cover_letter'] ?: 'Ứng viên chưa đính kèm thư giới thiệu.')) ?>
        </div>
      </div>

      <div>
        <h3 class="h6 text-uppercase text-muted fw-semibold">Tóm tắt kinh nghiệm</h3>
        <p class="mb-0"><?= nl2br(htmlspecialchars($application['summary'] ?: 'Ứng viên chưa cập nhật tóm tắt kinh nghiệm.')) ?></p>
      </div>
    </div>

    <div class="ea-card">
      <h3 class="h5 mb-3">Thông tin tin tuyển dụng</h3>
      <div class="d-flex flex-wrap gap-3 text-muted mb-3">
        <span><i class="fa-solid fa-briefcase me-2"></i><?= htmlspecialchars($application['job_title'] ?? 'Tin tuyển dụng') ?></span>
        <?php if (!empty($application['job_location'])): ?>
          <span><i class="fa-solid fa-location-dot me-2"></i><?= htmlspecialchars($application['job_location']) ?></span>
        <?php endif; ?>
        <?php if (!empty($application['employment_type'])): ?>
          <span><i class="fa-regular fa-clock me-2"></i><?= htmlspecialchars($application['employment_type']) ?></span>
        <?php endif; ?>
        <?php if (!empty($application['salary'])): ?>
          <span><i class="fa-solid fa-sack-dollar me-2"></i><?= htmlspecialchars($application['salary']) ?></span>
        <?php endif; ?>
      </div>
      <div class="bg-light rounded-3 p-3 small text-secondary">
        <?= nl2br(htmlspecialchars($application['job_description'] ?? 'Tin tuyển dụng không có mô tả.')) ?>
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="ea-card mb-4">
      <h3 class="h6 text-uppercase text-muted fw-semibold mb-3">Hành động</h3>
      <form method="post" class="d-grid gap-3">
        <div>
          <label for="status" class="form-label fw-semibold">Trạng thái tuyển dụng</label>
          <select id="status" name="status" class="form-select">
            <?php foreach ($statusLabels as $value => $label): ?>
              <option value="<?= $value ?>" <?= $currentFormStatus === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="note" class="form-label fw-semibold">Ghi chú gửi ứng viên</label>
          <textarea id="note" name="note" class="form-control" rows="4" placeholder="Chia sẻ lý do từ chối hoặc hướng dẫn bước tiếp theo..."><?= htmlspecialchars($currentFormNote) ?></textarea>
          <div class="form-text">Bắt buộc khi từ chối. Ứng viên sẽ nhìn thấy nội dung này.</div>
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-floppy-disk me-2"></i>Cập nhật trạng thái
        </button>
      </form>
      <?php if (!empty($application['decision_note'])): ?>
        <div class="alert alert-secondary mt-3 mb-0" role="alert">
          <div class="fw-semibold mb-1">Ghi chú đã gửi cho ứng viên</div>
          <div class="small mb-0"><?= nl2br(htmlspecialchars($application['decision_note'])) ?></div>
        </div>
      <?php endif; ?>
    </div>

    <div class="ea-card">
      <h3 class="h6 text-uppercase text-muted fw-semibold mb-3">Hồ sơ &amp; tài liệu</h3>
      <dl class="row mb-0 small">
        <dt class="col-5 text-secondary">Ứng tuyển lúc</dt>
        <dd class="col-7"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($application['applied_at'] ?? 'now'))) ?></dd>
        <?php if (!empty($application['candidate_location'])): ?>
          <dt class="col-5 text-secondary">Địa điểm</dt>
          <dd class="col-7"><?= htmlspecialchars($application['candidate_location']) ?></dd>
        <?php endif; ?>
      </dl>

      <?php if (!empty($skillTags)): ?>
        <div class="mt-3">
          <h4 class="h6 fw-semibold">Kỹ năng nổi bật</h4>
          <div class="d-flex flex-wrap gap-2 mt-2">
            <?php foreach ($skillTags as $skill): ?>
              <span class="badge bg-light text-dark border">#<?= htmlspecialchars($skill) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="mt-4">
        <?php if (!empty($application['cv_path'])): ?>
          <a class="btn btn-success w-100" href="<?= BASE_URL . '/' . ltrim($application['cv_path'], '/') ?>" target="_blank" rel="noopener">
            <i class="fa-solid fa-download me-2"></i>Tải CV ứng viên
          </a>
        <?php else: ?>
          <div class="alert alert-warning mb-0">
            Ứng viên chưa tải lên CV.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
