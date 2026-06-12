<?php
require_once dirname(__DIR__, 3) . '/config/config.php';
require_once dirname(__DIR__, 3) . '/app/models/Employer.php';
require_once dirname(__DIR__, 3) . '/app/models/Job.php';
require_once dirname(__DIR__, 3) . '/app/models/Application.php';

if (empty($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 2) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$employerModel = new Employer();
$jobModel = new Job();
$applicationModel = new Application();

$employer = $employerModel->getByUserId($userId);
if (!$employer) {
    header('Location: ' . BASE_URL . '/employer/edit.php');
    exit;
}

$employerId = (int)$employer['id'];
$employerCompanyName = $employer['company_name'] ?? 'Nhà tuyển dụng JobFind';
$employerProfileUrl = BASE_URL . '/employer/show.php?id=' . $employerId;
$_SESSION['employer_company_name'] = $employerCompanyName;
$_SESSION['employer_profile_url'] = $employerProfileUrl;

function ea_dashboard_date(?string $date): string
{
    $timestamp = $date ? strtotime($date) : false;
    return $timestamp ? date('d/m/Y', $timestamp) : 'Chưa cập nhật';
}

function ea_dashboard_initial(string $name): string
{
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return strtoupper(substr($name, 0, 1));
}

$jobs = [];
$jobStatusCounts = [
    'published' => 0,
    'draft' => 0,
    'closed' => 0,
];

$jobsResult = $jobModel->getByEmployer($employerId);
if ($jobsResult) {
    while ($job = $jobsResult->fetch_assoc()) {
        $jobs[] = $job;
        $status = $job['status'] ?? 'draft';
        if (isset($jobStatusCounts[$status])) {
            $jobStatusCounts[$status]++;
        }
    }
    $jobsResult->free();
}

$totalJobs = count($jobs);
$latestJobs = array_slice($jobs, 0, 4);

$applicationStats = [
    'total' => 0,
    'last7_days' => 0,
    'awaiting_review' => 0,
    'shortlisted' => 0,
    'hired' => 0,
];
$statsSql = "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN a.applied_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS last7_days,
        SUM(CASE WHEN a.status IN ('applied', 'viewed') THEN 1 ELSE 0 END) AS awaiting_review,
        SUM(CASE WHEN a.status = 'shortlisted' THEN 1 ELSE 0 END) AS shortlisted,
        SUM(CASE WHEN a.status = 'hired' THEN 1 ELSE 0 END) AS hired
    FROM applications a
    INNER JOIN jobs j ON j.id = a.job_id
    WHERE j.employer_id = ? AND a.status != 'withdrawn'";
$statsStmt = $jobModel->conn->prepare($statsSql);
if ($statsStmt !== false) {
    $statsStmt->bind_param('i', $employerId);
    if ($statsStmt->execute()) {
        $statsResult = $statsStmt->get_result();
        if ($statsResult && ($row = $statsResult->fetch_assoc())) {
            foreach ($applicationStats as $key => $value) {
                $applicationStats[$key] = (int)($row[$key] ?? 0);
            }
        }
        if ($statsResult) {
            $statsResult->free();
        }
    }
    $statsStmt->close();
}

$recentApplicants = [];
$recentSql = "SELECT
        a.id,
        a.status,
        a.applied_at,
        j.title,
        u.email,
        u.name AS candidate_name
    FROM applications a
    INNER JOIN jobs j ON j.id = a.job_id
    INNER JOIN candidates c ON c.id = a.candidate_id
    INNER JOIN users u ON u.id = c.user_id
    WHERE j.employer_id = ? AND a.status != 'withdrawn'
    ORDER BY a.applied_at DESC
    LIMIT 6";
$recentStmt = $jobModel->conn->prepare($recentSql);
if ($recentStmt !== false) {
    $recentStmt->bind_param('i', $employerId);
    if ($recentStmt->execute()) {
        $recentResult = $recentStmt->get_result();
        if ($recentResult) {
            while ($row = $recentResult->fetch_assoc()) {
                $recentApplicants[] = $row;
            }
            $recentResult->free();
        }
    }
    $recentStmt->close();
}

$statusLabels = $applicationModel->getStatusLabels();
$reviewRate = $applicationStats['total'] > 0
    ? (int)round((($applicationStats['shortlisted'] + $applicationStats['hired']) / $applicationStats['total']) * 100)
    : 0;
$publishedRate = $totalJobs > 0 ? (int)round(($jobStatusCounts['published'] / $totalJobs) * 100) : 0;

$pageTitle = 'Dashboard nhà tuyển dụng';
$employerNavActive = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<section class="ea-dashboard-hero">
  <div class="ea-dashboard-hero__copy">
    <span class="ea-eyebrow">Tổng quan tuyển dụng</span>
    <h2><?= htmlspecialchars($employerCompanyName) ?></h2>
    <p>Theo dõi tin đăng, hồ sơ mới và các bước cần xử lý trong một màn hình gọn hơn.</p>
  </div>
  <div class="ea-dashboard-hero__actions">
    <a class="btn btn-light" href="<?= BASE_URL ?>/employer/edit.php">Sửa hồ sơ</a>
    <a class="btn btn-success" href="<?= BASE_URL ?>/employer/admin/job_edit.php">Đăng tin mới</a>
  </div>
</section>

<section class="ea-metric-grid">
  <article class="ea-metric-card">
    <span>Tin đang hiển thị</span>
    <strong><?= number_format($jobStatusCounts['published']) ?></strong>
    <p><?= $publishedRate ?>% trong tổng số <?= number_format($totalJobs) ?> tin</p>
  </article>
  <article class="ea-metric-card">
    <span>Hồ sơ cần xem</span>
    <strong><?= number_format($applicationStats['awaiting_review']) ?></strong>
    <p><?= number_format($applicationStats['last7_days']) ?> hồ sơ mới trong 7 ngày</p>
  </article>
  <article class="ea-metric-card">
    <span>Đã shortlist</span>
    <strong><?= number_format($applicationStats['shortlisted']) ?></strong>
    <p><?= $reviewRate ?>% hồ sơ vào vòng tiếp theo</p>
  </article>
  <article class="ea-metric-card">
    <span>Đã tuyển</span>
    <strong><?= number_format($applicationStats['hired']) ?></strong>
    <p>Từ <?= number_format($applicationStats['total']) ?> hồ sơ ứng tuyển</p>
  </article>
</section>

<section class="ea-dashboard-grid">
  <article class="ea-panel ea-panel--large">
    <div class="ea-panel__head">
      <div>
        <span class="ea-eyebrow">Hồ sơ ứng viên</span>
        <h3>Ứng viên mới nhất</h3>
      </div>
      <a class="ea-text-link" href="<?= BASE_URL ?>/employer/admin/applications.php">Xem tất cả</a>
    </div>

    <?php if (empty($recentApplicants)): ?>
      <div class="ea-empty-state">
        <strong>Chưa có hồ sơ ứng tuyển.</strong>
        <p>Đăng hoặc làm mới tin tuyển dụng để bắt đầu nhận hồ sơ.</p>
      </div>
    <?php else: ?>
      <div class="ea-applicant-list">
        <?php foreach ($recentApplicants as $app): ?>
          <?php
            $status = $app['status'] ?? 'applied';
            $candidateName = trim((string)($app['candidate_name'] ?? '')) ?: ($app['email'] ?? 'Ứng viên');
          ?>
          <a class="ea-applicant-row" href="<?= BASE_URL ?>/employer/admin/application_view.php?id=<?= (int)$app['id'] ?>">
            <div class="ea-applicant-avatar"><?= htmlspecialchars(ea_dashboard_initial($candidateName)) ?></div>
            <div class="ea-applicant-main">
              <strong><?= htmlspecialchars($candidateName) ?></strong>
              <span><?= htmlspecialchars($app['title'] ?? 'Tin tuyển dụng') ?></span>
            </div>
            <div class="ea-applicant-meta">
              <span class="badge-status <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?></span>
              <small><?= htmlspecialchars(ea_dashboard_date($app['applied_at'] ?? null)) ?></small>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>

  <aside class="ea-panel">
    <div class="ea-panel__head ea-panel__head--compact">
      <div>
        <span class="ea-eyebrow">Trạng thái tin</span>
        <h3>Pipeline</h3>
      </div>
    </div>
    <div class="ea-pipeline">
      <div class="ea-pipeline__bar" style="--published: <?= $publishedRate ?>%;"></div>
      <div class="ea-pipeline__item">
        <span>Đang đăng</span>
        <strong><?= number_format($jobStatusCounts['published']) ?></strong>
      </div>
      <div class="ea-pipeline__item">
        <span>Bản nháp</span>
        <strong><?= number_format($jobStatusCounts['draft']) ?></strong>
      </div>
      <div class="ea-pipeline__item">
        <span>Đã đóng</span>
        <strong><?= number_format($jobStatusCounts['closed']) ?></strong>
      </div>
    </div>
  </aside>
</section>

<section class="ea-dashboard-grid ea-dashboard-grid--bottom">
  <article class="ea-panel">
    <div class="ea-panel__head">
      <div>
        <span class="ea-eyebrow">Tin tuyển dụng</span>
        <h3>Tin cập nhật gần đây</h3>
      </div>
      <a class="ea-text-link" href="<?= BASE_URL ?>/employer/admin/jobs.php">Quản lý tin</a>
    </div>
    <?php if (empty($latestJobs)): ?>
      <div class="ea-empty-state">
        <strong>Chưa có tin tuyển dụng.</strong>
        <p>Tạo tin đầu tiên để ứng viên có thể tìm thấy doanh nghiệp.</p>
      </div>
    <?php else: ?>
      <div class="ea-job-list">
        <?php foreach ($latestJobs as $job): ?>
          <?php
            $jobStatus = $job['status'] ?? 'draft';
            $jobStatusText = ['published' => 'Đang đăng', 'draft' => 'Nháp', 'closed' => 'Đã đóng'][$jobStatus] ?? ucfirst($jobStatus);
          ?>
          <a class="ea-job-row" href="<?= BASE_URL ?>/employer/admin/job_edit.php?id=<?= (int)$job['id'] ?>">
            <div>
              <strong><?= htmlspecialchars($job['title'] ?? 'Tin tuyển dụng') ?></strong>
              <span><?= htmlspecialchars($job['location'] ?: 'Toàn quốc') ?> · <?= htmlspecialchars($job['salary'] ?: 'Thỏa thuận') ?></span>
            </div>
            <small><?= htmlspecialchars($jobStatusText) ?></small>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>

  <article class="ea-panel">
    <div class="ea-panel__head ea-panel__head--compact">
      <div>
        <span class="ea-eyebrow">Thao tác nhanh</span>
        <h3>Cần làm</h3>
      </div>
    </div>
    <div class="ea-action-list">
      <a href="<?= BASE_URL ?>/employer/admin/job_edit.php">
        <strong>Đăng tin mới</strong>
        <span>Tạo vị trí tuyển dụng và mở nhận hồ sơ.</span>
      </a>
      <a href="<?= BASE_URL ?>/employer/admin/applications.php?status=applied">
        <strong>Xem hồ sơ mới</strong>
        <span>Lọc các ứng viên đang chờ phản hồi.</span>
      </a>
      <a href="<?= htmlspecialchars($employerProfileUrl) ?>" target="_blank" rel="noopener">
        <strong>Xem trang công ty</strong>
        <span>Kiểm tra hồ sơ doanh nghiệp bên ngoài.</span>
      </a>
    </div>
  </article>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
