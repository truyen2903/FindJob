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
    'applied' => 'Hồ sơ mới được nộp và chờ duyệt',
    'viewed' => 'Nhà tuyển dụng đã xem hồ sơ',
    'shortlisted' => 'Ứng viên đã vào danh sách phỏng vấn',
    'rejected' => 'Hồ sơ bị từ chối với ghi chú phản hồi',
    'hired' => 'Ứng viên đã được tuyển dụng',
    'withdrawn' => 'Ứng viên chủ động rút hồ sơ',
];

$filters = [
    'status' => isset($_GET['status']) ? trim((string)$_GET['status']) : '',
    'keyword' => isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '',
    'job_id' => isset($_GET['job_id']) ? (int)$_GET['job_id'] : null,
    'employer_id' => isset($_GET['employer_id']) ? (int)$_GET['employer_id'] : null,
    'date_from' => isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '',
    'date_to' => isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '',
];

$perPage = 20;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$listResult = $applicationModel->listForAdmin($filters, $currentPage, $perPage);
$applications = $listResult['rows'] ?? [];
$queryError = $listResult['query_error'] ?? null;
$totalRecords = (int)($listResult['total'] ?? 0);
$currentPage = (int)($listResult['page'] ?? $currentPage);
$totalPages = (int)($listResult['total_pages'] ?? 1);
$appliedFilters = $listResult['applied_filters'] ?? $filters;
$displayedCount = count($applications);

$summaryStats = $applicationModel->getAdminSummaryStats();
$statusBreakdown = $applicationModel->getAdminStatusBreakdown();
$statusOrder = array_keys($statusLabels);
$statusIndex = array_flip($statusOrder);
$statusBadgeMap = [
    'applied' => 'secondary',
    'viewed' => 'info',
    'shortlisted' => 'warning',
    'rejected' => 'danger',
    'hired' => 'success',
    'withdrawn' => 'dark',
];

$flash = $_SESSION['admin_application_flash'] ?? null;
unset($_SESSION['admin_application_flash']);

ob_start();
?>
<div class="pagetitle">
  <h1>Giám sát hồ sơ ứng tuyển</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Trang chủ</a></li>
      <li class="breadcrumb-item active">Hồ sơ ứng tuyển</li>
    </ol>
  </nav>
</div>

<section class="section">
  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($flash['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
    </div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-xxl-2 col-md-4">
      <div class="card info-card">
        <div class="card-body">
          <h5 class="card-title">Tổng hồ sơ</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-files"></i>
            </div>
            <div class="ps-3">
              <h6><?= number_format($summaryStats['total'] ?? 0) ?></h6>
              <span class="text-muted small">Tất cả thời gian</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xxl-2 col-md-4">
      <div class="card info-card">
        <div class="card-body">
          <h5 class="card-title">7 ngày gần nhất</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-calendar-week"></i>
            </div>
            <div class="ps-3">
              <h6><?= number_format($summaryStats['last7_days'] ?? 0) ?></h6>
              <span class="text-muted small">Hồ sơ mới</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xxl-2 col-md-4">
      <div class="card info-card">
        <div class="card-body">
          <h5 class="card-title">30 ngày</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-clock-history"></i>
            </div>
            <div class="ps-3">
              <h6><?= number_format($summaryStats['last30_days'] ?? 0) ?></h6>
              <span class="text-muted small">Xu hướng gần đây</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xxl-2 col-md-4">
      <div class="card info-card">
        <div class="card-body">
          <h5 class="card-title">Chờ duyệt</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="ps-3">
              <h6><?= number_format($summaryStats['awaiting_review'] ?? 0) ?></h6>
              <span class="text-muted small">Applied + Viewed</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xxl-2 col-md-4">
      <div class="card info-card">
        <div class="card-body">
          <h5 class="card-title">Đã chọn</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-star"></i>
            </div>
            <div class="ps-3">
              <h6><?= number_format($summaryStats['shortlisted'] ?? 0) ?></h6>
              <span class="text-muted small">Shortlisted</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xxl-2 col-md-4">
      <div class="card info-card">
        <div class="card-body">
          <h5 class="card-title">Đã tuyển dụng</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-patch-check"></i>
            </div>
            <div class="ps-3">
              <h6><?= number_format($summaryStats['hired'] ?? 0) ?></h6>
              <span class="text-muted small">Hoàn tất</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title">Bộ lọc nâng cao</h5>
          <form class="row gy-3" method="get">
            <div class="col-md-6">
              <label for="filterKeyword" class="form-label">Từ khóa</label>
              <input type="text" id="filterKeyword" name="keyword" class="form-control" placeholder="Tên ứng viên, email, tin tuyển dụng..." value="<?= htmlspecialchars($appliedFilters['keyword'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label for="filterStatus" class="form-label">Trạng thái</label>
              <select id="filterStatus" name="status" class="form-select">
                <option value="" <?= ($appliedFilters['status'] ?? '') === '' ? 'selected' : '' ?>>Tất cả</option>
                <?php foreach ($statusLabels as $key => $label): ?>
                  <option value="<?= htmlspecialchars($key) ?>" <?= ($appliedFilters['status'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label for="filterJob" class="form-label">ID tin tuyển dụng</label>
              <input type="number" min="1" id="filterJob" name="job_id" class="form-control" value="<?= $appliedFilters['job_id'] ? (int)$appliedFilters['job_id'] : '' ?>">
            </div>
            <div class="col-md-3">
              <label for="filterEmployer" class="form-label">ID nhà tuyển dụng</label>
              <input type="number" min="1" id="filterEmployer" name="employer_id" class="form-control" value="<?= $appliedFilters['employer_id'] ? (int)$appliedFilters['employer_id'] : '' ?>">
            </div>
            <div class="col-md-3">
              <label for="filterDateFrom" class="form-label">Từ ngày</label>
              <input type="date" id="filterDateFrom" name="date_from" class="form-control" value="<?= htmlspecialchars($appliedFilters['date_from'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label for="filterDateTo" class="form-label">Đến ngày</label>
              <input type="date" id="filterDateTo" name="date_to" class="form-control" value="<?= htmlspecialchars($appliedFilters['date_to'] ?? '') ?>">
            </div>
            <div class="col-md-6 d-flex align-items-end justify-content-end gap-2">
              <a href="<?= ADMIN_URL ?>/applications/index.php" class="btn btn-outline-secondary">Đặt lại</a>
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-search me-1"></i>Lọc dữ liệu
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title">Phân bổ trạng thái</h5>
          <?php $breakdownTotal = array_sum($statusBreakdown); ?>
          <?php foreach ($statusBreakdown as $statusKey => $count): ?>
            <?php $percent = $breakdownTotal > 0 ? round(($count / $breakdownTotal) * 100, 1) : 0; ?>
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <span class="badge bg-<?= htmlspecialchars($statusBadgeMap[$statusKey] ?? 'secondary') ?>">
                    <?= htmlspecialchars($statusLabels[$statusKey] ?? ucfirst($statusKey)) ?>
                  </span>
                </div>
                <div class="fw-semibold"><?= number_format($count) ?></div>
              </div>
              <div class="progress mt-2" style="height: 6px;">
                <div class="progress-bar bg-<?= htmlspecialchars($statusBadgeMap[$statusKey] ?? 'secondary') ?>" role="progressbar" style="width: <?= $percent ?>%" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
              </div>
              <div class="text-muted small mt-1"><?= $percent ?>% tổng số hồ sơ</div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-3">
        <div class="text-muted small">
          Đang hiển thị <strong><?= $displayedCount ?></strong> / <strong><?= number_format($totalRecords) ?></strong> hồ sơ phù hợp.
        </div>
        <div class="badge bg-light text-primary border border-primary">Trang <?= $currentPage ?> / <?= max(1, $totalPages) ?></div>
      </div>

      <?php if ($queryError): ?>
        <div class="alert alert-danger" role="alert">
          Không thể tải dữ liệu: <?= htmlspecialchars($queryError) ?>
        </div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Ứng viên</th>
              <th>Tin tuyển dụng</th>
              <th>Trạng thái</th>
              <th>Thời gian</th>
              <th class="text-end">Theo dõi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($applications)): ?>
              <tr>
                <td colspan="6" class="text-center text-muted py-4">Không có hồ sơ nào đáp ứng bộ lọc.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($applications as $application): ?>
                <?php
                  $statusKey = $application['status'] ?? 'applied';
                  $statusLabel = $statusLabels[$statusKey] ?? ucfirst($statusKey);
                  $badge = $statusBadgeMap[$statusKey] ?? 'secondary';
                  $index = isset($statusIndex[$statusKey]) ? $statusIndex[$statusKey] : 0;
                  $progressPercent = count($statusOrder) > 0 ? round((($index + 1) / count($statusOrder)) * 100) : 0;
                  $appliedAt = $application['applied_at'] ?? null;
                  $appliedLabel = $appliedAt ? date('d/m/Y H:i', strtotime($appliedAt)) : '—';
          $note = trim((string)($application['decision_note'] ?? ''));
          $notePreview = $note;
          if ($note !== '') {
            if (function_exists('mb_strimwidth')) {
              $notePreview = mb_strimwidth($note, 0, 60, '...');
            } elseif (strlen($note) > 60) {
              $notePreview = substr($note, 0, 57) . '...';
            }
          }
                ?>
                <tr>
                  <td class="fw-semibold">#<?= (int)$application['id'] ?></td>
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars($application['candidate_name'] ?? 'Ứng viên') ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($application['candidate_email'] ?? '') ?></div>
                    <?php if (!empty($application['candidate_location'])): ?>
                      <div class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($application['candidate_location']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars($application['job_title'] ?? 'Tin tuyển dụng') ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($application['employer_name'] ?? '') ?></div>
                  </td>
                  <td>
                    <span class="badge bg-<?= htmlspecialchars($badge) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                    <div class="progress mt-2" style="height: 6px;">
                      <div class="progress-bar bg-<?= htmlspecialchars($badge) ?>" role="progressbar" style="width: <?= $progressPercent ?>%" aria-valuenow="<?= $progressPercent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="text-muted small mt-1">
                      <?= $progressPercent ?>% hành trình · <?= htmlspecialchars($statusDescriptions[$statusKey] ?? '') ?>
                    </div>
                  </td>
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars($appliedLabel) ?></div>
                    <?php if ($note !== ''): ?>
                      <div class="text-muted small"><i class="bi bi-chat-dots me-1"></i><?= htmlspecialchars($notePreview) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <a href="<?= ADMIN_URL ?>/applications/show.php?id=<?= (int)$application['id'] ?>" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-eye"></i> Chi tiết
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
        <?php
          $baseUrl = ADMIN_URL . '/applications/index.php';
          $paginationQuery = $_GET;
          unset($paginationQuery['page']);
          $buildUrl = static function (int $page, string $base, array $query): string {
              $query['page'] = $page;
              $queryString = http_build_query($query);
              return $base . ($queryString === '' ? '' : '?' . $queryString);
          };
          $start = max(1, $currentPage - 2);
          $end = min($totalPages, $start + 4);
          $start = max(1, $end - 4);
        ?>
        <nav class="mt-4" aria-label="Phân trang hồ sơ ứng tuyển">
          <ul class="pagination justify-content-center">
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= $currentPage <= 1 ? '#' : $buildUrl($currentPage - 1, $baseUrl, $paginationQuery) ?>" aria-label="Trang trước">&laquo;</a>
            </li>
            <?php for ($page = $start; $page <= $end; $page++): ?>
              <li class="page-item <?= $page === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="<?= $buildUrl($page, $baseUrl, $paginationQuery) ?>"><?= $page ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= $currentPage >= $totalPages ? '#' : $buildUrl($currentPage + 1, $baseUrl, $paginationQuery) ?>" aria-label="Trang sau">&raquo;</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
?>
