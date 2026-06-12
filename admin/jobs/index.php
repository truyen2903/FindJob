<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Job.php';

if (!isset($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$jobModel = new Job();

$filters = [
    'keyword' => trim($_GET['keyword'] ?? ''),
    'status' => trim($_GET['status'] ?? '')
];

$allowedStatuses = ['draft', 'published', 'closed'];
if (!in_array($filters['status'], $allowedStatuses, true)) {
    $filters['status'] = '';
}

$perPage = 20;
$requestedPage = max(1, (int)($_GET['page'] ?? 1));
$result = $jobModel->getAdminList($filters, $requestedPage, $perPage);
$jobs = $result['rows'] ?? [];
$queryError = $result['query_error'] ?? null;
$totalFiltered = (int)($result['total'] ?? 0);
$currentPage = (int)($result['page'] ?? $requestedPage);
$totalPages = (int)($result['total_pages'] ?? 1);
$displayedJobs = count($jobs);

$statusCounts = $jobModel->countByStatus();
$totalJobs = array_sum($statusCounts);

$flash = $_SESSION['admin_job_flash'] ?? null;
unset($_SESSION['admin_job_flash']);

$statusLabels = [
    'draft' => ['label' => 'Nháp', 'badge' => 'secondary'],
    'published' => ['label' => 'Đang hiển thị', 'badge' => 'success'],
    'closed' => ['label' => 'Đã đóng', 'badge' => 'dark']
];

ob_start();
?>
<div class="pagetitle">
  <h1>Duyệt tin tuyển dụng</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Trang chủ</a></li>
      <li class="breadcrumb-item active">Tin tuyển dụng</li>
    </ol>
  </nav>
</div>

<section class="section">
  <?php if (!empty($flash)) : ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($flash['message'] ?? '') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
    </div>
  <?php endif; ?>

  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <div class="text-muted small">Tổng số tin</div>
          <div class="fs-4 fw-semibold"><?= (int)$totalJobs ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <div class="text-muted small">Tin nháp</div>
          <div class="fs-4 fw-semibold text-secondary"><?= (int)($statusCounts['draft'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <div class="text-muted small">Đang hiển thị</div>
          <div class="fs-4 fw-semibold text-success"><?= (int)($statusCounts['published'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <div class="text-muted small">Đã đóng</div>
          <div class="fs-4 fw-semibold text-dark"><?= (int)($statusCounts['closed'] ?? 0) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <form class="row gy-2 gx-3 align-items-end mb-4" method="get">
        <div class="col-sm-6 col-lg-4">
          <label class="form-label" for="filterKeyword">Từ khóa</label>
          <input type="text" id="filterKeyword" name="keyword" class="form-control" placeholder="Tiêu đề, công ty hoặc email" value="<?= htmlspecialchars($filters['keyword']) ?>">
        </div>
        <div class="col-sm-6 col-lg-3">
          <label class="form-label" for="filterStatus">Trạng thái</label>
          <select id="filterStatus" name="status" class="form-select">
            <option value="" <?= $filters['status'] === '' ? 'selected' : '' ?>>Tất cả</option>
            <?php foreach ($statusLabels as $key => $meta) : ?>
              <option value="<?= htmlspecialchars($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-12 col-lg-3 text-lg-end">
          <button type="submit" class="btn btn-primary me-2">
            <i class="bi bi-search"></i> Tìm kiếm
          </button>
          <a href="<?= ADMIN_URL ?>/jobs/index.php" class="btn btn-outline-secondary">Đặt lại</a>
        </div>
      </form>

      <?php if ($queryError) : ?>
        <div class="alert alert-danger">
          Không thể tải danh sách tin tuyển dụng. Chi tiết: <?= htmlspecialchars($queryError) ?>
        </div>
      <?php endif; ?>

      <?php if ($totalFiltered > 0) : ?>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
          <div class="text-muted small">
            Đang hiển thị <strong><?= $displayedJobs ?></strong> / <strong><?= number_format($totalFiltered) ?></strong> tin phù hợp với bộ lọc.
          </div>
          <div class="badge bg-light text-primary border border-primary">Trang <?= $currentPage ?> / <?= max(1, $totalPages) ?></div>
        </div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th scope="col">ID</th>
              <th scope="col">Tiêu đề</th>
              <th scope="col">Nhà tuyển dụng</th>
              <th scope="col">Trạng thái</th>
              <th scope="col" class="d-none d-xl-table-cell">Số lượng</th>
              <th scope="col" class="d-none d-xl-table-cell">Hạn nộp</th>
              <th scope="col" class="d-none d-lg-table-cell">Tạo lúc</th>
              <th scope="col" class="d-none d-lg-table-cell">Cập nhật</th>
              <th scope="col" class="text-end">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($jobs)) : ?>
              <tr>
                <td colspan="7" class="text-center text-muted py-4">Không có tin tuyển dụng phù hợp.</td>
              </tr>
            <?php else : ?>
              <?php foreach ($jobs as $job) : ?>
                <?php
                  $status = $job['status'] ?? 'draft';
                  $statusMeta = $statusLabels[$status] ?? $statusLabels['draft'];
                  $createdAt = $job['created_at'] ?? null;
                  $updatedAt = $job['updated_at'] ?? null;
                  $createdLabel = $createdAt ? date('d/m/Y H:i', strtotime($createdAt)) : '—';
                  $updatedLabel = $updatedAt ? date('d/m/Y H:i', strtotime($updatedAt)) : '—';
                  $employerName = $job['company_name'] ?? 'Chưa cập nhật';
                  $employerEmail = $job['employer_email'] ?? '';
                  $description = trim((string)($job['description'] ?? ''));
                  if ($description !== '') {
                      if (function_exists('mb_strimwidth')) {
                          $descriptionPreview = mb_strimwidth($description, 0, 140, '...');
                      } else {
                          $descriptionPreview = strlen($description) > 140 ? substr($description, 0, 137) . '...' : $description;
                      }
                  } else {
                      $descriptionPreview = 'Chưa cập nhật mô tả.';
                  }
                ?>
                <tr>
                  <td><?= (int)$job['id'] ?></td>
                  <td>
                    <div class="fw-semibold mb-1"><?= htmlspecialchars($job['title']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($descriptionPreview) ?></div>
                  </td>
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars($employerName) ?></div>
                    <?php if ($employerEmail !== '') : ?>
                      <div class="text-muted small"><?= htmlspecialchars($employerEmail) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge bg-<?= htmlspecialchars($statusMeta['badge']) ?>"><?= htmlspecialchars($statusMeta['label']) ?></span></td>
                  <td class="d-none d-xl-table-cell text-muted small"><?= isset($job['quantity']) && $job['quantity'] ? (int)$job['quantity'] : '—' ?></td>
                  <td class="d-none d-xl-table-cell text-muted small"><?= $job['deadline'] ? htmlspecialchars(date('d/m/Y', strtotime($job['deadline']))) : '—' ?></td>
                  <td class="d-none d-lg-table-cell text-muted small"><?= htmlspecialchars($createdLabel) ?></td>
                  <td class="d-none d-lg-table-cell text-muted small"><?= htmlspecialchars($updatedLabel) ?></td>
                  <td class="text-end">
                    <div class="btn-group" role="group">
                      <?php if ($status !== 'published') : ?>
                        <form action="<?= ADMIN_URL ?>/jobs/update_status.php" method="post" class="d-inline">
                          <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
                          <input type="hidden" name="status" value="published">
                          <button type="submit" class="btn btn-sm btn-outline-success" title="Duyệt tin">
                            <i class="bi bi-check-circle"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                      <?php if ($status !== 'draft') : ?>
                        <form action="<?= ADMIN_URL ?>/jobs/update_status.php" method="post" class="d-inline">
                          <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
                          <input type="hidden" name="status" value="draft">
                          <button type="submit" class="btn btn-sm btn-outline-secondary" title="Chuyển về nháp">
                            <i class="bi bi-arrow-counterclockwise"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                      <?php if ($status !== 'closed') : ?>
                        <form action="<?= ADMIN_URL ?>/jobs/update_status.php" method="post" class="d-inline" onsubmit="return confirm('Xác nhận đóng tin tuyển dụng này?');">
                          <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
                          <input type="hidden" name="status" value="closed">
                          <button type="submit" class="btn btn-sm btn-outline-danger" title="Đóng tin">
                            <i class="bi bi-x-circle"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($totalPages > 1) : ?>
    <?php
      $paginationBase = ADMIN_URL . '/jobs/index.php';
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
    <nav class="mt-4" aria-label="Phân trang tin tuyển dụng">
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
</section>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
