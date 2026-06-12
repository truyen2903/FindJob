<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Candidate.php';

if (!isset($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$candidateModel = new Candidate();
$conn = $candidateModel->conn;

$userId = (int)($_GET['user_id'] ?? 0);
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    header('Location: ' . ADMIN_URL . '/candidates/index.php');
    exit;
}

$sql = "
    SELECT
        u.id AS user_id,
        u.name AS full_name,
        u.email,
        u.phone,
        u.avatar_path,
        u.created_at,
        c.id AS candidate_id,
        c.headline,
        c.location,
        c.cv_path,
        c.profile_picture,
        c.updated_at
    FROM users u
    LEFT JOIN candidates c ON c.user_id = u.id
    WHERE u.id = ? AND u.role_id = 3
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    header('Location: ' . ADMIN_URL . '/candidates/index.php');
    exit;
}

$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$candidate = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$candidate) {
    header('Location: ' . ADMIN_URL . '/candidates/index.php');
    exit;
}

if ($candidate['user_id'] === $currentUserId) {
    header('Location: ' . ADMIN_URL . '/candidates/index.php');
    exit;
}

$errorMessage = '';

function jf_admin_remove_public_file(?string $path): void {
  if (empty($path)) {
    return;
  }

  $normalized = str_replace('\\', '/', $path);
  $normalized = ltrim($normalized, '/');
  if ($normalized === '') {
    return;
  }

  $publicRoot = realpath(dirname(__DIR__, 2) . '/public');
  if ($publicRoot === false) {
    return;
  }

  $target = $publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
  $targetReal = realpath($target);
  if ($targetReal !== false) {
    if (strpos($targetReal, $publicRoot) !== 0) {
      return;
    }
    if (is_file($targetReal)) {
      @unlink($targetReal);
    }
    return;
  }

  if (is_file($target)) {
    @unlink($target);
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    $deleteStmt = $conn->prepare('DELETE FROM users WHERE id = ?');

    if ($deleteStmt === false) {
        $conn->rollback();
        $errorMessage = 'Không thể xóa ứng viên vào lúc này. Vui lòng thử lại sau.';
    } else {
        $deleteStmt->bind_param('i', $userId);
        $deleteStmt->execute();

        if ($deleteStmt->affected_rows > 0) {
            $conn->commit();
            $deleteStmt->close();

            jf_admin_remove_public_file($candidate['cv_path'] ?? null);
            jf_admin_remove_public_file($candidate['profile_picture'] ?? null);

            if (!empty($candidate['avatar_path'])) {
                jf_admin_remove_public_file($candidate['avatar_path']);
                $thumbPath = preg_replace('/(\.[a-zA-Z0-9]+)$/', '_thumb$1', $candidate['avatar_path']);
                jf_admin_remove_public_file($thumbPath);
            }

            header('Location: ' . ADMIN_URL . '/candidates/index.php?deleted=1');
            exit;
        }

        $conn->rollback();
        $deleteStmt->close();
        $errorMessage = 'Không thể xóa ứng viên vào lúc này. Vui lòng thử lại sau.';
    }
}

$fullName = $candidate['full_name'] ?: 'Ứng viên JobFind';
$headline = $candidate['headline'] ?: 'Chưa cập nhật';
$location = $candidate['location'] ?: 'Chưa cập nhật';
$createdLabel = $candidate['created_at'] ? date('d/m/Y', strtotime($candidate['created_at'])) : 'Không rõ';
$cvStatus = !empty($candidate['cv_path']) ? 'Đã tải lên' : 'Chưa có CV';

ob_start();
?>
<div class="pagetitle">
  <h1>Xóa ứng viên</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Trang chủ</a></li>
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/candidates/index.php">Ứng viên</a></li>
      <li class="breadcrumb-item active">Xóa</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="card p-4 shadow-sm">
    <h5 class="text-danger mb-3"><i class="bi bi-exclamation-triangle"></i> Xác nhận xóa ứng viên</h5>
    <?php if ($errorMessage): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <p>Bạn sắp xóa hồ sơ và tài khoản của ứng viên <strong><?= htmlspecialchars($fullName) ?></strong>.</p>
    <ul class="mb-4">
      <li><strong>Email:</strong> <?= htmlspecialchars($candidate['email']) ?></li>
      <li><strong>Tiêu đề:</strong> <?= htmlspecialchars($headline) ?></li>
      <li><strong>Địa điểm:</strong> <?= htmlspecialchars($location) ?></li>
      <li><strong>Tạo ngày:</strong> <?= htmlspecialchars($createdLabel) ?></li>
      <li><strong>CV:</strong> <?= htmlspecialchars($cvStatus) ?></li>
    </ul>

    <div class="alert alert-warning small">
      <i class="bi bi-info-circle"></i> Khi xóa, toàn bộ dữ liệu liên quan (bao gồm CV, đơn ứng tuyển, việc đã lưu) của ứng viên sẽ bị loại bỏ vĩnh viễn.
    </div>

    <form method="POST" class="text-end">
      <a href="<?= ADMIN_URL ?>/candidates/index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Hủy
      </a>
      <button type="submit" class="btn btn-danger">
        <i class="bi bi-trash"></i> Xác nhận xóa ứng viên
      </button>
    </form>
  </div>
</section>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
