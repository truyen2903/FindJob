<?php
require_once dirname(__DIR__) . '/../config/config.php';
require_once dirname(__DIR__) . '/../app/models/Employer.php';
require_once dirname(__DIR__) . '/../app/models/Job.php';
require_once dirname(__DIR__) . '/../app/models/User.php';
require_once dirname(__DIR__) . '/../app/models/SavedJob.php';

$employerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($employerId <= 0) {
    header('Location: ' . BASE_URL . '/employer/index.php');
    exit;
}

$employerModel = new Employer();
$jobModel = new Job();
$userModel = new User();

$employer = $employerModel->getById($employerId);
if (!$employer) {
    header('Location: ' . BASE_URL . '/employer/index.php');
    exit;
}

$ownerUser = null;
if (!empty($employer['user_id'])) {
    $ownerUser = $userModel->getById((int)$employer['user_id']);
}

$jobs = $jobModel->getPublishedByEmployer($employerId);

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = (int)($_SESSION['role_id'] ?? 0);
$canSaveJobs = $userId > 0 && $userRole === 3;
$savedJobModel = $canSaveJobs ? new SavedJob() : null;
$savedJobIds = $canSaveJobs ? $savedJobModel->getSavedJobIdsForUser($userId) : [];
$isOwner = $userId > 0 && $userRole === 2 && (int)$employer['user_id'] === $userId;
$profileUpdated = isset($_GET['updated']);

$companyName = $employer['company_name'] ?? 'Nhà tuyển dụng JobFind';
$companyAbout = trim((string)($employer['about'] ?? ''));
$companyWebsite = trim((string)($employer['website'] ?? ''));
$companyAddress = trim((string)($employer['address'] ?? ''));
$companyLogo = trim((string)($employer['logo_path'] ?? ''));
$logoUrl = $companyLogo !== '' ? BASE_URL . '/' . ltrim($companyLogo, '/') : '';
$companyInitial = strtoupper(substr($companyName, 0, 2));

$totalJobs = count($jobs);
$totalViews = 0;
$recentJobs = 0;
$latestActivity = null;
$jobLocations = [];
$now = time();
$recentThreshold = strtotime('-30 days', $now);

foreach ($jobs as $job) {
    $totalViews += (int)($job['view_count'] ?? 0);
    $jobCreated = strtotime($job['created_at'] ?? '');
    $jobUpdated = strtotime($job['updated_at'] ?? '');
    $activity = $jobUpdated ?: ($jobCreated ?: 0);
    if ($activity > 0 && ($latestActivity === null || $activity > $latestActivity)) {
        $latestActivity = $activity;
    }
    if ($jobCreated !== false && $jobCreated >= $recentThreshold) {
        $recentJobs++;
    }
    $location = trim((string)($job['location'] ?? ''));
    if ($location !== '') {
        $jobLocations[$location] = true;
    }
}

$uniqueLocations = array_keys($jobLocations);
sort($uniqueLocations, SORT_NATURAL | SORT_FLAG_CASE);

$activityLabel = $latestActivity ? date('d/m/Y', $latestActivity) : 'Đang cập nhật';

if (!function_exists('jf_profile_map_embed_url')) {
  function jf_profile_map_embed_url(?string $address): ?string {
    $address = trim((string)$address);
    if ($address === '') {
      return null;
    }
    $query = rawurlencode($address);
    return 'https://www.google.com/maps?q=' . $query . '&output=embed';
  }
}

$primaryMapAddress = $companyAddress !== '' ? $companyAddress : ($uniqueLocations[0] ?? '');
$companyMapEmbedUrl = jf_profile_map_embed_url($primaryMapAddress);

$benefits = jf_profile_get_benefits($employer);
$cultureHighlights = jf_profile_get_culture_highlights($companyName, $totalJobs, $recentJobs, $uniqueLocations, $totalViews);
$hiringTimeline = jf_profile_get_hiring_timeline($jobs);
$relatedJobs = $jobModel->getHotJobs(6, [
  'within_days' => 60,
  'exclude_employer_id' => $employerId
]);

function jf_profile_snippet(string $text, int $limit = 220): string {
    $text = trim($text);
    if ($text === '') {
        return 'Doanh nghiệp đang cập nhật thông tin giới thiệu.';
    }
    if (function_exists('mb_strimwidth')) {
        $snippet = mb_strimwidth($text, 0, $limit, '...');
    } else {
        $snippet = strlen($text) > $limit ? substr($text, 0, $limit - 3) . '...' : $text;
    }
    return $snippet;
}

function jf_profile_format_paragraphs(string $text): string {
    $text = trim($text);
    if ($text === '') {
        return '<p class="text-muted">Doanh nghiệp đang cập nhật thông tin giới thiệu chi tiết.</p>';
    }
    $escaped = htmlspecialchars($text);
    $paragraphs = preg_split('/\r\n|\r|\n/', $escaped);
    $paragraphs = array_map(static function ($line) {
        $line = trim($line);
        return $line === '' ? '' : '<p>' . $line . '</p>';
    }, $paragraphs);
    $paragraphs = array_filter($paragraphs, static fn($p) => $p !== '');
    if (empty($paragraphs)) {
        return '<p class="text-muted">Doanh nghiệp đang cập nhật thông tin giới thiệu chi tiết.</p>';
    }
    return implode('', $paragraphs);
}

function jf_profile_get_benefits(array $employer): array {
  $raw = '';
  if (isset($employer['benefits'])) {
    $raw = trim((string)$employer['benefits']);
  }

  if ($raw !== '') {
    $parts = preg_split('/[\r\n;,]+/', $raw);
    $parts = array_map(static fn($item) => trim($item), $parts);
    $parts = array_filter($parts, static fn($item) => $item !== '');
    $parts = array_values(array_unique($parts));
    if (!empty($parts)) {
      return $parts;
    }
  }

  return [
    'Thưởng lương tháng 13 và đánh giá hiệu suất 2 lần/năm',
    'Bảo hiểm sức khỏe toàn diện cho nhân viên và người thân',
    'Làm việc hybrid linh hoạt, hỗ trợ thiết bị làm việc',
    'Ngân sách đào tạo & chứng chỉ nghề nghiệp hàng năm',
    '12 ngày phép năm + 5 ngày Recharge Day toàn công ty'
  ];
}

function jf_profile_benefit_icon(string $benefit): string {
  $normalized = strtolower($benefit);
  $map = [
    'bảo hiểm' => 'fa-shield-heart',
    'health' => 'fa-shield-heart',
    'thưởng' => 'fa-gift',
    'bonus' => 'fa-gift',
    'hybrid' => 'fa-building-columns',
    'remote' => 'fa-laptop-house',
    'đào tạo' => 'fa-graduation-cap',
    'training' => 'fa-graduation-cap',
    'chứng chỉ' => 'fa-certificate',
    'phép' => 'fa-umbrella-beach',
    'nghỉ' => 'fa-umbrella-beach',
    'thiết bị' => 'fa-computer',
    'laptop' => 'fa-computer',
    'phúc lợi' => 'fa-gem'
  ];
  foreach ($map as $keyword => $icon) {
    if (strpos($normalized, $keyword) !== false) {
      return 'fa-solid ' . $icon;
    }
  }
  return 'fa-solid fa-seedling';
}

function jf_profile_get_culture_highlights(string $companyName, int $totalJobs, int $recentJobs, array $uniqueLocations, int $totalViews): array {
  $highlights = [];

  $highlights[] = [
    'icon' => 'fa-people-group',
    'title' => 'Đội ngũ đang mở rộng',
    'description' => $totalJobs > 0
      ? $companyName . ' đang mở ' . $totalJobs . ' vị trí và tích cực tìm kiếm nhân sự nổi bật.'
      : $companyName . ' đang chuẩn bị cho những chiến dịch tuyển dụng mới.'
  ];

  if ($recentJobs > 0) {
    $highlights[] = [
      'icon' => 'fa-rocket',
      'title' => 'Tuyển dụng sôi động',
      'description' => $recentJobs . ' tin đăng mới trong 30 ngày thể hiện tốc độ tăng trưởng và nhu cầu nhân sự thực tế.'
    ];
  }

  if (!empty($uniqueLocations)) {
    $highlights[] = [
      'icon' => 'fa-map-location-dot',
      'title' => 'Môi trường đa địa điểm',
      'description' => 'Cơ hội làm việc tại ' . count($uniqueLocations) . ' địa điểm, phù hợp cho cả nhân sự mong muốn on-site hoặc linh hoạt địa lý.'
    ];
  }

  if ($totalViews > 0) {
    $highlights[] = [
      'icon' => 'fa-eye',
      'title' => 'Được ứng viên quan tâm',
      'description' => number_format($totalViews) . ' lượt xem việc làm trên JobFind, phản ánh mức độ hấp dẫn của thương hiệu tuyển dụng.'
    ];
  }

  if (empty($highlights)) {
    $highlights[] = [
      'icon' => 'fa-lightbulb',
      'title' => 'Không ngừng đổi mới',
      'description' => $companyName . ' chú trọng xây dựng văn hóa doanh nghiệp và trải nghiệm nhân viên.'
    ];
  }

  return $highlights;
}

function jf_profile_get_hiring_timeline(array $jobs): array {
  if (empty($jobs)) {
    return [];
  }

  $timeline = [];
  foreach ($jobs as $job) {
    $createdAt = isset($job['created_at']) ? strtotime((string)$job['created_at']) : false;
    $createdAt = $createdAt ?: null;
    $timeline[] = [
      'title' => $job['title'] ?? 'Tin tuyển dụng',
      'date' => $createdAt,
      'view_count' => (int)($job['view_count'] ?? 0)
    ];
  }

  usort($timeline, static function ($a, $b) {
    return ($b['date'] ?? 0) <=> ($a['date'] ?? 0);
  });

  return array_slice($timeline, 0, 6);
}

function jf_profile_format_date(?int $timestamp): string {
  if (!$timestamp) {
    return 'Đang cập nhật';
  }
  return date('d/m/Y', $timestamp);
}

$pageTitle = htmlspecialchars($companyName) . ' | Hồ sơ nhà tuyển dụng JobFind';
$bodyClass = 'employer-profile';
$additionalCSS = $additionalCSS ?? [];
$additionalCSS[] = '<link rel="stylesheet" href="' . ASSETS_URL . '/css/employer-profile.css">';
require_once dirname(__DIR__) . '/includes/header.php';

$currentUri = $_SERVER['REQUEST_URI'] ?? (BASE_URL . '/employer/show.php?id=' . $employerId);
?>

<main class="employer-profile">
  <section class="employer-hero">
    <div class="container">
      <div class="hero-card">
        <div class="company-header">
          <div class="company-logo">
            <?php if ($logoUrl !== ''): ?>
              <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($companyName) ?>">
            <?php else: ?>
              <span class="company-logo-fallback"><?= htmlspecialchars($companyInitial) ?></span>
            <?php endif; ?>
          </div>
          <div>
            <h1><?= htmlspecialchars($companyName) ?></h1>
            <div class="company-meta">
              <?php if ($companyAddress !== ''): ?>
                <span><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($companyAddress) ?></span>
              <?php endif; ?>
              <?php if ($companyWebsite !== ''): ?>
                <span><i class="fa-solid fa-globe"></i><a href="<?= htmlspecialchars($companyWebsite) ?>" target="_blank" rel="noopener">Website chính thức</a></span>
              <?php endif; ?>
              <span><i class="fa-solid fa-briefcase"></i><?= $totalJobs ?> việc làm đang mở</span>
              <span><i class="fa-solid fa-clock"></i>Cập nhật gần nhất <?= htmlspecialchars($activityLabel) ?></span>
            </div>
          </div>
        </div>
        <div class="d-flex flex-column flex-md-row justify-content-between gap-4">
          <p class="mb-0 text-muted flex-grow-1">
            <?= htmlspecialchars(jf_profile_snippet($companyAbout)) ?>
          </p>
          <div class="company-actions">
            <a class="btn btn-success" href="#employer-jobs">Xem việc đang tuyển</a>
            <?php if ($companyWebsite !== ''): ?>
              <a class="btn btn-outline-success" href="<?= htmlspecialchars($companyWebsite) ?>" target="_blank" rel="noopener">Ghé thăm website</a>
            <?php endif; ?>
            <?php if ($isOwner): ?>
              <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/employer/edit.php">
                <i class="fa-solid fa-pen-to-square me-2"></i>Chỉnh sửa hồ sơ
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php if ($profileUpdated): ?>
        <div class="alert alert-success mt-4 shadow-sm" role="alert">
          <i class="fa-solid fa-circle-check me-2"></i>Thông tin doanh nghiệp đã được cập nhật thành công.
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="employer-stats">
    <div class="container">
      <div class="row g-4">
        <div class="col-md-3">
          <div class="stat-card">
            <span class="stat-label">Việc đang tuyển</span>
            <span class="stat-value"><?= $totalJobs ?></span>
            <small class="text-muted">Số lượng tin tuyển dụng đang hiển thị</small>
          </div>
        </div>
        <div class="col-md-3">
          <div class="stat-card">
            <span class="stat-label">Tin đăng mới</span>
            <span class="stat-value"><?= $recentJobs ?></span>
            <small class="text-muted">Trong 30 ngày gần nhất</small>
          </div>
        </div>
        <div class="col-md-3">
          <div class="stat-card">
            <span class="stat-label">Lượt xem</span>
            <span class="stat-value"><?= number_format($totalViews) ?></span>
            <small class="text-muted">Tổng lượt xem việc làm</small>
          </div>
        </div>
        <div class="col-md-3">
          <div class="stat-card">
            <span class="stat-label">Địa điểm</span>
            <span class="stat-value"><?= count($uniqueLocations) ?></span>
            <small class="text-muted">Khu vực đang tuyển dụng</small>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="employer-content">
    <div class="container">
      <div class="profile-grid">
        <article class="profile-card">
          <h2>Về <?= htmlspecialchars($companyName) ?></h2>
          <div class="profile-description">
            <?= jf_profile_format_paragraphs($companyAbout) ?>
          </div>
        </article>

        <aside class="profile-card">
          <h2>Thông tin liên hệ</h2>
          <ul class="contact-list">
            <?php if ($companyAddress !== ''): ?>
              <li><i class="fa-solid fa-location-dot"></i><span><?= htmlspecialchars($companyAddress) ?></span></li>
            <?php endif; ?>
            <?php if ($ownerUser && !empty($ownerUser['email'])): ?>
              <li><i class="fa-solid fa-envelope"></i><span><?= htmlspecialchars($ownerUser['email']) ?></span></li>
            <?php endif; ?>
            <?php if ($ownerUser && !empty($ownerUser['phone'])): ?>
              <li><i class="fa-solid fa-phone"></i><span><?= htmlspecialchars($ownerUser['phone']) ?></span></li>
            <?php endif; ?>
            <?php if ($companyWebsite !== ''): ?>
              <li><i class="fa-solid fa-globe"></i><a href="<?= htmlspecialchars($companyWebsite) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($companyWebsite) ?></a></li>
            <?php endif; ?>
          </ul>
          <?php if ($companyMapEmbedUrl): ?>
            <div class="ratio ratio-16x9 mt-4">
              <iframe
                src="<?= htmlspecialchars($companyMapEmbedUrl) ?>"
                allowfullscreen
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                aria-label="Bản đồ vị trí doanh nghiệp"
                style="border:0;"
              ></iframe>
            </div>
            <?php if ($primaryMapAddress !== ''): ?>
              <p class="small text-muted mt-2 mb-0">
                <i class="fa-solid fa-location-dot me-2"></i><?= htmlspecialchars($primaryMapAddress) ?>
              </p>
            <?php endif; ?>
          <?php endif; ?>
          <?php if (!empty($uniqueLocations)): ?>
            <div class="mt-4">
              <h3 class="h6 text-uppercase text-muted">Đang tuyển tại</h3>
              <div class="tag-cloud mt-2">
                <?php foreach ($uniqueLocations as $location): ?>
                  <span><?= htmlspecialchars($location) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </aside>
      </div>

      <section class="employer-benefits mt-5">
        <div class="section-heading d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
          <div>
            <h2>Phúc lợi nổi bật</h2>
            <p class="text-muted mb-0">Những đãi ngộ tiêu biểu dành cho nhân viên tại <?= htmlspecialchars($companyName) ?></p>
          </div>
          <span class="badge rounded-pill bg-success bg-opacity-10 text-success">Cân bằng phúc lợi & phát triển</span>
        </div>
        <div class="row g-3">
          <?php foreach ($benefits as $benefit): ?>
            <div class="col-lg-4 col-md-6">
              <div class="benefit-card h-100">
                <div class="benefit-icon"><i class="<?= htmlspecialchars(jf_profile_benefit_icon($benefit)) ?>"></i></div>
                <p class="benefit-text mb-0"><?= htmlspecialchars($benefit) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="employer-culture mt-5">
        <div class="section-heading d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
          <div>
            <h2>Văn hóa & môi trường làm việc</h2>
            <p class="text-muted mb-0">Những điểm nổi bật giúp ứng viên hiểu hơn về <?= htmlspecialchars($companyName) ?></p>
          </div>
        </div>
        <div class="row g-4">
          <?php foreach ($cultureHighlights as $highlight): ?>
            <div class="col-lg-3 col-sm-6">
              <div class="culture-card h-100">
                <div class="culture-icon"><i class="fa-solid <?= htmlspecialchars($highlight['icon']) ?>"></i></div>
                <h3><?= htmlspecialchars($highlight['title']) ?></h3>
                <p class="mb-0 text-muted"><?= htmlspecialchars($highlight['description']) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="employer-timeline mt-5">
        <div class="section-heading d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
          <div>
            <h2>Lộ trình tuyển dụng gần đây</h2>
            <p class="text-muted mb-0">Theo dõi hoạt động tuyển dụng mới nhất của doanh nghiệp</p>
          </div>
        </div>
        <?php if (empty($hiringTimeline)): ?>
          <div class="alert alert-light border text-center mb-0">Chưa có dữ liệu tuyển dụng gần đây.</div>
        <?php else: ?>
          <ul class="timeline">
            <?php foreach ($hiringTimeline as $timelineItem): ?>
              <li class="timeline-item">
                <div class="timeline-point"></div>
                <div class="timeline-content">
                  <div class="timeline-title"><?= htmlspecialchars($timelineItem['title']) ?></div>
                  <div class="timeline-meta text-muted small">
                    <i class="fa-regular fa-calendar"></i>
                    <?= htmlspecialchars(jf_profile_format_date($timelineItem['date'])) ?>
                    <span class="mx-2">&middot;</span>
                    <i class="fa-solid fa-eye"></i>
                    <?= number_format($timelineItem['view_count']) ?> lượt xem
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <section id="employer-jobs" class="employer-jobs mt-5">
        <div class="section-heading">
          <div>
            <h2>Việc làm đang tuyển</h2>
            <p class="text-muted mb-0"><?= $totalJobs > 0 ? 'Cơ hội phù hợp từ ' . htmlspecialchars($companyName) : 'Chưa có tin tuyển dụng đang mở.' ?></p>
          </div>
          <a class="btn btn-outline-success" href="<?= BASE_URL ?>/job/share/index.php">Khám phá thêm việc làm</a>
        </div>
        
        <?php $relatedJobs = array_slice($relatedJobs, 0, 3); ?>


        <?php if ($totalJobs === 0): ?>
          <div class="alert alert-light border text-center py-5">
            <h5 class="fw-semibold mb-2">Nhà tuyển dụng chưa đăng tin tuyển dụng mới</h5>
            <p class="text-muted mb-0">Quay lại sau để khám phá các cơ hội mới nhất từ <?= htmlspecialchars($companyName) ?>.</p>
          </div>
        <?php else: ?>
          <div class="row g-4">
            <?php foreach ($jobs as $job): ?>
              <?php
                $jobId = (int)$job['id'];
                $jobTitle = $job['title'] ?? 'Tin tuyển dụng';
                $jobLocation = $job['location'] ?: 'Toàn quốc';
                $jobSalary = $job['salary'] ?: 'Thỏa thuận';
                $employmentType = $job['employment_type'] ?: 'Full-time';
                $jobDetailUrl = BASE_URL . '/job/share/view.php?id=' . $jobId;
                $jobPosted = strtotime($job['created_at'] ?? '') ?: null;
                $postedLabel = $jobPosted ? date('d/m/Y', $jobPosted) : 'Chưa xác định';
                $viewCount = (int)($job['view_count'] ?? 0);
                $jobQuantity = isset($job['quantity']) && $job['quantity'] ? (int)$job['quantity'] : null;
                $jobDeadline = $job['deadline'] ? date('d/m/Y', strtotime($job['deadline'])) : null;
                $isSaved = $canSaveJobs && in_array($jobId, $savedJobIds, true);
              ?>
              <div class="col-xl-4 col-md-6">
                <article class="job-card h-100">
                  <div class="d-flex align-items-start justify-content-between">
                    <div>
                      <h3 class="h5 mb-2"><a href="<?= htmlspecialchars($jobDetailUrl) ?>" class="text-decoration-none"><?= htmlspecialchars($jobTitle) ?></a></h3>
                      <div class="text-muted small">Đăng ngày <?= htmlspecialchars($postedLabel) ?></div>
                    </div>
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

                  <div class="job-meta">
                    <span><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($jobLocation) ?></span>
                    <span><i class="fa-solid fa-coins"></i><?= htmlspecialchars($jobSalary) ?></span>
                    <span><i class="fa-solid fa-suitcase"></i><?= htmlspecialchars($employmentType) ?></span>
                    <span><i class="fa-solid fa-eye"></i><?= number_format($viewCount) ?> lượt xem</span>
                    <span><i class="fa-solid fa-users"></i><?= $jobQuantity ? $jobQuantity . ' vị trí' : 'Không giới hạn' ?></span>
                    <span><i class="fa-solid fa-calendar-day"></i><?= $jobDeadline ? 'Hạn ' . htmlspecialchars($jobDeadline) : 'Hạn linh hoạt' ?></span>
                  </div>

                  <div class="job-footer">
                    <span class="badge bg-light text-success border border-success"><?= htmlspecialchars($employmentType) ?></span>
                    <a class="btn btn-outline-success" href="<?= htmlspecialchars($jobDetailUrl) ?>">Xem chi tiết <i class="fa-solid fa-arrow-right ms-2"></i></a>
                  </div>
                </article>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="employer-related mt-5">
        <div class="section-heading d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
          <div>
            <h2>Việc làm gợi ý cho bạn</h2>
            <p class="text-muted mb-0">Khám phá thêm cơ hội tương tự từ các nhà tuyển dụng khác</p>
          </div>
          <a class="btn btn-outline-success" href="<?= BASE_URL ?>/job/share/hot.php">Xem bảng xếp hạng việc hot</a>
        </div>

        <?php if (empty($relatedJobs)): ?>
          <div class="alert alert-light border text-center mb-0">Chưa có gợi ý việc làm phù hợp. Tiếp tục khám phá trên JobFind nhé!</div>
        <?php else: ?>
          <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
            <?php foreach ($relatedJobs as $relatedJob): ?>
              <?php
                $relatedJobId = (int)($relatedJob['id'] ?? 0);
                $relatedCompany = $relatedJob['company_name'] ?? 'Nhà tuyển dụng JobFind';
                $relatedTitle = $relatedJob['title'] ?? 'Tin tuyển dụng';
                $relatedLocation = $relatedJob['location'] ?: 'Toàn quốc';
                $relatedSalary = $relatedJob['salary'] ?: 'Thỏa thuận';
                $relatedEmployment = $relatedJob['employment_type'] ?: 'Full-time';
                $relatedViews = (int)($relatedJob['view_count'] ?? 0);
                $relatedQuantity = isset($relatedJob['quantity']) && $relatedJob['quantity'] ? (int)$relatedJob['quantity'] : null;
                $relatedDeadline = $relatedJob['deadline'] ? date('d/m/Y', strtotime($relatedJob['deadline'])) : null;
                $relatedPosted = $relatedJob['created_at'] ? date('d/m/Y', strtotime($relatedJob['created_at'])) : null;
                $relatedLogoPath = trim((string)($relatedJob['logo_path'] ?? ''));
                $relatedLogoUrl = $relatedLogoPath !== '' ? BASE_URL . '/' . ltrim($relatedLogoPath, '/') : '';
                $relatedDetailUrl = BASE_URL . '/job/share/view.php?id=' . $relatedJobId;
                $relatedSaved = $canSaveJobs && in_array($relatedJobId, $savedJobIds, true);
              ?>
              <div class="col">
                <article class="related-card h-100">
                  <div class="related-card__header">
                    <div>
                      <span class="related-card__company"><?= htmlspecialchars($relatedCompany) ?></span>
                      <h3 class="related-card__title"><a href="<?= htmlspecialchars($relatedDetailUrl) ?>" class="stretched-link text-decoration-none"><?= htmlspecialchars($relatedTitle) ?></a></h3>
                    </div>
                    <div class="related-card__actions">
                      <span class="badge bg-success bg-opacity-10 text-success"><i class="fa-solid fa-eye me-1"></i><?= number_format($relatedViews) ?></span>
                      <?php if ($canSaveJobs): ?>
                        <form action="<?= BASE_URL ?>/job/share/save.php" method="post" class="job-save-form">
                          <input type="hidden" name="job_id" value="<?= $relatedJobId ?>">
                          <input type="hidden" name="return" value="<?= htmlspecialchars($currentUri) ?>">
                          <input type="hidden" name="action" value="<?= $relatedSaved ? 'remove' : 'save' ?>">
                          <button type="submit" class="btn btn-sm btn-link p-0 text-decoration-none <?= $relatedSaved ? 'text-danger' : 'text-muted' ?>" title="<?= $relatedSaved ? 'Bỏ lưu việc làm' : 'Lưu việc làm' ?>" aria-label="<?= $relatedSaved ? 'Bỏ lưu việc làm' : 'Lưu việc làm' ?>">
                            <i class="fa-<?= $relatedSaved ? 'solid' : 'regular' ?> fa-heart fa-lg"></i>
                          </button>
                        </form>
                      <?php else: ?>
                        <a href="<?= BASE_URL ?>/account/login.php" class="btn btn-sm btn-link p-0 text-muted" title="Đăng nhập để lưu việc" aria-label="Đăng nhập để lưu việc">
                          <i class="fa-regular fa-heart fa-lg"></i>
                        </a>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="related-card__meta">
                    <span><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($relatedLocation) ?></span>
                    <span><i class="fa-regular fa-calendar"></i><?= $relatedPosted ? 'Đăng ngày ' . htmlspecialchars($relatedPosted) : 'Đang cập nhật' ?></span>
                  </div>

                  <ul class="related-card__stats">
                    <li>
                      <i class="fa-solid fa-coins text-success"></i>
                      <div>
                        <small class="text-muted">Mức lương</small>
                        <strong><?= htmlspecialchars($relatedSalary) ?></strong>
                      </div>
                    </li>
                    <li>
                      <i class="fa-solid fa-suitcase text-primary"></i>
                      <div>
                        <small class="text-muted">Hình thức</small>
                        <strong><?= htmlspecialchars($relatedEmployment) ?></strong>
                      </div>
                    </li>
                    <li>
                      <i class="fa-solid fa-users text-warning"></i>
                      <div>
                        <small class="text-muted">Số lượng</small>
                        <strong><?= $relatedQuantity ? $relatedQuantity . ' vị trí' : 'Không giới hạn' ?></strong>
                      </div>
                    </li>
                    <li>
                      <i class="fa-solid fa-calendar-day text-danger"></i>
                      <div>
                        <small class="text-muted">Hạn nộp</small>
                        <strong><?= $relatedDeadline ? htmlspecialchars($relatedDeadline) : 'Linh hoạt' ?></strong>
                      </div>
                    </li>
                  </ul>

                  <div class="related-card__footer">
                    <div class="related-card__employer">
                      <?php if ($relatedLogoUrl !== ''): ?>
                        <span class="related-card__avatar"><img src="<?= htmlspecialchars($relatedLogoUrl) ?>" alt="<?= htmlspecialchars($relatedCompany) ?>"></span>
                      <?php else: ?>
                        <span class="related-card__avatar related-card__avatar--fallback"><?= htmlspecialchars(strtoupper(substr($relatedCompany, 0, 2))) ?></span>
                      <?php endif; ?>
                      <div>
                        <small class="text-muted">Thương hiệu</small>
                        <strong><?= htmlspecialchars($relatedCompany) ?></strong>
                      </div>
                    </div>
                    <div class="related-card__cta">
                      <span class="badge bg-light text-success border border-success">
                        <?= htmlspecialchars($relatedEmployment) ?>
                      </span>
                      <a class="btn btn-success" href="<?= htmlspecialchars($relatedDetailUrl) ?>">Xem chi tiết<i class="fa-solid fa-arrow-right ms-2"></i></a>
                    </div>
                  </div>
                </article>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </section>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
