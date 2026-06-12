<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Role.php';
require_once dirname(__DIR__, 2) . '/app/models/Permission.php';

if (empty($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$roleModel = new Role();
$permissionModel = new Permission();
$allPermissions = $permissionModel->getAll();

$roleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $roleId > 0;
$role = $editing ? $roleModel->getRoleById($roleId) : null;
if ($editing && !$role) {
    $_SESSION['role_flash'] = [
        'type' => 'danger',
        'message' => 'Không tìm thấy vai trò cần chỉnh sửa.'
    ];
    header('Location: ' . ADMIN_URL . '/roles/index.php');
    exit;
}

$currentPermissions = $editing ? $roleModel->getPermissionIds($roleId) : [];
$formData = [
    'name' => $role['name'] ?? '',
    'description' => $role['description'] ?? '',
    'permissions' => $currentPermissions,
];
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $permissionIds = isset($_POST['permissions']) && is_array($_POST['permissions'])
        ? array_map('intval', $_POST['permissions'])
        : [];

    if ($name === '') {
        $formErrors['name'] = 'Tên vai trò là bắt buộc.';
    }

    if (empty($formErrors)) {
        if ($editing) {
            $updated = $roleModel->updateRole($roleId, $name, $description === '' ? null : $description);
            $targetRoleId = $roleId;
        } else {
            $newId = $roleModel->createRole($name, $description === '' ? null : $description);
            $updated = $newId !== null;
            $targetRoleId = $newId;
        }

        if ($updated && $targetRoleId) {
            $roleModel->syncPermissions($targetRoleId, $permissionIds);
            $_SESSION['role_flash'] = [
                'type' => 'success',
                'message' => $editing ? 'Cập nhật vai trò thành công.' : 'Tạo vai trò mới thành công.'
            ];
            header('Location: ' . ADMIN_URL . '/roles/index.php');
            exit;
        }

        $formErrors['general'] = 'Không thể lưu vai trò. Vui lòng thử lại.';
    }

    $formData['name'] = $name;
    $formData['description'] = $description;
    $formData['permissions'] = $permissionIds;
}

$pageTitle = $editing ? 'Chỉnh sửa vai trò' : 'Tạo vai trò mới';

ob_start();
?>
<div class="pagetitle">
  <h1><?= htmlspecialchars($pageTitle) ?></h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Trang chủ</a></li>
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/roles/index.php">Vai trò &amp; quyền</a></li>
      <li class="breadcrumb-item active"><?= htmlspecialchars($pageTitle) ?></li>
    </ol>
  </nav>
</div>

<a class="btn btn-link text-decoration-none ps-0 mb-3" href="<?= ADMIN_URL ?>/roles/index.php">
  <i class="bi bi-arrow-left me-2"></i>Quay lại danh sách vai trò
</a>

<section class="section">
  <div class="card">
    <div class="card-body">
      <h5 class="card-title mb-3">Thông tin vai trò</h5>

      <?php if (!empty($formErrors['general'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($formErrors['general']) ?></div>
      <?php endif; ?>

      <form method="post" class="row g-4">
        <div class="col-lg-6">
          <label for="name" class="form-label">Tên vai trò <span class="text-danger">*</span></label>
          <input type="text" id="name" name="name" class="form-control <?= isset($formErrors['name']) ? 'is-invalid' : '' ?>"
                 value="<?= htmlspecialchars($formData['name']) ?>" required>
          <?php if (isset($formErrors['name'])): ?>
            <div class="invalid-feedback"><?= htmlspecialchars($formErrors['name']) ?></div>
          <?php endif; ?>
        </div>
        <div class="col-lg-6">
          <label for="description" class="form-label">Mô tả</label>
          <input type="text" id="description" name="description" class="form-control"
                 value="<?= htmlspecialchars($formData['description']) ?>" placeholder="Ví dụ: Quản lý nội dung, hỗ trợ khách hàng...">
        </div>

        <div class="col-12">
          <h5 class="card-title">Phân quyền chức năng</h5>
          <?php if (empty($allPermissions)): ?>
            <div class="alert alert-warning mb-0">Chưa có quyền hệ thống nào được khai báo.</div>
          <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
              <?php foreach ($allPermissions as $permission): ?>
                <?php $pid = (int)$permission['id']; ?>
                <div class="col">
                  <div class="form-check border rounded-3 p-3 h-100">
                    <input class="form-check-input" type="checkbox" id="perm-<?= $pid ?>" name="permissions[]" value="<?= $pid ?>"
                      <?= in_array($pid, $formData['permissions'], true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="perm-<?= $pid ?>">
                      <span class="fw-semibold d-block text-uppercase small"><i class="bi bi-lock me-1"></i><?= htmlspecialchars($permission['name']) ?></span>
                      <span class="text-muted small"><?= htmlspecialchars($permission['description'] ?? 'Chưa có mô tả') ?></span>
                    </label>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="col-12 text-end">
          <a href="<?= ADMIN_URL ?>/roles/index.php" class="btn btn-outline-secondary">Huỷ</a>
          <button type="submit" class="btn btn-primary ms-2">
            <i class="bi bi-save me-1"></i>Lưu vai trò
          </button>
        </div>
      </form>
    </div>
  </div>
</section>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
?>
