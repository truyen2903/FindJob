<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Employer.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
  header('Location: ' . BASE_URL . '/403.php');
  exit;
}

$employerModel = new Employer();
$id = (int)($_GET['id'] ?? 0);
$res = $employerModel->conn->query("SELECT * FROM employers WHERE id = $id");
$employer = $res->fetch_assoc();
if (!$employer) {
  header('Location: ' . ADMIN_URL . '/employers/employers.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $stmt = $employerModel->conn->prepare("DELETE FROM employers WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  header('Location: ' . ADMIN_URL . '/employers/employers.php');
  exit;
}

ob_start();
?>
<div class="pagetitle">
  <h1>Xoá nhà tuyển dụng</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Trang chủ</a></li>
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/employers/employers.php">Nhà tuyển dụng</a></li>
      <li class="breadcrumb-item active">Xoá</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="card p-4 shadow-sm">
    <h5>Bạn có chắc muốn xoá nhà tuyển dụng này?</h5>
    <ul>
      <li><strong>ID:</strong> <?= $employer['id'] ?></li>
      <li><strong>Tên công ty:</strong> <?= htmlspecialchars($employer['company_name']) ?></li>
      <li><strong>Website:</strong> <?= htmlspecialchars($employer['website']) ?></li>
    </ul>
    <form method="POST" class="mt-3">
      <a href="<?= ADMIN_URL ?>/employers/employers.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Huỷ
      </a>
      <button type="submit" class="btn btn-danger">
        <i class="bi bi-trash"></i> Xác nhận xoá
      </button>
    </form>
  </div>
</section>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
