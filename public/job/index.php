<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/controllers/JobController.php';
require_once dirname(__DIR__, 2) . '/app/models/Employer.php';
require_once dirname(__DIR__, 2) . '/app/models/Job.php';

$userId = $_SESSION['user_id'] ?? null;
$roleId = $_SESSION['role_id'] ?? null;
if (!$userId) {
  header('Location: ' . BASE_URL . '/account/login.php');
  exit;
}
if ((int)$roleId !== 2) {
  header('Location: ' . BASE_URL . '/403.php');
  exit;
}

$jobController = new JobController();
$employer = $jobController->ensureEmployer((int)$userId);
if (!$employer) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$perPage = 10;
$requestedPage = max(1, (int)($_GET['page'] ?? 1));
$jobsData = $jobController->listJobs((int)$userId, $requestedPage, $perPage);
$jobs = $jobsData['rows'] ?? [];
$totalJobs = (int)($jobsData['total'] ?? 0);
$currentPage = (int)($jobsData['page'] ?? $requestedPage);
$totalPages = (int)($jobsData['total_pages'] ?? 1);
$queryError = $jobsData['query_error'] ?? null;
$displayedJobs = count($jobs);

$jobCategoryMap = [];
if (!empty($jobs)) {
  $jobIds = [];
  foreach ($jobs as $jobItem) {
    $jobIds[] = (int)($jobItem['id'] ?? 0);
  }
  $jobIds = array_values(array_filter(array_unique($jobIds))); 
  if (!empty($jobIds)) {
    $jobCategoryMap = $jobController->getCategoriesForJobs($jobIds);
  }
}

$flash = $_SESSION['job_flash'] ?? null;
unset($_SESSION['job_flash']);

$pageTitle = 'Quản lý tin tuyển dụng | JobFind';
$bodyClass = 'job-manage-page';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<main class="container py-5">
  <div class="row mb-4 align-items-center">
    <div class="col-12 col-lg-8">
      <h1 class="fw-semibold mb-1">Quản lý tin tuyển dụng</h1>
      <p class="text-muted mb-0">Theo dõi và cập nhật các vị trí đang tuyển của doanh nghiệp bạn.</p>
    </div>
    <div class="col-12 col-lg-4 text-lg-end mt-3 mt-lg-0">
      <a href="<?= BASE_URL ?>/job/create.php" class="btn btn-success">
        <i class="fa-solid fa-plus me-2"></i>Đăng tin mới
      </a>
    </div>
  </div>

  <?php if (!empty($flash)) : ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($flash['message'] ?? '') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
    </div>
  <?php endif; ?>

  <?php if ($queryError) : ?>
    <div class="alert alert-danger">Không thể tải danh sách việc làm. Chi tiết: <?= htmlspecialchars($queryError) ?></div>
  <?php endif; ?>

  <?php if (empty($jobs)) : ?>
    <div class="card border-0 shadow-sm p-5 text-center">
      <div class="text-muted mb-3"><i class="fa-solid fa-briefcase fa-2x"></i></div>
      <h5 class="fw-semibold mb-2">Bạn chưa có tin tuyển dụng nào</h5>
      <p class="text-muted mb-4">Hãy đăng tin đầu tiên để thu hút ứng viên chất lượng trên JobFind.</p>
      <a href="<?= BASE_URL ?>/job/create.php" class="btn btn-success">Tạo tin tuyển dụng</a>
    </div>
  <?php else : ?>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
      <div class="text-muted small">
        Đang hiển thị <strong><?= $displayedJobs ?></strong> / <strong><?= number_format($totalJobs) ?></strong> tin tuyển dụng.
      </div>
      <div class="badge bg-light text-success border border-success">Trang <?= $currentPage ?> / <?= max(1, $totalPages) ?></div>
    </div>
    <div class="card border-0 shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th scope="col">Tiêu đề</th>
                <th scope="col" class="d-none d-md-table-cell">Địa điểm</th>
                <th scope="col" class="d-none d-md-table-cell">Hình thức</th>
                <th scope="col" class="d-none d-lg-table-cell">Số lượng</th>
                <th scope="col" class="d-none d-lg-table-cell">Hạn nộp</th>
                <th scope="col">Trạng thái</th>
                <th scope="col" class="d-none d-lg-table-cell">Cập nhật</th>
                <th scope="col" class="text-end">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($jobs as $job) : ?>
                <?php
          $status = $job['status'] ?? 'draft';
          $statusLabel = 'Chờ duyệt';
          $statusClass = 'warning';
          if ($status === 'published') {
            $statusLabel = 'Đang hiển thị';
            $statusClass = 'success';
          } elseif ($status === 'closed') {
            $statusLabel = 'Đã đóng';
            $statusClass = 'dark';
          }
                  $updatedAt = $job['updated_at'] ?: $job['created_at'] ?? null;
                  $updatedText = $updatedAt ? date('d/m/Y H:i', strtotime($updatedAt)) : '—';
                  $employmentType = $job['employment_type'] ?: 'Chưa cập nhật';
                  $location = $job['location'] ?: 'Chưa cập nhật';
          $jobCategories = $jobCategoryMap[(int)($job['id'] ?? 0)] ?? [];
                ?>
                <tr>
                  <td>
                    <div class="fw-semibold mb-1"><?= htmlspecialchars($job['title']) ?></div>
                    <div class="text-muted small d-md-none"><?= htmlspecialchars($location) ?></div>
                    <div class="text-muted small d-lg-none">Hạn nộp: <?= $job['deadline'] ? htmlspecialchars(date('d/m/Y', strtotime($job['deadline']))) : 'Không giới hạn' ?></div>
            <?php if (!empty($jobCategories)) : ?>
              <div class="mt-2 d-flex flex-wrap gap-1">
                <?php foreach ($jobCategories as $category) : ?>
                  <span class="badge bg-success bg-opacity-10 text-success border border-success"><?= htmlspecialchars($category['name']) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
                  </td>
                  <td class="d-none d-md-table-cell text-muted"><?= htmlspecialchars($location) ?></td>
                  <td class="d-none d-md-table-cell text-muted"><?= htmlspecialchars($employmentType) ?></td>
                  <td class="d-none d-lg-table-cell text-muted"><?= $job['quantity'] ? (int)$job['quantity'] : 'Không giới hạn' ?></td>
                  <td class="d-none d-lg-table-cell text-muted"><?= $job['deadline'] ? htmlspecialchars(date('d/m/Y', strtotime($job['deadline']))) : 'Không giới hạn' ?></td>
                  <td><span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                  <td class="d-none d-lg-table-cell text-muted small"><?= htmlspecialchars($updatedText) ?></td>
                  <td class="text-end">
                    <div class="btn-group" role="group">
                      <a href="<?= BASE_URL ?>/job/edit.php?id=<?= (int)$job['id'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-pen"></i>
                      </a>
                      <form action="<?= BASE_URL ?>/job/delete.php" method="post" class="d-inline" onsubmit="return confirm('Xác nhận xoá tin tuyển dụng này?');">
                        <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php if ($totalPages > 1) : ?>
      <?php
        $paginationBase = BASE_URL . '/job/index.php';
        $paginationQuery = $_GET;
        unset($paginationQuery['page']);
        $buildPageUrl = static function (int $pageNumber, string $base, array $query): string {
            $query['page'] = $pageNumber;
            $queryString = http_build_query($query);
            return $queryString === '' ? $base . '?page=' . $pageNumber : $base . '?' . $queryString;
        };
        $startPage = max(1, $currentPage - 2);
        $endPage = min($totalPages, $startPage + 4);
        $startPage = max(1, $endPage - 4);
      ?>
      <nav class="mt-4" aria-label="Phân trang danh sách việc làm">
        <ul class="pagination justify-content-center">
          <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $currentPage <= 1 ? '#' : $buildPageUrl($currentPage - 1, $paginationBase, $paginationQuery) ?>" tabindex="<?= $currentPage <= 1 ? '-1' : '0' ?>" aria-label="Trang trước">&laquo;</a>
          </li>
          <?php for ($page = $startPage; $page <= $endPage; $page++) : ?>
            <li class="page-item <?= $page === $currentPage ? 'active' : '' ?>">
              <a class="page-link" href="<?= $buildPageUrl($page, $paginationBase, $paginationQuery) ?>"><?= $page ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $currentPage >= $totalPages ? '#' : $buildPageUrl($currentPage + 1, $paginationBase, $paginationQuery) ?>" tabindex="<?= $currentPage >= $totalPages ? '-1' : '0' ?>" aria-label="Trang sau">&raquo;</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
