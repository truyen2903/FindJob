<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Candidate.php';
require_once dirname(__DIR__, 2) . '/app/models/Job.php';

$candidateModel = new Candidate();
$jobModel = new Job();

$sessionUserId = $_SESSION['user_id'] ?? null;
$sessionRoleId = $_SESSION['role_id'] ?? null;
$profile = null;

$userParam = isset($_GET['user']) ? (int)$_GET['user'] : null;
$candidateParam = isset($_GET['candidate']) ? (int)$_GET['candidate'] : null;

if ($userParam) {
    $profile = $candidateModel->getProfileByUserId($userParam);
}

if (!$profile && $candidateParam) {
    $profile = $candidateModel->getProfileByCandidateId($candidateParam);
}

if (!$profile && $sessionUserId && (int)$sessionRoleId === 3) {
    $profile = $candidateModel->getProfileByUserId((int)$sessionUserId);
}

if (!$profile) {
    $list = $candidateModel->listCandidates();
    if ($list && ($row = $list->fetch_assoc())) {
        $profile = $candidateModel->getProfileByUserId((int)$row['user_id']);
    }
}

if (!$profile) {
    $profile = [
        'user_id' => 0,
        'full_name' => 'Ứng viên JobFind',
        'email' => 'candidate@example.com',
        'phone' => '0123 456 789',
        'headline' => 'Chuyên viên Marketing Digital',
        'summary' => 'Ứng viên năng động với hơn 3 năm kinh nghiệm xây dựng chiến lược digital marketing đa kênh, tối ưu hiệu suất quảng cáo và tăng trưởng thương hiệu.',
        'location' => 'Hà Nội, Việt Nam',
        'skills' => json_encode(['Digital Marketing', 'Facebook Ads', 'Google Analytics', 'Content Strategy', 'SEO']),
        'experience' => json_encode([
            [
                'title' => 'Digital Marketing Executive',
                'company' => 'TopGrow Agency',
                'start' => '2021-06-01',
                'end' => null,
                'description' => 'Quản lý ngân sách quảng cáo 200 triệu/tháng, tối ưu tỷ lệ chuyển đổi và triển khai chiến dịch tăng trưởng khách hàng mới.'
            ],
            [
                'title' => 'Content Marketing Specialist',
                'company' => 'Creative Hub',
                'start' => '2019-08-01',
                'end' => '2021-05-01',
                'description' => 'Phụ trách chiến lược nội dung đa nền tảng, đạt mức tăng 45% lượng khách hàng tiềm năng sau 6 tháng.'
            ],
        ]),
        'avatar_path' => null,
        'profile_picture' => null,
        'cv_path' => null,
        'created_at' => date('Y-m-d H:i:s'),
    ];
}

if (!function_exists('jf_profile_initial')) {
    function jf_profile_initial($name)
    {
        if (!$name) {
            return 'J';
        }
        if (function_exists('mb_substr')) {
            return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
        }
        return strtoupper(substr($name, 0, 1));
    }
}

if (!function_exists('jf_profile_format_date')) {
    function jf_profile_format_date(?string $date)
    {
        if (empty($date)) {
            return 'Hiện tại';
        }
        $ts = strtotime($date);
        if (!$ts) {
            return $date;
        }
        return date('m/Y', $ts);
    }
}

if (!function_exists('jf_profile_calculate_years')) {
    function jf_profile_calculate_years(array $experience)
    {
        $totalMonths = 0;
        foreach ($experience as $item) {
            $start = isset($item['start']) ? strtotime($item['start']) : false;
            $end = isset($item['end']) && $item['end'] ? strtotime($item['end']) : time();
            if ($start) {
                if (!$end || $end < $start) {
                    $end = time();
                }
                $diffMonths = (int)round(($end - $start) / (30 * 24 * 60 * 60));
                if ($diffMonths > 0) {
                    $totalMonths += $diffMonths;
                }
            }
        }
        $years = $totalMonths / 12;
        if ($years <= 0) {
            return 'Dưới 1 năm';
        }
        if ($years < 1.5) {
            return '1 năm+';
        }
        if ($years < 10) {
            return number_format($years, 1) . ' năm';
        }
        return number_format(round($years)) . ' năm';
    }
}

$fullName = $profile['full_name'] ?: 'Ứng viên chưa cập nhật';
$headline = $profile['headline'] ?: 'Ứng viên đang tìm kiếm cơ hội mới';
$summary = $profile['summary'] ?: 'Ứng viên chưa cập nhật phần giới thiệu. Hãy mô tả ngắn gọn mục tiêu nghề nghiệp, kinh nghiệm nổi bật và thành tích của bạn để gây ấn tượng với nhà tuyển dụng.';
$location = $profile['location'] ?: 'Chưa cập nhật địa điểm';
$email = $profile['email'] ?? 'contact@jobfind.vn';
$phone = $profile['phone'] ?? 'Chưa cập nhật';

$skillsList = [];
if (!empty($profile['skills'])) {
    $decodedSkills = json_decode($profile['skills'], true);
    if (is_array($decodedSkills)) {
        $skillsList = array_filter(array_map('trim', $decodedSkills));
    }
}
if (empty($skillsList)) {
    $skillsList = ['Communication', 'Project Management', 'Leadership', 'English', 'Problem Solving'];
}

$experienceList = [];
if (!empty($profile['experience'])) {
    $decodedExperience = json_decode($profile['experience'], true);
    if (is_array($decodedExperience)) {
        $experienceList = $decodedExperience;
    }
}
if (empty($experienceList)) {
    $experienceList = [
        [
            'title' => 'Business Analyst',
            'company' => 'JobFind JSC',
            'start' => '2022-01-01',
            'end' => null,
            'description' => 'Phân tích yêu cầu nghiệp vụ, phối hợp với đội kỹ thuật xây dựng sản phẩm tuyển dụng thông minh.'
        ],
        [
            'title' => 'Intern Analyst',
            'company' => 'Talent Lab',
            'start' => '2020-06-01',
            'end' => '2021-12-01',
            'description' => 'Hỗ trợ xây dựng báo cáo dữ liệu nhân sự, tối ưu quy trình tuyển dụng và trải nghiệm ứng viên.'
        ],
    ];
}

$experienceYearsText = jf_profile_calculate_years($experienceList);
$skillCount = count($skillsList);
$latestUpdate = !empty($profile['created_at']) ? date('d/m/Y', strtotime($profile['created_at'])) : date('d/m/Y');

$avatarPath = $profile['profile_picture'] ?: ($profile['avatar_path'] ?? null);
$avatarUrl = '';
if (!empty($avatarPath)) {
    $avatarUrl = BASE_URL . '/' . ltrim($avatarPath, '/');
}
$profileInitial = jf_profile_initial($fullName);

$resumeUrl = '';
if (!empty($profile['cv_path'])) {
    $resumeUrl = BASE_URL . '/' . ltrim($profile['cv_path'], '/');
}

$cvStatus = $_GET['cv'] ?? null;
$cvAlert = null;
if ($cvStatus === 'uploaded') {
  $cvAlert = [
    'type' => 'success',
    'message' => 'CV của bạn đã được cập nhật thành công. Nhà tuyển dụng có thể tải xuống phiên bản mới nhất.'
  ];
} elseif ($cvStatus === 'failed') {
  $cvAlert = [
    'type' => 'danger',
    'message' => 'Không thể cập nhật CV. Vui lòng thử lại hoặc liên hệ đội ngũ hỗ trợ JobFind.'
  ];
}

$profileUpdated = isset($_GET['updated']);

$highlightStats = [
    [
        'title' => 'Kinh nghiệm',
        'value' => $experienceYearsText,
        'caption' => 'Tổng thời gian làm việc thực tế'
    ],
    [
        'title' => 'Kỹ năng chủ chốt',
        'value' => (string)$skillCount,
        'caption' => 'Kỹ năng đã được ứng viên cập nhật'
    ],
    [
        'title' => 'Cập nhật gần nhất',
        'value' => $latestUpdate,
        'caption' => 'Hồ sơ luôn được làm mới'
    ],
    [
        'title' => 'Trạng thái',
        'value' => 'Sẵn sàng',
        'caption' => 'Ứng viên mở với cơ hội phỏng vấn'
    ],
];

$featuredJobs = $jobModel->getFeaturedJobs(3);

$pageTitle = $fullName . ' | Hồ sơ ứng viên JobFind';
$bodyClass = 'candidate-page';
$additionalCSS = $additionalCSS ?? [];
$additionalCSS[] = '<link rel="stylesheet" href="' . ASSETS_URL . '/css/candidate-profile.css">';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<main class="candidate-page">
  <section class="candidate-hero">
    <div class="container">
      <div class="candidate-card">
        <div class="candidate-avatar">
          <?php if (!empty($avatarUrl)): ?>
            <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($fullName) ?>">
          <?php else: ?>
            <span><?= htmlspecialchars($profileInitial) ?></span>
          <?php endif; ?>
        </div>
        <div class="candidate-meta flex-grow-1">
          <h1><?= htmlspecialchars($fullName) ?></h1>
          <div class="headline"><i class="fa-solid fa-wand-magic-sparkles me-2"></i><?= htmlspecialchars($headline) ?></div>
          <div class="meta-list">
            <span><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($location) ?></span>
            <span><i class="fa-regular fa-envelope"></i><?= htmlspecialchars($email) ?></span>
            <?php if (!empty($phone) && strtolower($phone) !== 'chưa cập nhật'): ?>
              <span><i class="fa-solid fa-phone"></i><?= htmlspecialchars($phone) ?></span>
            <?php endif; ?>
          </div>
          <div class="candidate-actions">
            <?php if (!empty($resumeUrl)): ?>
              <a class="btn btn-success" href="<?= htmlspecialchars($resumeUrl) ?>" target="_blank" rel="noopener">
                <i class="fa-solid fa-download me-2"></i>Tải CV
              </a>
            <?php else: ?>
              <a class="btn btn-outline-success" href="<?= BASE_URL ?>/candidate/upload_cv.php">
                <i class="fa-solid fa-file-circle-plus me-2"></i>Thêm CV của bạn
              </a>
            <?php endif; ?>
            <a class="btn btn-outline-success" href="mailto:<?= htmlspecialchars($email) ?>">
              <i class="fa-solid fa-envelope-open-text me-2"></i>Liên hệ ứng viên
            </a>
            <button class="btn btn-light border" type="button" onclick="navigator.clipboard?.writeText('<?= htmlspecialchars($email) ?>').catch(() => {});">
              <i class="fa-solid fa-share-nodes me-2"></i>Chia sẻ hồ sơ
            </button>
            <?php if ($sessionUserId && (int)$sessionRoleId === 3 && (int)$profile['user_id'] === (int)$sessionUserId): ?>
              <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/candidate/edit_profile.php">
                <i class="fa-solid fa-pen-to-square me-2"></i>Chỉnh sửa hồ sơ
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php if ($profileUpdated): ?>
        <div class="alert alert-success mt-4 shadow-sm" role="alert">
          <i class="fa-solid fa-circle-check me-2"></i>Hồ sơ của bạn đã được cập nhật. Kiểm tra lại để đảm bảo mọi thông tin chính xác.
        </div>
      <?php endif; ?>
      <?php if ($cvAlert): ?>
        <div class="alert alert-<?= htmlspecialchars($cvAlert['type']) ?> mt-4 shadow-sm" role="alert">
          <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($cvAlert['message']) ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="candidate-section">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-8">
          <article class="section-card">
            <h2>Giới thiệu</h2>
            <p><?= nl2br(htmlspecialchars($summary)) ?></p>
            <div class="highlight-grid mt-4">
              <?php foreach ($highlightStats as $stat): ?>
                <div class="highlight-card">
                  <h4><?= htmlspecialchars($stat['title']) ?></h4>
                  <span><?= htmlspecialchars($stat['value']) ?></span>
                  <p class="mt-2 mb-0 text-muted small"><?= htmlspecialchars($stat['caption']) ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          </article>
        </div>
        <div class="col-lg-4">
          <aside class="section-card">
            <h2>Thông tin nhanh</h2>
            <ul class="list-unstyled text-muted mb-0">
              <li class="mb-3"><i class="fa-solid fa-circle-check text-success me-2"></i>Hồ sơ được xác thực bởi JobFind</li>
              <li class="mb-3"><i class="fa-solid fa-rocket text-success me-2"></i>Sẵn sàng nhận việc trong 2 tuần</li>
              <li class="mb-3"><i class="fa-solid fa-language text-success me-2"></i>Tiếng Anh giao tiếp tốt</li>
              <li><i class="fa-solid fa-user-shield text-success me-2"></i>Ưu tiên cơ hội Hybrid/Remote</li>
            </ul>
          </aside>
        </div>
      </div>
    </div>
  </section>

  <section class="candidate-section" id="experience">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-8">
          <article class="section-card">
            <h2>Kinh nghiệm làm việc</h2>
            <div class="timeline">
              <?php foreach ($experienceList as $item): ?>
                <?php
                  $title = $item['title'] ?? 'Vị trí chưa cập nhật';
                  $company = $item['company'] ?? 'Doanh nghiệp';
                  $start = jf_profile_format_date($item['start'] ?? null);
                  $end = jf_profile_format_date($item['end'] ?? null);
                  $description = trim((string)($item['description'] ?? '')); 
                ?>
                <div class="timeline-item">
                  <div class="timeline-marker"></div>
                  <h3><?= htmlspecialchars($title) ?></h3>
                  <div class="meta"><?= htmlspecialchars($company) ?> &middot; <?= htmlspecialchars($start) ?> - <?= htmlspecialchars($end) ?></div>
                  <?php if ($description !== ''): ?>
                    <p><?= nl2br(htmlspecialchars($description)) ?></p>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </article>
        </div>
        <div class="col-lg-4">
          <aside class="section-card" id="skills">
            <h2>Kỹ năng chuyên môn</h2>
            <div class="skill-cloud">
              <?php foreach ($skillsList as $skill): ?>
                <span class="skill-chip"><i class="fa-solid fa-bolt"></i><?= htmlspecialchars($skill) ?></span>
              <?php endforeach; ?>
            </div>
            <div class="mt-4">
              <span class="badge bg-success-subtle text-success fw-semibold badge-pill px-3 py-2">Liên tục cập nhật kỹ năng mới</span>
            </div>
          </aside>
        </div>
      </div>
    </div>
  </section>

  <section class="candidate-section" id="resume">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-6">
          <article class="section-card">
            <h2>CV &amp; tài liệu</h2>
            <div class="resume-card">
              <div class="resume-meta">
                <div class="resume-icon"><i class="fa-regular fa-file-lines"></i></div>
                <div>
                  <h3 class="h6 mb-1">CV chính thức</h3>
                  <p class="text-muted mb-0">Định dạng chuẩn ATS, tối ưu cho nhà tuyển dụng</p>
                </div>
              </div>
              <?php if (!empty($resumeUrl)): ?>
                <a class="btn btn-success" href="<?= htmlspecialchars($resumeUrl) ?>" target="_blank" rel="noopener">Tải xuống</a>
              <?php else: ?>
                <span class="text-muted">Ứng viên chưa tải CV</span>
              <?php endif; ?>
            </div>
            <div class="mt-4">
              <p class="text-muted mb-2"><i class="fa-regular fa-lightbulb me-2"></i>Tip: Thêm link portfolio hoặc dự án nổi bật để tăng ấn tượng.</p>
              <a class="btn btn-outline-success" href="<?= BASE_URL ?>/candidate/upload_cv.php">Cập nhật CV mới</a>
            </div>
          </article>
        </div>
        <div class="col-lg-6">
          <article class="section-card">
            <h2>Thành tựu nổi bật</h2>
            <ul class="list-unstyled text-muted mb-0">
              <li class="mb-3"><i class="fa-solid fa-medal text-warning me-2"></i>Đạt giải Nhân viên xuất sắc quý I/2024 tại doanh nghiệp hiện tại.</li>
              <li class="mb-3"><i class="fa-solid fa-chart-line text-success me-2"></i>Đóng góp tăng trưởng 150% traffic tự nhiên trong 6 tháng.</li>
              <li><i class="fa-solid fa-people-group text-primary me-2"></i>Lãnh đạo nhóm 5 thành viên triển khai chiến dịch digital đa kênh.</li>
            </ul>
          </article>
        </div>
      </div>
    </div>
  </section>

  <section class="candidate-section bg-white">
    <div class="container">
      <div class="section-card">
        <h2>Việc làm gợi ý cho <?= htmlspecialchars($fullName) ?></h2>
        <?php if (!empty($featuredJobs)): ?>
          <div class="row row-cols-1 row-cols-md-3 g-4 mt-2">
            <?php foreach ($featuredJobs as $job): ?>
              <div class="col">
                <div class="highlight-card h-100">
                  <h4><?= htmlspecialchars($job['title']) ?></h4>
                  <p class="text-muted mb-2"><i class="fa-solid fa-building me-2"></i><?= htmlspecialchars($job['company_name'] ?? 'Doanh nghiệp ẩn danh') ?></p>
                  <?php if (!empty($job['location'])): ?>
                    <p class="text-muted mb-2"><i class="fa-solid fa-location-dot me-2"></i><?= htmlspecialchars($job['location']) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($job['salary'])): ?>
                    <p class="text-success fw-semibold"><i class="fa-solid fa-coins me-2"></i><?= htmlspecialchars($job['salary']) ?></p>
                  <?php endif; ?>
                  <a class="btn btn-outline-success w-100 mt-3" href="<?= BASE_URL ?>/jobs.php?id=<?= (int)$job['id'] ?>">Xem chi tiết</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-muted">Chúng tôi đang cập nhật thêm việc làm phù hợp. Hãy quay lại sau hoặc khám phá danh sách việc làm mới nhất.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
