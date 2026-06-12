<?php
require_once dirname(__DIR__, 3) . '/config/config.php';
require_once dirname(__DIR__, 3) . '/app/models/Employer.php';
require_once dirname(__DIR__, 3) . '/app/models/Job.php';

if (empty($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 2) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$employerModel = new Employer();
$jobModel = new Job();
$employer = $employerModel->getByUserId($userId);
if (!$employer) {
    header('Location: ' . BASE_URL . '/employer/edit.php');
    exit;
}
$employerId = (int)$employer['id'];
$_SESSION['employer_company_name'] = $employer['company_name'];
$_SESSION['employer_profile_url'] = BASE_URL . '/employer/show.php?id=' . $employerId;

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$dataset = $jobModel->getByEmployerPaginated($employerId, $page, $perPage);
$jobs = $dataset['rows'];
$total = $dataset['total'];
$totalPages = $dataset['total_pages'];

$pageTitle = 'Tin tuyển dụng của bạn';
$employerNavActive = 'jobs';
$employerCompanyName = $employer['company_name'];
$employerProfileUrl = BASE_URL . '/employer/show.php?id=' . $employerId;

$statusBadges = [
    'draft' => ['label' => 'Nháp', 'class' => 'badge bg-secondary'],
    'published' => ['label' => 'Đang đăng', 'class' => 'badge bg-success'],
    'closed' => ['label' => 'Đã đóng', 'class' => 'badge bg-dark'],
];

require_once __DIR__ . '/includes/header.php';

$flash = $_SESSION['employer_job_flash'] ?? null;
unset($_SESSION['employer_job_flash']);
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
  <div>
    <span class="text-muted text-uppercase small fw-semibold">Quản lý tin tuyển dụng</span>
    <h2 class="h4 mb-0">Danh sách tin đã tạo</h2>
  </div>
  <a class="btn btn-primary" href="<?= BASE_URL ?>/employer/admin/job_edit.php"><i class="fa-solid fa-circle-plus me-2"></i>Đăng tin mới</a>
</div>

<div class="ea-card">
  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($flash['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th scope="col">#</th>
          <th scope="col">Tiêu đề</th>
          <th scope="col">Trạng thái</th>
          <th scope="col">Địa điểm</th>
          <th scope="col">Lương</th>
          <th scope="col" class="d-none d-md-table-cell">Số lượng</th>
          <th scope="col" class="d-none d-md-table-cell">Hạn nộp</th>
          <th scope="col">Ứng viên</th>
          <th scope="col" class="text-end">Hành động</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($jobs)): ?>
          <tr>
            <td colspan="7" class="text-center text-muted py-5">
              <i class="fa-regular fa-rectangle-list fa-2x mb-3"></i>
              <p class="mb-0">Bạn chưa có tin tuyển dụng nào. Bắt đầu đăng tin đầu tiên ngay!</p>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($jobs as $job): ?>
            <?php
              $status = $job['status'] ?? 'draft';
              $badge = $statusBadges[$status] ?? ['label' => ucfirst($status), 'class' => 'badge bg-secondary'];
              $cRes = $jobModel->getApplicants((int)$job['id']);
              $cCount = $cRes ? $cRes->num_rows : 0;
              if ($cRes) {
                  $cRes->free();
              }
            ?>
            <tr>
              <td class="fw-semibold">#<?= (int)$job['id'] ?></td>
              <td>
                <div class="fw-semibold mb-1"><a class="text-decoration-none" href="<?= BASE_URL ?>/job/share/view.php?id=<?= (int)$job['id'] ?>" target="_blank" rel="noopener"><?= htmlspecialchars($job['title']) ?></a></div>
                <div class="text-muted small">Cập nhật: <?= htmlspecialchars(date('d/m/Y', strtotime($job['updated_at'] ?? $job['created_at'] ?? 'now'))) ?></div>
              </td>
              <td><span class="<?= $badge['class'] ?>"><?= $badge['label'] ?></span></td>
              <td><?= htmlspecialchars($job['location'] ?: 'Toàn quốc') ?></td>
              <td><?= htmlspecialchars($job['salary'] ?: 'Thỏa thuận') ?></td>
              <td class="d-none d-md-table-cell text-muted small"><?= isset($job['quantity']) && $job['quantity'] ? (int)$job['quantity'] : '—' ?></td>
              <td class="d-none d-md-table-cell text-muted small"><?= $job['deadline'] ? htmlspecialchars(date('d/m/Y', strtotime($job['deadline']))) : '—' ?></td>
              <td><a href="<?= BASE_URL ?>/employer/admin/applications.php?job_id=<?= (int)$job['id'] ?>" class="text-decoration-none"><?= $cCount ?> hồ sơ</a></td>
              <td class="text-end">
                <div class="d-flex flex-wrap justify-content-end gap-2">
                  <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/job/share/view.php?id=<?= (int)$job['id'] ?>" target="_blank" rel="noopener">Xem</a>
                  <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/employer/admin/job_edit.php?id=<?= (int)$job['id'] ?>">Sửa</a>
                  <form method="post" action="<?= BASE_URL ?>/employer/admin/job_delete.php" onsubmit="return confirm('Xác nhận xóa tin này?');" class="d-inline">
                    <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
    <nav class="mt-4" aria-label="Pagination">
      <ul class="pagination justify-content-end">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
