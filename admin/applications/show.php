<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Application.php';

if (empty($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$applicationModel = new Application();
$statusLabels = $applicationModel->getStatusLabels();
$statusDescriptions = [
    'applied' => 'Ứng viên đã gửi hồ sơ và chờ xét duyệt',
    'viewed' => 'Nhà tuyển dụng đã xem hồ sơ',
    'shortlisted' => 'Ứng viên nằm trong danh sách phỏng vấn',
    'rejected' => 'Hồ sơ đã bị từ chối và cần ghi chú phản hồi',
    'hired' => 'Ứng viên đã được nhận việc',
    'withdrawn' => 'Ứng viên đã chủ động rút hồ sơ',
];
$statusBadgeMap = [
    'applied' => 'secondary',
    'viewed' => 'info',
    'shortlisted' => 'warning',
    'rejected' => 'danger',
    'hired' => 'success',
    'withdrawn' => 'dark',
];

$applicationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($applicationId <= 0) {
    $_SESSION['admin_application_flash'] = [
        'type' => 'danger',
        'message' => 'Không tìm thấy hồ sơ cần xem. Vui lòng chọn lại.'
    ];
    header('Location: ' . ADMIN_URL . '/applications/index.php');
    exit;
}

$application = $applicationModel->getAdminApplication($applicationId);
if (!$application) {
    $_SESSION['admin_application_flash'] = [
        'type' => 'danger',
        'message' => 'Hồ sơ ứng tuyển không tồn tại hoặc đã bị xoá.'
    ];
    header('Location: ' . ADMIN_URL . '/applications/index.php');
    exit;
}

$currentStatus = $application['status'] ?? 'applied';
$statusOrder = array_keys($statusLabels);
$statusIndex = array_flip($statusOrder);
$currentIndex = isset($statusIndex[$currentStatus]) ? $statusIndex[$currentStatus] : 0;
$progressPercent = count($statusOrder) > 0 ? round((($currentIndex + 1) / count($statusOrder)) * 100) : 0;

$appliedAt = $application['applied_at'] ?? null;
$appliedLabel = $appliedAt ? date('d/m/Y H:i', strtotime($appliedAt)) : '—';
$decisionNote = trim((string)($application['decision_note'] ?? ''));
$coverLetter = trim((string)($application['cover_letter'] ?? ''));
$resumeSnapshot = trim((string)($application['resume_snapshot'] ?? ''));
$jobDescription = trim((string)($application['job_description'] ?? ''));
$candidateSummary = trim((string)($application['summary'] ?? ''));
$cvPath = trim((string)($application['cv_path'] ?? ''));
$cvUrl = $cvPath !== '' ? BASE_URL . '/' . ltrim($cvPath, '/') : null;

$skillTags = [];
if (!empty($application['skills'])) {
    $decodedSkills = json_decode($application['skills'], true);
    if (is_array($decodedSkills)) {
        $skillTags = array_slice(array_filter(array_map('trim', $decodedSkills)), 0, 12);
    }
}

$flash = $_SESSION['admin_application_flash'] ?? null;
unset($_SESSION['admin_application_flash']);

ob_start();
?>
<div class="pagetitle">
  <h1>Chi tiết hồ sơ ứng tuyển</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Trang chủ</a></li>
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/applications/index.php">Hồ sơ ứng tuyển</a></li>
      <li class="breadcrumb-item active">#<?= (int)$application['id'] ?></li>
    </ol>
  </nav>
</div>

<a class="btn btn-link text-decoration-none ps-0 mb-3" href="<?= ADMIN_URL ?>/applications/index.php">
  <i class="bi bi-arrow-left me-2"></i>Quay lại danh sách
</a>

<?php if ($flash): ?>
  <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
  </div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-xl-8">
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
          <div>
            <span class="badge bg-<?= htmlspecialchars($statusBadgeMap[$currentStatus] ?? 'secondary') ?>">
              <?= htmlspecialchars($statusLabels[$currentStatus] ?? ucfirst($currentStatus)) ?>
            </span>
            <h2 class="h4 mt-3 mb-1"><?= htmlspecialchars($application['candidate_name'] ?? 'Ứng viên') ?></h2>
            <div class="text-muted">
              <i class="bi bi-envelope me-2"></i><?= htmlspecialchars($application['candidate_email'] ?? '') ?>
              <?php if (!empty($application['candidate_phone'])): ?>
                <span class="ms-3"><i class="bi bi-telephone me-2"></i><?= htmlspecialchars($application['candidate_phone']) ?></span>
              <?php endif; ?>
            </div>
            <?php if (!empty($application['candidate_location'])): ?>
              <div class="text-muted small mt-1"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($application['candidate_location']) ?></div>
            <?php endif; ?>
          </div>
          <div class="text-muted text-md-end">
            <div class="fw-semibold">Ứng tuyển lúc</div>
            <div><?= htmlspecialchars($appliedLabel) ?></div>
          </div>
        </div>

        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Tiến độ xử lý</div>
            <div class="text-muted small"><?= $progressPercent ?>% hoàn tất</div>
          </div>
          <div class="progress" style="height: 8px;">
            <div class="progress-bar bg-<?= htmlspecialchars($statusBadgeMap[$currentStatus] ?? 'primary') ?>" role="progressbar" style="width: <?= $progressPercent ?>%" aria-valuenow="<?= $progressPercent ?>" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
          <ul class="list-unstyled mt-3 mb-0">
            <?php foreach ($statusOrder as $index => $statusKey): ?>
              <?php $completed = $index <= $currentIndex; ?>
              <li class="d-flex align-items-start gap-3 mb-2">
                <span class="badge rounded-pill <?= $completed ? 'bg-' . ($statusBadgeMap[$statusKey] ?? 'primary') : 'bg-light text-muted border' ?>">
                  <?= $index + 1 ?>
                </span>
                <div>
                  <div class="fw-semibold mb-1">
                    <?= htmlspecialchars($statusLabels[$statusKey] ?? ucfirst($statusKey)) ?>
                    <?php if ($statusKey === $currentStatus): ?>
                      <span class="badge bg-light text-dark border ms-2">Hiện tại</span>
                    <?php endif; ?>
                  </div>
                  <div class="text-muted small">
                    <?= htmlspecialchars($statusDescriptions[$statusKey] ?? '') ?>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="mb-4">
          <h5 class="card-title">Tổng quan ứng viên</h5>
          <?php if ($candidateSummary !== ''): ?>
            <p><?= nl2br(htmlspecialchars($candidateSummary)) ?></p>
          <?php else: ?>
            <p class="text-muted">Ứng viên chưa cập nhật tóm tắt kinh nghiệm.</p>
          <?php endif; ?>
          <?php if (!empty($application['headline'])): ?>
            <div class="alert alert-secondary mb-0" role="alert">
              <strong>Tiêu đề hồ sơ:</strong> <?= htmlspecialchars($application['headline']) ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="mb-4">
          <h5 class="card-title">Thư giới thiệu</h5>
          <div class="bg-light rounded-3 p-3">
            <?= nl2br(htmlspecialchars($coverLetter !== '' ? $coverLetter : 'Ứng viên chưa đính kèm thư giới thiệu.')) ?>
          </div>
        </div>

        <div class="mb-4">
          <h5 class="card-title">Ghi chú từ nhà tuyển dụng</h5>
          <?php if ($decisionNote !== ''): ?>
            <div class="bg-light rounded-3 p-3">
              <?= nl2br(htmlspecialchars($decisionNote)) ?>
            </div>
          <?php else: ?>
            <p class="text-muted">Chưa có phản hồi nào được gửi cho ứng viên.</p>
          <?php endif; ?>
        </div>

        <div>
          <h5 class="card-title">Tóm tắt hồ sơ đính kèm</h5>
          <?php if ($resumeSnapshot !== ''): ?>
            <pre class="bg-dark text-white rounded-3 p-3 small mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($resumeSnapshot) ?></pre>
          <?php else: ?>
            <p class="text-muted">Không có bản tóm tắt CV khi ứng tuyển.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">Thông tin tin tuyển dụng</h5>
        <dl class="row mb-0 small">
          <dt class="col-5 text-secondary">Tiêu đề</dt>
          <dd class="col-7 fw-semibold"><?= htmlspecialchars($application['job_title'] ?? 'Tin tuyển dụng') ?></dd>
          <dt class="col-5 text-secondary">Nhà tuyển dụng</dt>
          <dd class="col-7"><?= htmlspecialchars($application['employer_name'] ?? 'Không xác định') ?></dd>
          <?php if (!empty($application['job_location'])): ?>
            <dt class="col-5 text-secondary">Địa điểm</dt>
            <dd class="col-7"><?= htmlspecialchars($application['job_location']) ?></dd>
          <?php endif; ?>
          <?php if (!empty($application['employment_type'])): ?>
            <dt class="col-5 text-secondary">Hình thức</dt>
            <dd class="col-7"><?= htmlspecialchars($application['employment_type']) ?></dd>
          <?php endif; ?>
          <?php if (!empty($application['job_salary'])): ?>
            <dt class="col-5 text-secondary">Mức lương</dt>
            <dd class="col-7"><?= htmlspecialchars($application['job_salary']) ?></dd>
          <?php endif; ?>
        </dl>
        <?php if ($jobDescription !== ''): ?>
          <div class="bg-light rounded-3 p-3 mt-3 small text-secondary" style="max-height: 220px; overflow-y: auto;">
            <?= nl2br(htmlspecialchars($jobDescription)) ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">Kỹ năng nổi bật</h5>
        <?php if (!empty($skillTags)): ?>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($skillTags as $skill): ?>
              <span class="badge bg-light text-dark border">#<?= htmlspecialchars($skill) ?></span>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-muted">Chưa có kỹ năng nào được liệt kê.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Tài liệu &amp; liên hệ</h5>
        <dl class="row small mb-0">
          <dt class="col-5 text-secondary">Email tuyển dụng</dt>
          <dd class="col-7"><?= htmlspecialchars($application['employer_email'] ?? 'Chưa cập nhật') ?></dd>
          <?php if (!empty($application['candidate_phone'])): ?>
            <dt class="col-5 text-secondary">Điện thoại ứng viên</dt>
            <dd class="col-7"><?= htmlspecialchars($application['candidate_phone']) ?></dd>
          <?php endif; ?>
        </dl>
        <?php if ($cvUrl): ?>
          <a class="btn btn-success w-100 mt-3" href="<?= htmlspecialchars($cvUrl) ?>" target="_blank" rel="noopener">
            <i class="bi bi-download me-2"></i>Tải CV ứng viên
          </a>
        <?php else: ?>
          <div class="alert alert-warning mt-3 mb-0" role="alert">
            Ứng viên chưa tải CV lên hệ thống.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
?>
