<?php
require_once dirname(__DIR__, 3) . '/config/config.php';
require_once dirname(__DIR__, 3) . '/app/models/Job.php';
require_once dirname(__DIR__, 3) . '/app/models/SavedJob.php';

$jobModel = new Job();

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = (int)($_SESSION['role_id'] ?? 0);
$canSaveJobs = $userId > 0 && $userRole === 3;
$savedJobModel = $canSaveJobs ? new SavedJob() : null;
$savedJobIds = $canSaveJobs ? $savedJobModel->getSavedJobIdsForUser($userId) : [];

$rangeOptions = [
    '7' => 7,
    '30' => 30,
    '90' => 90
];
$rangeParam = isset($_GET['range']) ? (string)$_GET['range'] : '30';
$withinDays = $rangeOptions[$rangeParam] ?? 30;

$hotJobs = $jobModel->getHotJobs(24, ['within_days' => $withinDays]);
if (empty($hotJobs)) {
    $hotJobs = $jobModel->getHotJobs(24, ['within_days' => 365]);
}
if (empty($hotJobs)) {
    $hotJobs = $jobModel->getFeaturedJobs(24);
}

$hotJobCategoryMap = [];
if (!empty($hotJobs)) {
  $jobIds = [];
  foreach ($hotJobs as $jobRow) {
    $jobIds[] = (int)($jobRow['id'] ?? 0);
  }
  $jobIds = array_values(array_filter(array_unique($jobIds)));
  if (!empty($jobIds)) {
    $hotJobCategoryMap = $jobModel->getCategoriesForJobs($jobIds);
  }
}

$jobShareFlash = $_SESSION['job_share_flash'] ?? null;
if ($jobShareFlash) {
    unset($_SESSION['job_share_flash']);
}

$currentUri = $_SERVER['REQUEST_URI'] ?? BASE_URL . '/job/share/hot.php';

function jf_hot_job_time_ago(?string $date): string {
    if (!$date) {
        return 'Chưa có lượt xem';
    }
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return $date;
    }
    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'Vừa xem';
    }
    $minutes = floor($diff / 60);
    if ($minutes < 60) {
        return $minutes . ' phút trước';
    }
    $hours = floor($minutes / 60);
    if ($hours < 24) {
        return $hours . ' giờ trước';
    }
    $days = floor($hours / 24);
    if ($days === 1) {
        return '1 ngày trước';
    }
    if ($days < 7) {
        return $days . ' ngày trước';
    }
    $weeks = floor($days / 7);
    if ($weeks === 1) {
        return '1 tuần trước';
    }
    if ($weeks < 5) {
        return $weeks . ' tuần trước';
    }
    return date('d/m/Y', $timestamp);
}

$pageTitle = 'Việc làm hot | JobFind';
$bodyClass = 'job-hot-page';
$additionalCSS = $additionalCSS ?? [];
$additionalCSS[] = '<link rel="stylesheet" href="' . ASSETS_URL . '/css/home.css">';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<main class="job-hot-page py-5">
  <div class="container">
    <?php if ($jobShareFlash): ?>
      <div class="alert alert-<?= htmlspecialchars($jobShareFlash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($jobShareFlash['message'] ?? '') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
      <div>
        <h1 class="h3 mb-1">Việc làm hot</h1>
        <p class="text-muted mb-0">Xếp hạng theo số lượt xem<?= $withinDays ? ' trong ' . $withinDays . ' ngày gần đây' : '' ?>.</p>
      </div>
      <form method="get" class="d-flex align-items-center gap-2">
        <label for="range" class="text-muted small mb-0">Khoảng thời gian</label>
        <select id="range" name="range" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="7" <?= $withinDays === 7 ? 'selected' : '' ?>>7 ngày</option>
          <option value="30" <?= $withinDays === 30 ? 'selected' : '' ?>>30 ngày</option>
          <option value="90" <?= $withinDays === 90 ? 'selected' : '' ?>>90 ngày</option>
        </select>
      </form>
    </div>

    <?php if (empty($hotJobs)): ?>
      <div class="alert alert-light border text-center py-5">
        <h5 class="fw-semibold mb-2">Chưa có việc làm nổi bật</h5>
        <p class="text-muted mb-3">Hãy khám phá danh sách việc làm mới nhất để tìm cơ hội phù hợp.</p>
        <a class="btn btn-success" href="<?= BASE_URL ?>/job/share/index.php">Xem việc làm mới</a>
      </div>
    <?php else: ?>
      <div class="row row-cols-1 row-cols-lg-2 g-4">
        <?php foreach ($hotJobs as $job): ?>
          <?php
            $logoUrl = '';
            $logoPath = trim((string)($job['logo_path'] ?? ''));
            if ($logoPath !== '') {
                $logoUrl = BASE_URL . '/' . ltrim($logoPath, '/');
            }
            $companyName = $job['company_name'] ?? 'Nhà tuyển dụng JobFind';
            $employmentType = $job['employment_type'] ?: 'Full-time';
            $location = $job['location'] ?: 'Toàn quốc';
            $salary = $job['salary'] ?: 'Thỏa thuận';
            $viewCount = isset($job['view_count']) ? (int)$job['view_count'] : 0;
            $lastViewed = $viewCount > 0 ? jf_hot_job_time_ago($job['last_viewed_at'] ?? null) : jf_hot_job_time_ago($job['created_at'] ?? null);
            $jobId = (int)$job['id'];
            $isSaved = $canSaveJobs && in_array($jobId, $savedJobIds, true);
            $deadlineLabel = $job['deadline'] ? date('d/m/Y', strtotime($job['deadline'])) : null;
            $jobCategories = $hotJobCategoryMap[$jobId] ?? [];
          ?>
          <div class="col">
            <article class="card shadow-sm border-0 h-100">
              <div class="card-body p-4">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="job-logo">
                      <?php if ($logoUrl !== ''): ?>
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($companyName) ?>">
                      <?php else: ?>
                        <span class="job-logo-fallback"><?= htmlspecialchars(strtoupper(substr($companyName, 0, 2))) ?></span>
                      <?php endif; ?>
                    </div>
                    <div>
                      <h3 class="h5 mb-1">
                        <a href="<?= BASE_URL ?>/job/share/view.php?id=<?= $jobId ?>" class="text-decoration-none"><?= htmlspecialchars($job['title']) ?></a>
                      </h3>
                      <div class="text-muted small fw-semibold"><?= htmlspecialchars($companyName) ?></div>
                    </div>
                  </div>
                  <div class="ms-3">
                    <?php if ($canSaveJobs): ?>
                      <form action="<?= BASE_URL ?>/job/share/save.php" method="post" class="job-save-form">
                        <input type="hidden" name="job_id" value="<?= $jobId ?>">
                        <input type="hidden" name="return" value="<?= htmlspecialchars($currentUri) ?>">
                        <input type="hidden" name="action" value="<?= $isSaved ? 'remove' : 'save' ?>">
                        <button type="submit" class="btn btn-sm btn-link p-0 text-decoration-none <?= $isSaved ? 'text-danger' : 'text-muted' ?>" title="<?= $isSaved ? 'Bỏ lưu việc làm' : 'Lưu việc làm' ?>" aria-label="<?= $isSaved ? 'Bỏ lưu việc làm' : 'Lưu việc làm' ?>">
                          <i class="fa-<?= $isSaved ? 'solid' : 'regular' ?> fa-heart fa-lg"></i>
                        </button>
                      </form>
                    <?php else: ?>
                      <a href="<?= BASE_URL ?>/account/login.php" class="btn btn-sm btn-link p-0 text-muted" title="Đăng nhập để lưu việc" aria-label="Đăng nhập để lưu việc">
                        <i class="fa-regular fa-heart fa-lg"></i>
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="row row-cols-1 row-cols-md-3 g-3 small text-muted mb-3">
                  <div><i class="fa-solid fa-location-dot me-2 text-success"></i><?= htmlspecialchars($location) ?></div>
                  <div><i class="fa-solid fa-coins me-2 text-success"></i><?= htmlspecialchars($salary) ?></div>
                  <div><i class="fa-solid fa-suitcase me-2 text-success"></i><?= htmlspecialchars($employmentType) ?></div>
                </div>
                <?php if (!empty($jobCategories)): ?>
                  <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php foreach ($jobCategories as $category): ?>
                      <span class="badge bg-success bg-opacity-10 text-success border border-success"><?= htmlspecialchars($category['name']) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between">
                  <div class="d-flex flex-column flex-md-row gap-2 text-muted">
                    <div class="d-flex align-items-center gap-2">
                      <span class="badge bg-success bg-opacity-10 text-success">Lượt xem: <?= number_format($viewCount) ?></span>
                      <span class="small">Gần nhất: <?= htmlspecialchars($lastViewed) ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                      <span class="small"><i class="fa-solid fa-users me-2 text-success"></i><?= $job['quantity'] ? (int)$job['quantity'] . ' vị trí' : 'Không giới hạn vị trí' ?></span>
                      <span class="small"><i class="fa-solid fa-calendar-day me-2 text-success"></i><?= $deadlineLabel ? 'Hạn ' . htmlspecialchars($deadlineLabel) : 'Hạn nộp linh hoạt' ?></span>
                    </div>
                  </div>
                  <a href="<?= BASE_URL ?>/job/share/view.php?id=<?= $jobId ?>" class="btn btn-outline-success mt-3 mt-sm-0">
                    Xem chi tiết
                    <i class="fa-solid fa-arrow-right ms-2"></i>
                  </a>
                </div>
              </div>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
