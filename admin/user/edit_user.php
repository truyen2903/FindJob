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
$uploadError = null;
$message = '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user = $userModel->getById($id);
if (!$user) {
    header('Location: ' . ADMIN_URL . '/user/users.php');
    exit;
}

// Xử lý cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $role_id = (int)($_POST['role_id'] ?? $user['role_id']);

  $stmt = $userModel->conn->prepare("UPDATE users SET name = ?, role_id = ? WHERE id = ?");
  $stmt->bind_param("sii", $name, $role_id, $id);
  $stmt->execute();

  $shouldRedirect = true;
  if (!empty($_FILES['avatar']) && isset($_FILES['avatar']['error']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
      $err = null;
      $relative = handle_avatar_upload($_FILES['avatar'], $err);
      if ($relative) {
        if (!empty($user['avatar_path'])) {
          $old = dirname(__DIR__, 2) . '/public/' . ltrim($user['avatar_path'], '/');
          if (file_exists($old)) @unlink($old);
          $oldThumb = preg_replace('/(\.[a-zA-Z0-9]+)$/', '_thumb$1', $old);
          if (file_exists($oldThumb)) @unlink($oldThumb);
        }
        $userModel->setAvatar($id, $relative);
        $message = 'Đã cập nhật ảnh đại diện mới.';
      } else {
        $uploadError = $err;
        $shouldRedirect = false;
      }
    } else {
      $uploadError = avatar_upload_error_message($_FILES['avatar']['error']);
      $shouldRedirect = false;
    }
  } else {
    $message = 'Đã lưu thay đổi.';
  }

  if ($shouldRedirect && !$uploadError) {
    header('Location: ' . ADMIN_URL . '/user/users.php');
    exit;
  }

  $user = $userModel->getById($id);
}

// ===== GIAO DIỆN CHỈNH SỬA =====
ob_start();
?>
<div class="pagetitle">
  <h1>Sửa người dùng</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Trang chủ</a></li>
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/user/users.php">Người dùng</a></li>
      <li class="breadcrumb-item active">Sửa</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="card p-4 shadow-sm">
    <?php if ($message): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if ($uploadError): ?>
      <div class="alert alert-danger">Lỗi upload ảnh: <?= htmlspecialchars($uploadError) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label">Tên người dùng</label>
          <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Email (không đổi)</label>
          <input type="email" value="<?= htmlspecialchars($user['email']) ?>" class="form-control" readonly>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label">Vai trò</label>
          <select name="role_id" class="form-select" required>
            <option value="1" <?= $user['role_id']==1?'selected':'' ?>>Admin</option>
            <option value="2" <?= $user['role_id']==2?'selected':'' ?>>Nhà tuyển dụng</option>
            <option value="3" <?= $user['role_id']==3?'selected':'' ?>>Ứng viên</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Ảnh đại diện (tuỳ chọn)</label>
          <input type="file" name="avatar" accept="image/*" class="form-control">
          <?php if ($user['avatar_path']): ?>
            <?php
              $avatarSrc = filter_var($user['avatar_path'], FILTER_VALIDATE_URL)
                ? $user['avatar_path']
                : BASE_URL . '/' . ltrim($user['avatar_path'], '/');
            ?>
            <div class="mt-2">
              <img src="<?= htmlspecialchars($avatarSrc) ?>" width="100" class="rounded">
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="text-end">
        <a href="<?= ADMIN_URL ?>/user/users.php" class="btn btn-secondary">
          <i class="bi bi-arrow-left"></i> Quay lại
        </a>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save"></i> Lưu thay đổi
        </button>
      </div>
    </form>
  </div>
</section>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
