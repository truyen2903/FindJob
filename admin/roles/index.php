<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Role.php';
require_once dirname(__DIR__, 2) . '/app/models/Permission.php';
require_once dirname(__DIR__, 2) . '/app/models/User.php';

if (empty($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$roleModel = new Role();
$permissionModel = new Permission();
$userModel = new User();

$roles = $roleModel->getAllWithUserCounts();
$permissions = $permissionModel->getAll();
$totalRoles = count($roles);
$totalPermissions = count($permissions);
$totalUsers = $userModel->countAll();

$roleFlash = $_SESSION['role_flash'] ?? null;
unset($_SESSION['role_flash']);

$systemRoles = [1 => 'admin', 2 => 'employer', 3 => 'candidate'];

ob_start();
?>
<div class="pagetitle">
  <h1>Quản lý vai trò &amp; phân quyền</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Trang chủ</a></li>
      <li class="breadcrumb-item active">Vai trò &amp; quyền</li>
    </ol>
  </nav>
</div>

<section class="section">
  <?php if ($roleFlash): ?>
    <div class="alert alert-<?= htmlspecialchars($roleFlash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($roleFlash['message'] ?? '') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
    </div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card info-card">
        <div class="card-body">
          <h5 class="card-title">Tổng số vai trò</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-people"></i>
            </div>
            <div class="ps-3">
              <h6><?= number_format($totalRoles) ?></h6>
              <span class="text-muted small">bao gồm hệ thống &amp; tuỳ chỉnh</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card info-card">
        <div class="card-body">
          <h5 class="card-title">Tổng số quyền</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-shield-lock"></i>
            </div>
            <div class="ps-3">
              <h6><?= number_format($totalPermissions) ?></h6>
              <span class="text-muted small">module chức năng</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card info-card">
        <div class="card-body">
          <h5 class="card-title">Tổng người dùng</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-person-badge"></i>
            </div>
            <div class="ps-3">
              <h6><?= number_format($totalUsers) ?></h6>
              <span class="text-muted small">liên kết với vai trò</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-3">
        <h5 class="card-title mb-0">Danh sách vai trò</h5>
        <a href="<?= ADMIN_URL ?>/roles/manage.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Thêm vai trò</a>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Tên vai trò</th>
              <th>Mô tả</th>
              <th>Người dùng</th>
              <th>Quyền</th>
              <th class="text-end">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($roles)): ?>
              <tr>
                <td colspan="6" class="text-center text-muted py-4">Chưa có vai trò nào.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($roles as $role): ?>
                <?php
                  $roleId = (int)$role['id'];
                  $permissionCount = count($roleModel->getPermissionIds($roleId));
                  $isSystemRole = array_key_exists($roleId, $systemRoles);
                ?>
                <tr>
                  <td>#<?= $roleId ?></td>
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars($role['name']) ?></div>
                    <?php if ($isSystemRole): ?>
                      <span class="badge bg-light text-dark border">Hệ thống</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($role['description'] ?? '—') ?></td>
                  <td><?= number_format((int)($role['user_count'] ?? 0)) ?></td>
                  <td><?= number_format($permissionCount) ?></td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <a href="<?= ADMIN_URL ?>/roles/manage.php?id=<?= $roleId ?>" class="btn btn-outline-primary">
                        <i class="bi bi-pencil"></i> Sửa
                      </a>
                      <?php if (!$isSystemRole): ?>
                        <form action="<?= ADMIN_URL ?>/roles/delete.php" method="post" onsubmit="return confirm('Xác nhận xoá vai trò này?');">
                          <input type="hidden" name="role_id" value="<?= $roleId ?>">
                          <button type="submit" class="btn btn-outline-danger">
                            <i class="bi bi-trash"></i> Xoá
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

  <div class="card mt-4">
    <div class="card-body">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
        <h5 class="card-title mb-0">Danh mục quyền hệ thống</h5>
        <form class="row g-2" method="post" action="<?= ADMIN_URL ?>/roles/save_permission.php">
          <div class="col-auto">
            <input type="text" name="name" class="form-control" placeholder="Tên quyền (ví dụ: manage_roles)" required>
          </div>
          <div class="col">
            <input type="text" name="description" class="form-control" placeholder="Mô tả quyền">
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-outline-primary">
              <i class="bi bi-plus-circle me-1"></i>Thêm quyền
            </button>
          </div>
        </form>
      </div>

      <?php if (empty($permissions)): ?>
        <p class="text-muted mb-0">Chưa có quyền nào. Hãy thêm quyền mới bằng biểu mẫu phía trên.</p>
      <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
          <?php foreach ($permissions as $permission): ?>
            <div class="col">
              <div class="border rounded-3 p-3 h-100">
                <div class="fw-semibold text-primary text-uppercase small mb-1">
                  <?= htmlspecialchars($permission['name']) ?>
                </div>
                <div class="text-muted small mb-0">
                  <?= htmlspecialchars($permission['description'] ?? 'Chưa có mô tả') ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
?>
