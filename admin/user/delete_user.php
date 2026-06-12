<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/User.php';
require_once dirname(__DIR__, 2) . '/app/helpers/avatar.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
  header('Location: ' . BASE_URL . '/403.php');
  exit;
}

$userModel = new User();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user = $userModel->getById($id);

if (!$user) {
  header('Location: ' . ADMIN_URL . '/user/users.php');
  exit;
}

// Xử lý khi xác nhận xóa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Xóa ảnh cũ nếu có
  if (!empty($user['avatar_path'])) {
    $old = dirname(__DIR__, 2) . '/public/' . $user['avatar_path'];
    if (file_exists($old)) @unlink($old);
    $oldThumb = preg_replace('/(\.[a-zA-Z0-9]+)$/', '_thumb$1', $old);
    if (file_exists($oldThumb)) @unlink($oldThumb);
  }

  // Xóa user
  $stmt = $userModel->conn->prepare("DELETE FROM users WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();

  header('Location: ' . ADMIN_URL . '/user/users.php');
  exit;
}

// Giao diện hiển thị xác nhận
ob_start();
?>

<div class="pagetitle">
  <h1>Xóa người dùng</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Trang chủ</a></li>
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/user/users.php">Người dùng</a></li>
      <li class="breadcrumb-item active">Xóa</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="card p-4 shadow-sm">
    <h5 class="text-danger mb-3"><i class="bi bi-exclamation-triangle"></i> Xác nhận xóa người dùng</h5>
    <p>Bạn có chắc chắn muốn xóa tài khoản <strong><?= htmlspecialchars($user['name']) ?></strong> (<?= htmlspecialchars($user['email']) ?>)?</p>

    <?php if (!empty($user['avatar_path'])): ?>
      <p><img src="<?= BASE_URL ?>/<?= htmlspecialchars($user['avatar_path']) ?>" width="80" height="80" style="object-fit:cover;border-radius:8px;"></p>
    <?php endif; ?>

    <form method="POST" class="text-end">
      <a href="<?= ADMIN_URL ?>/user/users.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Quay lại
      </a>
      <button type="submit" class="btn btn-danger">
        <i class="bi bi-trash"></i> Xóa người dùng
      </button>
    </form>
  </div>
</section>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
