<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Employer.php';

if (!isset($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 1) {
  header('Location: ' . BASE_URL . '/403.php');
  exit;
}

$employerModel = new Employer();

$filters = [
  'keyword' => trim($_GET['keyword'] ?? ''),
  'location' => trim($_GET['location'] ?? ''),
  'status' => trim($_GET['status'] ?? '')
];

$dashboardData = $employerModel->getAdminDashboardData($filters);
$employers = $dashboardData['rows'] ?? [];
$stats = $dashboardData['stats'] ?? [];
$queryError = $dashboardData['query_error'] ?? null;
$filters = $dashboardData['applied_filters'] ?? $filters;

$totalEmployers = (int)($stats['total_employers'] ?? 0);
$activeEmployers = (int)($stats['active_employers'] ?? 0);
$inactiveEmployers = (int)($stats['inactive_employers'] ?? 0);
$totalPublishedJobs = (int)($stats['total_published_jobs'] ?? 0);

$statusOptions = [
    '' => 'Tất cả',
    'has_jobs' => 'Đang đăng tuyển',
    'no_jobs' => 'Chưa có tin'
];

ob_start();
?>
<div class="pagetitle">
  <h1>Quản lý nhà tuyển dụng</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Trang chủ</a></li>
      <li class="breadcrumb-item active">Nhà tuyển dụng</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <small class="text-muted">Tổng nhà tuyển dụng</small>
          <div class="fs-4 fw-semibold mb-0"><?= $totalEmployers ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <small class="text-muted">Đang hoạt động</small>
          <div class="fs-4 fw-semibold text-success mb-0"><?= $activeEmployers ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <small class="text-muted">Chưa đăng tin</small>
          <div class="fs-4 fw-semibold text-secondary mb-0"><?= $inactiveEmployers ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <small class="text-muted">Tin đã duyệt</small>
          <div class="fs-4 fw-semibold text-primary mb-0"><?= $totalPublishedJobs ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3 gap-3">
        <form class="row gy-2 gx-3 align-items-end" method="get">
          <div class="col-sm-6 col-lg-4">
            <label for="filterKeyword" class="form-label">Tìm kiếm</label>
            <input type="text" id="filterKeyword" name="keyword" value="<?= htmlspecialchars($filters['keyword']) ?>" class="form-control" placeholder="Tên công ty, email hoặc tên người dùng">
          </div>
          <div class="col-sm-6 col-lg-3">
            <label for="filterLocation" class="form-label">Địa chỉ</label>
            <input type="text" id="filterLocation" name="location" value="<?= htmlspecialchars($filters['location']) ?>" class="form-control" placeholder="Ví dụ: Hà Nội">
          </div>
          <div class="col-sm-6 col-lg-3">
            <label for="filterStatus" class="form-label">Trạng thái</label>
            <select id="filterStatus" name="status" class="form-select">
              <?php foreach ($statusOptions as $value => $label): ?>
                <option value="<?= htmlspecialchars($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-6 col-lg-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Lọc</button>
            <a href="<?= ADMIN_URL ?>/employers/employers.php" class="btn btn-light">Đặt lại</a>
          </div>
        </form>
        <div class="text-lg-end">
          <a href="<?= ADMIN_URL ?>/employers/add_employer.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Thêm nhà tuyển dụng
          </a>
        </div>
      </div>

      <?php if ($queryError): ?>
        <div class="alert alert-danger">Không thể tải dữ liệu: <?= htmlspecialchars($queryError) ?></div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th scope="col">Nhà tuyển dụng</th>
              <th scope="col" class="d-none d-lg-table-cell">Thông tin liên hệ</th>
              <th scope="col">Đăng tuyển</th>
              <th scope="col" class="d-none d-md-table-cell">Hoạt động gần đây</th>
              <th scope="col" class="text-end">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($employers)): ?>
              <tr>
                <td colspan="5" class="text-center text-muted py-4">Chưa có nhà tuyển dụng phù hợp với bộ lọc.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($employers as $row): ?>
                <?php
                  $about = trim((string)($row['about'] ?? ''));
                  if ($about !== '') {
                      if (function_exists('mb_strimwidth')) {
                          $aboutPreview = mb_strimwidth($about, 0, 140, '...');
                      } else {
                          $aboutPreview = strlen($about) > 140 ? substr($about, 0, 137) . '...' : $about;
                      }
                  } else {
                      $aboutPreview = 'Doanh nghiệp chưa cập nhật mô tả.';
                  }
                  $lastActivity = $row['last_job_activity'] ? date('d/m/Y H:i', strtotime($row['last_job_activity'])) : '—';
                  $publishedJobs = (int)$row['published_jobs'];
                  $draftJobs = (int)$row['draft_jobs'];
                  $totalJobs = (int)$row['total_jobs'];
                ?>
                <tr>
                  <td>
                    <div class="fw-semibold mb-1"><?= htmlspecialchars($row['company_name'] ?? 'Không tên') ?></div>
                    <div class="text-muted small mb-1"><?= htmlspecialchars($row['address'] ?: 'Chưa cập nhật địa chỉ') ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($aboutPreview) ?></div>
                  </td>
                  <td class="d-none d-lg-table-cell">
                    <div class="small"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($row['user_email'] ?? 'Không có') ?></div>
                    <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['user_phone'] ?: 'Chưa cập nhật') ?></div>
                    <div class="small text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($row['user_name'] ?: 'Tài khoản hệ thống') ?></div>
                  </td>
                  <td>
                    <span class="badge bg-success me-1">Hiển thị: <?= $publishedJobs ?></span>
                    <span class="badge bg-secondary me-1">Nháp: <?= $draftJobs ?></span>
                    <span class="badge bg-light text-dark">Tổng: <?= $totalJobs ?></span>
                  </td>
                  <td class="d-none d-md-table-cell text-muted small"><?= htmlspecialchars($lastActivity) ?></td>
                  <td class="text-end">
                    <div class="btn-group" role="group">
                      <a href="<?= BASE_URL ?>/employer/index.php?q=<?= urlencode($row['company_name'] ?? '') ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Xem trang công ty">
                        <i class="bi bi-eye"></i>
                      </a>
                      <a href="<?= ADMIN_URL ?>/employers/edit_employer.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Chỉnh sửa">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <a href="<?= ADMIN_URL ?>/employers/delete_employer.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-danger" title="Xóa" onclick="return confirm('Xoá nhà tuyển dụng này?');">
                        <i class="bi bi-trash"></i>
                      </a>
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
</section>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
