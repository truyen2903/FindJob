<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/models/Job.php';
require_once __DIR__ . '/../app/models/Employer.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/SavedJob.php';

if (!function_exists('jf_format_metric')) {
  function jf_format_metric($value) {
    $value = (int)$value;
    if ($value <= 0) {
      return '0';
    }
    if ($value >= 1000000) {
      $short = round($value / 1000000, 1);
      $short = rtrim(rtrim(number_format($short, 1, '.', ''), '0'), '.');
      return $short . 'M+';
    }
    if ($value >= 1000) {
      $short = round($value / 1000, 1);
      $short = rtrim(rtrim(number_format($short, 1, '.', ''), '0'), '.');
      return $short . 'K+';
    }
    return number_format($value) . '+';
  }
}

if (!function_exists('jf_category_icon')) {
  function jf_category_icon($name) {
    $normalized = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    $map = [
      'công nghệ' => 'fa-code',
      'developer' => 'fa-code',
      'it' => 'fa-code',
      'data' => 'fa-database',
      'kinh doanh' => 'fa-chart-line',
      'sale' => 'fa-chart-line',
      'marketing' => 'fa-bullhorn',
      'truyền thông' => 'fa-bullhorn',
      'nhân sự' => 'fa-people-group',
      'hành chính' => 'fa-briefcase',
      'thiết kế' => 'fa-palette',
      'sáng tạo' => 'fa-lightbulb',
      'kế toán' => 'fa-calculator',
      'tài chính' => 'fa-coins',
      'y tế' => 'fa-stethoscope',
      'chăm sóc sức khỏe' => 'fa-stethoscope',
      'logistics' => 'fa-truck-fast',
    ];
    foreach ($map as $keyword => $icon) {
      if (strpos($normalized, $keyword) !== false) {
        return $icon;
      }
    }
    return 'fa-briefcase';
  }
}

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
$employerModel = new Employer();
$userModel = new User();

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = (int)($_SESSION['role_id'] ?? 0);
$canSaveJobs = $userId > 0 && $userRole === 3;
$savedJobModel = $canSaveJobs ? new SavedJob() : null;
$savedJobIds = $canSaveJobs ? $savedJobModel->getSavedJobIdsForUser($userId) : [];

$stats = [
  'candidates' => $userModel->countByRole(3),
  'employers' => $employerModel->countAll(),
  'jobs' => $jobModel->countPublished()
];

$heroMetrics = [
  ['label' => 'Ứng viên tin dùng JobFind', 'value' => $stats['candidates']],
  ['label' => 'Nhà tuyển dụng đang tuyển', 'value' => $stats['employers']],
  ['label' => 'Việc làm đang mở', 'value' => $stats['jobs']],
];

$topCategories = $jobModel->getTopCategories(6);
$searchKeywords = $jobModel->getPopularKeywords(6);
$hotJobs = $jobModel->getHotJobs(4, ['within_days' => 30]);
if (empty($hotJobs)) {
  $hotJobs = $jobModel->getHotJobs(4, ['within_days' => 90]);
}
if (empty($hotJobs)) {
  $hotJobs = $jobModel->getFeaturedJobs(4);
}
$topEmployers = $employerModel->getTopEmployersByJobs(6);

if (empty($searchKeywords)) {
  $searchKeywords = array_map(static function ($cat) {
    return $cat['name'];
  }, array_slice($topCategories, 0, 4));
}
if (empty($searchKeywords)) {
  $searchKeywords = ['Marketing', 'Sales', 'Designer', 'IT'];
}

$jobShareFlash = $_SESSION['job_share_flash'] ?? null;
if ($jobShareFlash) {
  unset($_SESSION['job_share_flash']);
}

$currentUri = $_SERVER['REQUEST_URI'] ?? BASE_URL . '/index.php';
$prefilledKeyword = trim((string)($_GET['keyword'] ?? ''));
$prefilledLocation = trim((string)($_GET['location'] ?? ''));

$pageTitle = 'JobFind - Tìm việc nhanh, hồ sơ nổi bật';
$bodyClass = 'home-page';
$additionalCSS = $additionalCSS ?? [];
$additionalCSS[] = '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">';
$additionalCSS[] = '<link rel="stylesheet" href="' . ASSETS_URL . '/css/home.css?v=' . (filemtime(__DIR__ . '/assets/css/home.css') ?: time()) . '">';
$additionalScripts = $additionalScripts ?? [];
$additionalScripts[] = '<script src="' . ASSETS_URL . '/js/homepage.js" defer></script>';
require_once __DIR__ . '/includes/header.php';
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

  <section class="home-hero">
    <div class="container home-hero-grid">
      <div>
        <span class="home-eyebrow"><i class="fa-solid fa-bolt"></i> TopCV Experience</span>
        <h1 class="home-hero-title">Tìm việc nhanh 24h, chạm gần hơn công việc mơ ước.</h1>
        <p class="home-hero-subtitle">Một không gian tìm việc hiện đại cho ứng viên và nhà tuyển dụng. Tìm công việc phù hợp, xây hồ sơ nổi bật và theo dõi cơ hội mới mỗi ngày.</p>

        <div class="home-search-card">
          <form class="home-search-form" method="get" action="<?= BASE_URL ?>/job/share/index.php" data-search-url="<?= BASE_URL ?>/job/share/index.php">
            <label class="home-search-field" for="keyword">
              <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
              <input type="search" id="keyword" name="keyword" placeholder="Tên công việc, kỹ năng, công ty" value="<?= htmlspecialchars($prefilledKeyword) ?>">
            </label>
            <label class="home-search-field" for="location">
              <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
              <select id="location" name="location">
                <option value="" <?= $prefilledLocation === '' ? 'selected' : '' ?>>Toàn quốc</option>
                <option value="Hà Nội" <?= $prefilledLocation === 'Hà Nội' ? 'selected' : '' ?>>Hà Nội</option>
                <option value="TP. Hồ Chí Minh" <?= $prefilledLocation === 'TP. Hồ Chí Minh' ? 'selected' : '' ?>>TP. Hồ Chí Minh</option>
                <option value="Đà Nẵng" <?= $prefilledLocation === 'Đà Nẵng' ? 'selected' : '' ?>>Đà Nẵng</option>
                <option value="Remote" <?= $prefilledLocation === 'Remote' ? 'selected' : '' ?>>Remote</option>
              </select>
            </label>
            <button class="home-search-submit" type="submit">Tìm kiếm</button>
          </form>
          <div class="home-search-tags">
            <span>Từ khóa nổi bật:</span>
            <?php foreach (array_slice($searchKeywords, 0, 4) as $keyword): ?>
              <a href="<?= BASE_URL ?>/job/share/index.php?keyword=<?= urlencode($keyword) ?>">#<?= htmlspecialchars($keyword) ?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="hero-metrics">
          <?php foreach ($heroMetrics as $metric): ?>
            <div class="hero-metric-card" data-metric>
              <div class="hero-metric-value" data-value="<?= (int)$metric['value'] ?>" data-format="<?= htmlspecialchars(jf_format_metric($metric['value'])) ?>">0</div>
              <p class="hero-metric-label mb-0"><?= htmlspecialchars($metric['label']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="home-visual-stack" aria-hidden="true">
        <div class="home-dashboard-card">
          <div class="home-dashboard-top">
            <div class="home-dashboard-title">
              <strong>Việc làm phù hợp hôm nay</strong>
              <span>Dựa trên kỹ năng và vị trí của bạn</span>
            </div>
            <span class="home-live-badge">Live</span>
          </div>
          <div class="home-job-preview">
            <?php if (!empty($hotJobs)): ?>
              <?php foreach (array_slice($hotJobs, 0, 3) as $job): ?>
                <?php
                  $companyName = $job['company_name'] ?? 'JobFind';
                  $title = $job['title'] ?? 'Tin tuyển dụng';
                  $location = $job['location'] ?: 'Toàn quốc';
                  $salary = $job['salary'] ?: 'Thỏa thuận';
                ?>
                <div class="home-job-row">
                  <div class="logo-tile"><?= htmlspecialchars(jf_initials($companyName)) ?></div>
                  <div><b><?= htmlspecialchars($title) ?></b><span><?= htmlspecialchars($location) ?></span></div>
                  <small><?= htmlspecialchars($salary) ?></small>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="home-job-row"><div class="logo-tile">JF</div><div><b>Backend Developer</b><span>Hybrid · Hà Nội</span></div><small>20-30M</small></div>
              <div class="home-job-row"><div class="logo-tile">DA</div><div><b>Data Analyst</b><span>Remote · SQL</span></div><small>15-24M</small></div>
              <div class="home-job-row"><div class="logo-tile">UX</div><div><b>UI/UX Designer</b><span>Figma · Product</span></div><small>18-28M</small></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="home-floating-card">
          <span>Gợi ý thông minh</span>
          <b>CV khớp 82%</b>
          <p>Hệ thống đề xuất công việc dựa trên kỹ năng, địa điểm và mức lương mong muốn.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="home-benefits">
    <div class="container">
      <div class="benefit-grid">
        <article class="benefit-card home-glass fade-in-element">
          <div class="icon-box"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
          <h3>Gợi ý việc làm thông minh</h3>
          <p>Thuật toán đề xuất giúp ứng viên tìm thấy cơ hội phù hợp nhanh hơn.</p>
        </article>
        <article class="benefit-card home-glass fade-in-element">
          <div class="icon-box"><i class="fa-regular fa-file-lines"></i></div>
          <h3>Mẫu CV chuyên nghiệp</h3>
          <p>Tạo hồ sơ nổi bật, dễ đọc và sẵn sàng gửi đến nhà tuyển dụng.</p>
        </article>
        <article class="benefit-card home-glass fade-in-element">
          <div class="icon-box"><i class="fa-regular fa-building"></i></div>
          <h3>Doanh nghiệp uy tín</h3>
          <p>Kết nối với các công ty đã xác thực, thông tin rõ ràng và minh bạch.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="home-section home-section--categories" id="career">
    <div class="container">
      <div class="home-section-head">
        <div>
          <span class="section-kicker">Khám phá lĩnh vực</span>
          <h2>Ngành nghề nổi bật</h2>
          <p>Chọn nhóm ngành phù hợp để xem nhanh các cơ hội đang tuyển.</p>
        </div>
        <a class="see-all" href="<?= BASE_URL ?>/job/share/index.php?filter=featured">Xem tất cả ngành <i class="fa-solid fa-arrow-right ms-1"></i></a>
      </div>

      <?php if (!empty($topCategories)): ?>
        <div class="category-grid">
          <?php foreach ($topCategories as $cat): ?>
            <a class="category-card home-glass fade-in-element" href="<?= BASE_URL ?>/job/share/index.php?category=<?= (int)($cat['id'] ?? 0) ?>">
              <div class="icon-box"><i class="fa-solid <?= jf_category_icon($cat['name']) ?>"></i></div>
              <h3><?= htmlspecialchars($cat['name']) ?></h3>
              <p><?= number_format((int)$cat['job_count']) ?> việc làm</p>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="alert alert-light border text-center mb-0">Chưa có dữ liệu ngành nghề. Hãy thêm danh mục và việc làm để khởi động!</div>
      <?php endif; ?>
    </div>
  </section>

  <section class="home-section home-section--jobs" id="jobs">
    <div class="container">
      <div class="home-section-head">
        <div>
          <span class="section-kicker">Cơ hội mới nhất</span>
          <h2>Việc làm hot</h2>
          <p>Các vị trí được cập nhật gần đây và có mức độ phù hợp cao.</p>
        </div>
        <a class="see-all" href="<?= BASE_URL ?>/job/share/index.php">Xem thêm việc làm <i class="fa-solid fa-arrow-right ms-1"></i></a>
      </div>

      <div class="job-layout">
        <div class="job-grid">
          <?php if (!empty($hotJobs)): ?>
            <?php foreach ($hotJobs as $job): ?>
              <?php
                $jobId = (int)($job['id'] ?? 0);
                $companyName = $job['company_name'] ?? 'Nhà tuyển dụng JobFind';
                $title = $job['title'] ?? 'Tin tuyển dụng';
                $location = $job['location'] ?: 'Toàn quốc';
                $salary = $job['salary'] ?: 'Thỏa thuận';
                $employmentType = $job['employment_type'] ?: 'Toàn thời gian';
                $viewCount = isset($job['view_count']) ? (int)$job['view_count'] : 0;
                $jobDetailUrl = BASE_URL . '/job/share/view.php?id=' . $jobId;
                $isSaved = $canSaveJobs && in_array($jobId, $savedJobIds, true);
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
                <p>Khám phá mô tả chi tiết, yêu cầu công việc và ứng tuyển trực tiếp trên JobFind.</p>
                <div class="job-meta">
                  <span class="job-pill"><i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($location) ?></span>
                  <span class="job-pill"><i class="fa-solid fa-coins me-1"></i><?= htmlspecialchars($salary) ?></span>
                  <span class="job-pill"><?= htmlspecialchars($employmentType) ?></span>
                </div>
                <div class="job-card-bottom">
                  <span><i class="fa-regular fa-eye me-1"></i><?= number_format($viewCount) ?> lượt xem</span>
                  <a class="apply-link" href="<?= htmlspecialchars($jobDetailUrl) ?>">Xem chi tiết <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
              </article>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="alert alert-light border text-center mb-0">Chưa có việc làm nào được đăng. Nhà tuyển dụng hãy tạo tin tuyển dụng đầu tiên ngay!</div>
          <?php endif; ?>
        </div>

        <aside class="cv-side-panel home-glass" id="cv">
          <h3>Nâng cấp hồ sơ trong 5 phút</h3>
          <p>Tải CV hoặc tạo CV mới để JobFind gợi ý công việc phù hợp hơn.</p>
          <div class="upload-box"><i class="fa-regular fa-file-pdf me-2"></i>Hồ sơ chuyên nghiệp giúp bạn nổi bật hơn</div>
          <a class="home-primary-btn w-100" href="<?= BASE_URL ?>/candidate/profile.php">Tạo CV ngay</a>
        </aside>
      </div>
    </div>
  </section>

  <section class="company-band" id="companies">
    <div class="container company-strip">
      <div>
        <span class="section-kicker">Đối tác chiến lược</span>
        <h2>Những thương hiệu đồng hành cùng JobFind</h2>
        <p>Tăng độ tin cậy cho nền tảng bằng khu vực công ty nổi bật và nhóm nhà tuyển dụng đang hoạt động.</p>
      </div>
      <?php if (!empty($topEmployers)): ?>
        <div class="company-logos">
          <?php foreach ($topEmployers as $employer): ?>
            <?php
              $companyName = trim($employer['company_name'] ?? '') ?: 'Nhà tuyển dụng JobFind';
              $jobCount = (int)($employer['job_count'] ?? 0);
            ?>
            <article class="jf-brand-card home-glass fade-in-element">
              <div>
                <h3 class="brand-name" title="<?= htmlspecialchars($companyName) ?>"><?= htmlspecialchars($companyName) ?></h3>
                <div class="brand-stats"><i class="fa-solid fa-briefcase"></i><span><?= number_format($jobCount) ?> việc làm</span></div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="alert alert-light border text-center mb-0">Chưa có dữ liệu nhà tuyển dụng nổi bật.</div>
      <?php endif; ?>
    </div>
  </section>

  <section class="home-section home-section--articles">
    <div class="container">
      <div class="home-section-head">
        <div>
          <span class="section-kicker">Cẩm nang nghề nghiệp</span>
          <h2>Bài viết nên đọc</h2>
          <p>Gợi ý CV, phỏng vấn và kỹ năng phát triển nghề nghiệp.</p>
        </div>
        <a class="see-all" href="<?= BASE_URL ?>/index.php#career">Xem tất cả bài viết <i class="fa-solid fa-arrow-right ms-1"></i></a>
      </div>
      <div class="article-grid">
        <?php
        $articles = [
          ['title' => '5 bí kíp nâng cấp CV khiến nhà tuyển dụng chú ý', 'category' => 'CV & Hồ sơ', 'time' => '5 phút đọc', 'copy' => 'Những chỉnh sửa nhỏ giúp hồ sơ rõ ràng, chuyên nghiệp và dễ đọc hơn.'],
          ['title' => 'Checklist trước buổi phỏng vấn đầu tiên', 'category' => 'Phỏng vấn', 'time' => '7 phút đọc', 'copy' => 'Chuẩn bị thông tin công ty, câu hỏi thường gặp và cách trình bày kinh nghiệm.'],
          ['title' => 'Kỹ năng phân tích dữ liệu cho marketer thời 4.0', 'category' => 'Kỹ năng', 'time' => '6 phút đọc', 'copy' => 'Cách dùng dữ liệu để đọc hành vi khách hàng và tối ưu chiến dịch.'],
        ];
        foreach ($articles as $article): ?>
          <a class="article-card home-glass fade-in-element" href="<?= BASE_URL ?>/index.php#career">
            <span class="article-tag"><?= htmlspecialchars($article['category']) ?></span>
            <h3><?= htmlspecialchars($article['title']) ?></h3>
            <p><?= htmlspecialchars($article['copy']) ?></p>
            <span class="read-time"><i class="fa-regular fa-clock me-1"></i><?= htmlspecialchars($article['time']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
