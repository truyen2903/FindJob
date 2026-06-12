<?php
require_once dirname(__DIR__, 3) . '/config/config.php';
require_once dirname(__DIR__, 3) . '/app/models/Job.php';
require_once dirname(__DIR__, 3) . '/app/models/SavedJob.php';

if (!function_exists('jf_initials')) {
  function jf_initials($text) {
    $text = trim((string)$text);
    if ($text === '') {
      return 'JF';
    }
    $words = preg_split('/\s+/', $text);
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
      if (count($words) >= 2) {
        return mb_strtoupper(mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr($words[1], 0, 1, 'UTF-8'), 'UTF-8');
      }
      return mb_strtoupper(mb_substr($text, 0, 2, 'UTF-8'), 'UTF-8');
    }
    if (count($words) >= 2) {
      return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($text, 0, 2));
  }
}

$jobModel = new Job();
$categoryOptions = $jobModel->getAllCategories();
$categoryLookup = [];
foreach ($categoryOptions as $categoryOption) {
  $categoryId = (int)($categoryOption['id'] ?? 0);
  if ($categoryId > 0) {
    $categoryLookup[$categoryId] = $categoryOption;
  }
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = (int)($_SESSION['role_id'] ?? 0);
$canSaveJobs = $userId > 0 && $userRole === 3;
$savedJobModel = $canSaveJobs ? new SavedJob() : null;
$savedJobIds = $canSaveJobs ? $savedJobModel->getSavedJobIdsForUser($userId) : [];

$filters = [
    'keyword' => trim($_GET['keyword'] ?? ''),
    'location' => trim($_GET['location'] ?? ''),
  'employment_type' => trim($_GET['type'] ?? ''),
  'category' => (int)($_GET['category'] ?? 0)
];

if ($filters['category'] > 0 && !isset($categoryLookup[$filters['category']])) {
  $filters['category'] = 0;
}

$allowedTypes = ['Full-time', 'Part-time', 'Internship', 'Contract', 'Freelance'];
if ($filters['employment_type'] !== '' && !in_array($filters['employment_type'], $allowedTypes, true)) {
    $filters['employment_type'] = '';
}

$perPage = 9;
$page = max(1, (int)($_GET['page'] ?? 1));

$savedParam = isset($_GET['saved']) ? trim((string)$_GET['saved']) : '';
if ($savedParam !== '' && !$canSaveJobs) {
  $_SESSION['job_share_flash'] = [
    'type' => 'warning',
    'message' => 'Vui lòng đăng nhập bằng tài khoản ứng viên để xem việc làm đã lưu.'
  ];
  header('Location: ' . BASE_URL . '/account/login.php');
  exit;
}

$showSaved = $canSaveJobs && $savedParam !== '' && $savedParam !== '0';

$jobs = [];
$queryError = null;
$totalJobs = 0;

$conditions = ["j.status = 'published'", "(j.deadline IS NULL OR j.deadline >= CURDATE())"];
$params = [];
$types = '';

if ($filters['keyword'] !== '') {
  $conditions[] = '(j.title LIKE ? OR e.company_name LIKE ? OR j.description LIKE ?)';
  $like = '%' . $filters['keyword'] . '%';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $types .= 'sss';
}

if ($filters['location'] !== '') {
  $conditions[] = '(j.location LIKE ? OR e.address LIKE ?)';
  $like = '%' . $filters['location'] . '%';
  $params[] = $like;
  $params[] = $like;
  $types .= 'ss';
}

if ($filters['employment_type'] !== '') {
  $conditions[] = 'j.employment_type = ?';
  $params[] = $filters['employment_type'];
  $types .= 's';
}

if ($filters['category'] > 0) {
  $conditions[] = 'EXISTS (SELECT 1 FROM job_category_map m WHERE m.job_id = j.id AND m.category_id = ?)';
  $params[] = $filters['category'];
  $types .= 'i';
}

if ($showSaved && !empty($savedJobIds)) {
  $placeholders = implode(',', array_fill(0, count($savedJobIds), '?'));
  $conditions[] = "j.id IN ($placeholders)";
  foreach ($savedJobIds as $savedId) {
    $params[] = $savedId;
    $types .= 'i';
  }
}

$totalPages = 1;

if ($showSaved && empty($savedJobIds)) {
  $page = 1;
} else {
  $whereSql = 'WHERE ' . implode(' AND ', $conditions);
  $countSql = "SELECT COUNT(*) AS total FROM jobs j INNER JOIN employers e ON e.id = j.employer_id $whereSql";

  if ($types === '') {
    $countResult = $jobModel->conn->query($countSql);
    if ($countResult instanceof mysqli_result) {
      $row = $countResult->fetch_assoc();
      $totalJobs = (int)($row['total'] ?? 0);
      $countResult->free();
    }
  } else {
    $countStmt = $jobModel->conn->prepare($countSql);
    if ($countStmt !== false) {
      $countStmt->bind_param($types, ...$params);
      if ($countStmt->execute()) {
        $countResult = $countStmt->get_result();
        if ($countResult) {
          $row = $countResult->fetch_assoc();
          $totalJobs = (int)($row['total'] ?? 0);
          $countResult->free();
        }
      }
      $countStmt->close();
    }
  }

  $totalPages = $totalJobs > 0 ? (int)ceil($totalJobs / $perPage) : 1;
  if ($page > $totalPages) {
    $page = $totalPages;
  }
  $offset = ($page - 1) * $perPage;

  $dataSql = "SELECT j.id, j.title, j.location, j.salary, j.employment_type, j.quantity, j.deadline, j.created_at, j.view_count,
             e.company_name, e.logo_path
        FROM jobs j
        INNER JOIN employers e ON e.id = j.employer_id
        $whereSql
        ORDER BY j.created_at DESC
        LIMIT ? OFFSET ?";

  $dataTypes = $types . 'ii';
  $dataParams = $params;
  $dataParams[] = $perPage;
  $dataParams[] = $offset;

  $stmt = $jobModel->conn->prepare($dataSql);
  if ($stmt === false) {
    $queryError = $jobModel->conn->error;
  } else {
    $stmt->bind_param($dataTypes, ...$dataParams);
    if ($stmt->execute()) {
      $result = $stmt->get_result();
      if ($result) {
        while ($row = $result->fetch_assoc()) {
          $jobs[] = $row;
        }
        $result->free();
      }
    } else {
      $queryError = $stmt->error;
    }
    $stmt->close();
  }
}

$displayedJobs = count($jobs);
$jobCategoryMap = [];
if (!empty($jobs)) {
  $jobIds = [];
  foreach ($jobs as $jobRow) {
    $jobIds[] = (int)($jobRow['id'] ?? 0);
  }
  $jobIds = array_values(array_filter(array_unique($jobIds)));
  if (!empty($jobIds)) {
    $jobCategoryMap = $jobModel->getCategoriesForJobs($jobIds);
  }
}

$fullTimeCount = count(array_filter($jobs, static fn($job) => ($job['employment_type'] ?? '') === 'Full-time'));
$remoteCount = count(array_filter($jobs, static fn($job) => stripos((string)($job['location'] ?? ''), 'remote') !== false));
$hasFilters = $filters['keyword'] !== '' || $filters['location'] !== '' || $filters['employment_type'] !== '' || $filters['category'] > 0;

$jobShareFlash = $_SESSION['job_share_flash'] ?? null;
if ($jobShareFlash) {
    unset($_SESSION['job_share_flash']);
}

$currentUri = $_SERVER['REQUEST_URI'] ?? BASE_URL . '/job/share/index.php';
$paginationBase = BASE_URL . '/job/share/index.php';
$paginationQuery = $_GET;
unset($paginationQuery['page']);
$buildPageUrl = static function (int $pageNumber, string $base, array $query): string {
    $query['page'] = $pageNumber;
    $queryString = http_build_query($query);
    if ($queryString === '') {
        return $base . '?page=' . $pageNumber;
    }
    return $base . '?' . $queryString;
};

function jf_job_time_ago(?string $date): string {
  if (!$date) {
    return 'Không xác định';
  }
  $timestamp = strtotime($date);
  if (!$timestamp) {
    return $date;
  }
  $diff = time() - $timestamp;
  if ($diff < 60) {
    return 'Vừa đăng';
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

$pageTitle = $showSaved ? 'Việc làm đã lưu | JobFind' : 'Danh sách việc làm mới nhất | JobFind';
$headingTitle = $showSaved ? 'Việc làm đã lưu' : 'Tìm việc làm phù hợp';
$summaryUnit = $showSaved ? 'việc đã lưu' : 'vị trí';
$totalLabel = $showSaved ? 'Tổng việc đã lưu' : 'Tổng tin phù hợp';
$resetFiltersUrl = $showSaved ? BASE_URL . '/job/share/index.php?saved=1' : BASE_URL . '/job/share/index.php';

$bodyClass = 'home-page job-listing-page';
$additionalCSS = $additionalCSS ?? [];
$additionalCSS[] = '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">';
$additionalCSS[] = '<link rel="stylesheet" href="' . ASSETS_URL . '/css/home.css?v=' . (filemtime(dirname(__DIR__, 2) . '/assets/css/home.css') ?: time()) . '">';
$additionalScripts = $additionalScripts ?? [];
$additionalScripts[] = '<script src="' . ASSETS_URL . '/js/homepage.js" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<main class="home-main">
  <?php if ($jobShareFlash): ?>
    <div class="container mt-4">
      <div class="alert alert-<?= htmlspecialchars($jobShareFlash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($jobShareFlash['message'] ?? '') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    </div>
  <?php endif; ?>

  <section class="home-hero job-listing-hero">
    <div class="container job-listing-hero-inner">
      <div class="job-listing-copy">
        <span class="home-eyebrow"><i class="fa-solid fa-briefcase"></i> <?= $showSaved ? 'Việc làm đã lưu' : 'Khám phá cơ hội' ?></span>
        <h1 class="home-hero-title job-listing-title"><?= htmlspecialchars($headingTitle) ?></h1>
        <p class="home-hero-subtitle">
          <?= $showSaved
            ? 'Quản lý và theo dõi các cơ hội việc làm bạn quan tâm.'
            : 'Tìm kiếm trong ' . number_format($totalJobs) . ' việc làm đang tuyển dụng trên JobFind.'
          ?>
        </p>
      </div>

      <div class="home-search-card job-listing-search">
        <form class="home-search-form" method="get" action="<?= BASE_URL ?>/job/share/index.php" data-search-url="<?= BASE_URL ?>/job/share/index.php">
          <?php if ($showSaved): ?>
            <input type="hidden" name="saved" value="1">
          <?php endif; ?>
          <label class="home-search-field" for="keyword">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="keyword" name="keyword" placeholder="Tên công việc, kỹ năng, công ty" value="<?= htmlspecialchars($filters['keyword']) ?>">
          </label>
          <label class="home-search-field" for="location">
            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
            <select id="location" name="location">
              <option value="" <?= $filters['location'] === '' ? 'selected' : '' ?>>Toàn quốc</option>
              <option value="Hà Nội" <?= $filters['location'] === 'Hà Nội' ? 'selected' : '' ?>>Hà Nội</option>
              <option value="TP. Hồ Chí Minh" <?= $filters['location'] === 'TP. Hồ Chí Minh' ? 'selected' : '' ?>>TP. Hồ Chí Minh</option>
              <option value="Đà Nẵng" <?= $filters['location'] === 'Đà Nẵng' ? 'selected' : '' ?>>Đà Nẵng</option>
              <option value="Remote" <?= $filters['location'] === 'Remote' ? 'selected' : '' ?>>Remote</option>
            </select>
          </label>
          <button class="home-search-submit" type="submit">Tìm kiếm</button>
        </form>
      </div>
    </div>
  </section>

  <section class="home-section job-results-section">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-3">
          <aside class="home-glass job-filter-panel">
            <h3 class="job-filter-title">Bộ lọc nâng cao</h3>
            <form method="get">
              <?php if ($showSaved): ?>
                <input type="hidden" name="saved" value="1">
              <?php endif; ?>
              <input type="hidden" name="keyword" value="<?= htmlspecialchars($filters['keyword']) ?>">
              <input type="hidden" name="location" value="<?= htmlspecialchars($filters['location']) ?>">

              <div class="mb-3">
                <label class="form-label fw-semibold job-filter-label" for="category">Ngành nghề</label>
                <select id="category" name="category" class="form-select">
                  <option value="0" <?= $filters['category'] === 0 ? 'selected' : '' ?>>Tất cả ngành</option>
                  <?php foreach ($categoryOptions as $category): ?>
                    <?php $categoryId = (int)($category['id'] ?? 0); ?>
                    <?php if ($categoryId <= 0) { continue; } ?>
                    <option value="<?= $categoryId ?>" <?= $filters['category'] === $categoryId ? 'selected' : '' ?>><?= htmlspecialchars($category['name'] ?? ('Ngành nghề #' . $categoryId)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold job-filter-label" for="type">Hình thức làm việc</label>
                <select id="type" name="type" class="form-select">
                  <option value="">Tất cả hình thức</option>
                  <?php foreach ($allowedTypes as $type): ?>
                    <option value="<?= htmlspecialchars($type) ?>" <?= $filters['employment_type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="d-grid gap-2">
                <button type="submit" class="home-primary-btn">Áp dụng</button>
                <a href="<?= htmlspecialchars($resetFiltersUrl) ?>" class="btn btn-light">Xóa bộ lọc</a>
              </div>
            </form>

            <hr class="my-4">

            <div>
              <h6 class="fw-semibold mb-3 job-filter-label">Thống kê</h6>
              <ul class="list-unstyled mb-0 job-filter-stats">
                <li class="d-flex justify-content-between mb-2">
                  <span class="text-muted"><?= htmlspecialchars($totalLabel) ?></span>
                  <strong><?= number_format($totalJobs) ?></strong>
                </li>
                <li class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Hiển thị</span>
                  <strong><?= $displayedJobs ?></strong>
                </li>
                <li class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Full-time</span>
                  <strong><?= $fullTimeCount ?></strong>
                </li>
                <li class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Remote</span>
                  <strong><?= $remoteCount ?></strong>
                </li>
              </ul>
            </div>
          </aside>
        </div>

        <div class="col-lg-9">
          <div class="job-results-toolbar">
            <div>
              <p class="text-muted mb-0">
                Hiển thị <strong><?= $displayedJobs ?></strong> / <strong><?= number_format($totalJobs) ?></strong> <?= htmlspecialchars($summaryUnit) ?><?= $filters['keyword'] !== '' ? ' cho "' . htmlspecialchars($filters['keyword']) . '"' : '' ?>
              </p>
            </div>
            <?php if ($showSaved): ?>
              <a class="see-all" href="<?= BASE_URL ?>/job/share/index.php">Khám phá thêm <i class="fa-solid fa-arrow-right ms-1"></i></a>
            <?php else: ?>
              <a class="see-all" href="<?= BASE_URL ?>/employer/index.php">Nhà tuyển dụng <i class="fa-solid fa-arrow-right ms-1"></i></a>
            <?php endif; ?>
          </div>

          <?php if ($queryError): ?>
            <div class="alert alert-danger">Không thể tải dữ liệu việc làm. Vui lòng thử lại sau.</div>
          <?php elseif (empty($jobs)): ?>
            <div class="home-glass job-empty-state">
              <?php if ($showSaved): ?>
                <?php if ($hasFilters): ?>
                  <i class="fa-solid fa-filter-circle-xmark fa-3x text-muted mb-3"></i>
                  <h5 class="fw-semibold mb-2">Không có việc làm đã lưu phù hợp</h5>
                  <p class="text-muted mb-3">Không có việc làm đã lưu nào khớp với bộ lọc hiện tại.</p>
                  <a class="home-primary-btn" href="<?= htmlspecialchars($resetFiltersUrl) ?>">Xóa bộ lọc</a>
                <?php else: ?>
                  <i class="fa-regular fa-heart fa-3x text-muted mb-3"></i>
                  <h5 class="fw-semibold mb-2">Bạn chưa lưu việc làm nào</h5>
                  <p class="text-muted mb-3">Nhấn biểu tượng trái tim trên mỗi tin tuyển dụng để lưu và quản lý.</p>
                  <a class="home-primary-btn" href="<?= BASE_URL ?>/job/share/index.php">Khám phá việc làm</a>
                <?php endif; ?>
              <?php else: ?>
                <i class="fa-solid fa-magnifying-glass fa-3x text-muted mb-3"></i>
                <h5 class="fw-semibold mb-2">Không tìm thấy việc làm phù hợp</h5>
                <p class="text-muted mb-3">Hãy điều chỉnh bộ lọc hoặc quay lại sau để xem thêm cơ hội mới.</p>
                <a class="home-primary-btn" href="<?= htmlspecialchars($resetFiltersUrl) ?>">Xóa bộ lọc</a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="job-grid">
              <?php foreach ($jobs as $job): ?>
                <?php
                  $jobId = (int)($job['id'] ?? 0);
                  $companyName = $job['company_name'] ?? 'Nhà tuyển dụng JobFind';
                  $title = $job['title'] ?? 'Tin tuyển dụng';
                  $location = $job['location'] ?: 'Toàn quốc';
                  $salary = $job['salary'] ?: 'Thỏa thuận';
                  $employmentType = $job['employment_type'] ?: 'Full-time';
                  $viewCount = isset($job['view_count']) ? (int)$job['view_count'] : 0;
                  $postedAgo = jf_job_time_ago($job['created_at'] ?? null);
                  $jobDetailUrl = BASE_URL . '/job/share/view.php?id=' . $jobId;
                  $isSaved = $canSaveJobs && in_array($jobId, $savedJobIds, true);
                  $jobCategories = $jobCategoryMap[$jobId] ?? [];
                ?>
                <article class="job-card jf-job-card home-glass fade-in-element">
                  <div class="job-card-top">
                    <div class="logo-tile"><?= htmlspecialchars(jf_initials($companyName)) ?></div>
                    <div class="job-title-wrap">
                      <h3><?= htmlspecialchars($title) ?></h3>
                      <p class="mb-0"><?= htmlspecialchars($companyName) ?></p>
                    </div>
                    <?php if ($canSaveJobs): ?>
                      <form action="<?= BASE_URL ?>/job/share/save.php" method="post" class="job-save-form">
                        <input type="hidden" name="job_id" value="<?= $jobId ?>">
                        <input type="hidden" name="return" value="<?= htmlspecialchars($currentUri) ?>">
                        <input type="hidden" name="action" value="<?= $isSaved ? 'remove' : 'save' ?>">
                        <button type="submit" class="save-btn <?= $isSaved ? 'text-danger' : '' ?>" title="<?= $isSaved ? 'Bỏ lưu việc làm' : 'Lưu việc làm' ?>" aria-label="<?= $isSaved ? 'Bỏ lưu việc làm' : 'Lưu việc làm' ?>">
                          <i class="fa-<?= $isSaved ? 'solid' : 'regular' ?> fa-heart"></i>
                        </button>
                      </form>
                    <?php else: ?>
                      <a href="<?= BASE_URL ?>/account/login.php" class="save-btn job-save-link" title="Đăng nhập để lưu việc" aria-label="Đăng nhập để lưu việc">
                        <i class="fa-regular fa-heart"></i>
                      </a>
                    <?php endif; ?>
                  </div>

                  <?php if (!empty($jobCategories)): ?>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                      <?php foreach (array_slice($jobCategories, 0, 3) as $category): ?>
                        <span class="job-category-badge"><?= htmlspecialchars($category['name']) ?></span>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>

                  <p class="job-posted-meta">
                    <?= htmlspecialchars($postedAgo) ?> • <?= number_format($viewCount) ?> lượt xem
                  </p>

                  <div class="job-meta">
                    <span class="job-pill"><i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($location) ?></span>
                    <span class="job-pill"><i class="fa-solid fa-coins me-1"></i><?= htmlspecialchars($salary) ?></span>
                    <span class="job-pill"><?= htmlspecialchars($employmentType) ?></span>
                  </div>

                  <div class="job-card-bottom">
                    <a class="apply-link" href="<?= htmlspecialchars($jobDetailUrl) ?>">Xem chi tiết <i class="fa-solid fa-arrow-right ms-1"></i></a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
              <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $startPage + 4);
                $startPage = max(1, $endPage - 4);
              ?>
              <nav class="mt-5" aria-label="Phân trang việc làm">
                <ul class="pagination justify-content-center">
                  <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $page <= 1 ? '#' : $buildPageUrl($page - 1, $paginationBase, $paginationQuery) ?>" tabindex="<?= $page <= 1 ? '-1' : '0' ?>" aria-label="Trang trước">
                      <i class="fa-solid fa-chevron-left"></i>
                    </a>
                  </li>
                  <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                      <a class="page-link" href="<?= $buildPageUrl($p, $paginationBase, $paginationQuery) ?>"><?= $p ?></a>
                    </li>
                  <?php endfor; ?>
                  <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $page >= $totalPages ? '#' : $buildPageUrl($page + 1, $paginationBase, $paginationQuery) ?>" tabindex="<?= $page >= $totalPages ? '-1' : '0' ?>" aria-label="Trang sau">
                      <i class="fa-solid fa-chevron-right"></i>
                    </a>
                  </li>
                </ul>
              </nav>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
