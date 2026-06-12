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
$_SESSION['employer_company_name'] = $employer['company_name'];
$_SESSION['employer_profile_url'] = BASE_URL . '/employer/show.php?id=' . $employerId;

$page = max(1, (int)($_GET['page'] ?? 1));
$filters = [
    'job_id' => isset($_GET['job_id']) ? (int)$_GET['job_id'] : null,
    'status' => isset($_GET['status']) ? trim((string)$_GET['status']) : '',
    'keyword' => isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '',
];

$list = $applicationModel->listForEmployer($employerId, $filters, $page, 12);
$jobsResult = $jobModel->getByEmployer($employerId);
$jobs = [];
if ($jobsResult) {
    while ($row = $jobsResult->fetch_assoc()) {
        $jobs[] = $row;
    }
    $jobsResult->free();
}

$statusLabels = $applicationModel->getStatusLabels();

$pageTitle = 'Hồ sơ ứng viên | JobFind';
$employerNavActive = 'applications';
$employerCompanyName = $employer['company_name'];
$employerProfileUrl = BASE_URL . '/employer/show.php?id=' . $employerId;
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
  <div>
    <span class="text-muted text-uppercase small fw-semibold">Quản lý tuyển dụng</span>
    <h2 class="h4 mb-0">Hồ sơ ứng viên</h2>
  </div>
  <a class="btn btn-primary" href="<?= BASE_URL ?>/employer/admin/jobs.php"><i class="fa-solid fa-circle-plus me-2"></i>Đăng tin mới</a>
</div>

<form class="ea-filter-bar" method="get">
  <div class="input-group" style="max-width: 340px;">
    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
    <input type="search" name="keyword" class="form-control" placeholder="Tìm kiếm theo tên hoặc email" value="<?= htmlspecialchars($filters['keyword'] ?? '') ?>">
  </div>
  <select name="job_id" class="form-select" aria-label="Lọc theo tin tuyển dụng">
    <option value="">Tất cả tin tuyển dụng</option>
    <?php foreach ($jobs as $job): ?>
      <option value="<?= (int)$job['id'] ?>" <?= (int)($filters['job_id'] ?? 0) === (int)$job['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($job['title']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select name="status" class="form-select" aria-label="Lọc theo trạng thái">
    <option value="">Tất cả trạng thái</option>
    <?php foreach ($statusLabels as $value => $label): ?>
      <option value="<?= $value ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-secondary" type="submit">Lọc kết quả</button>
  <?php if (!empty($filters['job_id']) || !empty($filters['status']) || !empty($filters['keyword'])): ?>
    <a class="btn btn-link text-decoration-none" href="<?= BASE_URL ?>/employer/admin/applications.php">Xóa lọc</a>
  <?php endif; ?>
</form>

<div class="ea-table-wrapper">
  <table class="table align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th scope="col">Ứng viên</th>
        <th scope="col">Tin tuyển dụng</th>
        <th scope="col">Trạng thái</th>
        <th scope="col">Ứng tuyển lúc</th>
        <th scope="col" class="text-end">Hành động</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($list['rows'])): ?>
        <tr>
          <td colspan="5" class="text-center text-muted py-5">
            <i class="fa-regular fa-face-smile-beam fa-2x mb-3"></i>
            <p class="mb-0">Chưa có hồ sơ nào phù hợp điều kiện lọc.</p>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($list['rows'] as $app): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($app['candidate_name'] ?? $app['candidate_email'] ?? 'Ứng viên') ?></div>
              <div class="text-muted small"><?= htmlspecialchars($app['candidate_email'] ?? '') ?></div>
            </td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($app['job_title'] ?? 'Tin tuyển dụng') ?></div>
              <div class="text-muted small">ID #<?= (int)$app['job_id'] ?></div>
            </td>
            <td>
              <?php
                $statusValue = $app['status'] ?? 'applied';
                $notePreview = '';
                if (!empty($app['decision_note'])) {
                  $notePreview = trim((string)$app['decision_note']);
                  if ($notePreview !== '') {
                    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                      if (mb_strlen($notePreview) > 90) {
                        $notePreview = mb_substr($notePreview, 0, 90) . '…';
                      }
                    } elseif (strlen($notePreview) > 90) {
                      $notePreview = substr($notePreview, 0, 90) . '…';
                    }
                  }
                }
              ?>
              <span class="badge-status <?= htmlspecialchars($statusValue) ?>"><?= $statusLabels[$statusValue] ?? ucfirst($statusValue) ?></span>
              <?php if ($notePreview !== ''): ?>
                <div class="small text-muted mt-1">"<?= htmlspecialchars($notePreview) ?>"</div>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($app['applied_at'] ?? 'now'))) ?></td>
            <td class="text-end">
              <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/employer/admin/application_view.php?id=<?= (int)$app['id'] ?>">
                <i class="fa-regular fa-eye me-1"></i>Xem chi tiết
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (($list['total_pages'] ?? 1) > 1): ?>
  <nav class="mt-4" aria-label="Pagination">
    <ul class="pagination justify-content-end">
      <?php $totalPages = (int)($list['total_pages'] ?? 1); ?>
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php
          $query = $_GET;
          $query['page'] = $i;
          $url = BASE_URL . '/employer/admin/applications.php?' . http_build_query($query);
        ?>
        <li class="page-item <?= $i === (int)$list['page'] ? 'active' : '' ?>">
          <a class="page-link" href="<?= htmlspecialchars($url) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
