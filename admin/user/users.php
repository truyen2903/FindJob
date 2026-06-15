<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/User.php';
require_once dirname(__DIR__, 2) . '/app/helpers/avatar.php';

// Chỉ cho phép admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$userModel = new User();
$queryError = null;

$userFlash = $_SESSION['admin_user_flash'] ?? null;
if (isset($_SESSION['admin_user_flash'])) {
  unset($_SESSION['admin_user_flash']);
}

// Đổi vai trò nhanh (qua GET)
if (isset($_GET['set_role']) && isset($_GET['user_id'])) {
    $uid = (int)$_GET['user_id'];
    $rid = (int)$_GET['set_role'];
    $userModel->assignRole($uid, $rid);
    header('Location: ' . ADMIN_URL . '/user/users.php');
    exit;
}

// Lấy danh sách người dùng
$res = $userModel->conn->query("
    SELECT u.id, u.email, u.name, u.role_id, u.avatar_path, r.name AS role_name
    FROM users u
    JOIN roles r ON r.id = u.role_id
    ORDER BY u.id
");

if ($res === false) {
    $queryError = $userModel->conn->error;
    // Tránh lỗi fetch_assoc() trên bool
    $res = $userModel->conn->query("
        SELECT u.id, u.email, u.name, u.role_id, u.avatar_path, r.name AS role_name
        FROM users u
        JOIN roles r ON r.id = u.role_id
        WHERE 1=0
    ");
}

// ------------------------------------------------------
// GIAO DIỆN GỘP TRỰC TIẾP TRONG $content
// ------------------------------------------------------
ob_start();
?>

<div class="pagetitle">
  <h1>Quản lý người dùng</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Trang chủ</a></li>
      <li class="breadcrumb-item active">Người dùng</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="card p-3 shadow-sm">

    <?php if ($userFlash): ?>
      <div class="alert alert-<?= htmlspecialchars($userFlash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($userFlash['message'] ?? '') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
      </div>
    <?php endif; ?>

    <?php if ($queryError): ?>
      <div class="alert alert-danger">
        <strong>Lỗi truy vấn CSDL:</strong> <?= htmlspecialchars($queryError) ?>
      </div>
    <?php endif; ?>

    <div class="text-end mb-3">
      <a href="<?= ADMIN_URL ?>/user/add_user.php" class="btn btn-success" >
        <i class="bi bi-plus-circle"></i> Thêm người dùng
      </a>
    </div>

    <table class="table table-bordered table-hover align-middle bg-white">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>Ảnh</th>
          <th>Email</th>
          <th>Tên</th>
          <th>Vai trò</th>
          <th width="180">Thao tác</th>
        </tr>
      </thead>
      <tbody>
      <?php while ($u = $res->fetch_assoc()): ?>
        <?php
          $avatarSrc = '';
          if (!empty($u['avatar_path'])) {
            $avatarSrc = filter_var($u['avatar_path'], FILTER_VALIDATE_URL)
              ? $u['avatar_path']
              : BASE_URL . '/' . ltrim($u['avatar_path'], '/');
          }
        ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td style="width:60px;">
            <?php if ($avatarSrc): ?>
              <img src="<?= htmlspecialchars($avatarSrc) ?>" class="rounded-circle" width="48" height="48" style="object-fit:cover;">
            <?php else: ?>
              <span class="text-muted small">Không có</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><?= htmlspecialchars($u['name'] ?: 'Chưa cập nhật') ?></td>
          <td><?= htmlspecialchars($u['role_name']) ?></td>
          <td>
            <div class="btn-group">
              <a href="<?= ADMIN_URL ?>/user/edit_user.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-pencil"></i> Sửa
              </a>
              <a href="<?= ADMIN_URL ?>/user/delete_user.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-danger"
                 onclick="return confirm('Xác nhận xóa người dùng này?')">
                <i class="bi bi-trash"></i> Xóa
              </a>
            </div>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>

  </div>
</section>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
