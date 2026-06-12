<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/User.php';
require_once dirname(__DIR__, 2) . '/app/helpers/avatar.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
  header('Location: ' . BASE_URL . '/403.php');
  exit;
}

$userModel = new User();

$roleOptions = [
  1 => 'Admin',
  2 => 'Nhà tuyển dụng',
  3 => 'Ứng viên'
];

$errors = [];
$values = [
  'name' => '',
  'email' => '',
  'role_id' => ''
];

$pendingAvatarPath = null;
$avatarWarning = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $values['name'] = trim((string)($_POST['name'] ?? ''));
  $values['email'] = trim((string)($_POST['email'] ?? ''));
  $values['role_id'] = (string)($_POST['role_id'] ?? '');
  $submittedPassword = (string)($_POST['password'] ?? '');

  if ($values['name'] === '') {
    $errors[] = 'Vui lòng nhập tên hiển thị cho người dùng.';
  }

  if ($values['email'] === '') {
    $errors[] = 'Vui lòng nhập email đăng nhập.';
  } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email không hợp lệ. Vui lòng kiểm tra lại.';
  } elseif ($userModel->findByEmail($values['email'])) {
    $errors[] = 'Email đã tồn tại trong hệ thống.';
  }

  if ($submittedPassword === '') {
    $errors[] = 'Vui lòng nhập mật khẩu tạm thời cho người dùng.';
  } elseif (strlen($submittedPassword) < 8) {
    $errors[] = 'Mật khẩu cần có tối thiểu 8 ký tự.';
  }

  $roleId = (int)$values['role_id'];
  if (!array_key_exists($roleId, $roleOptions)) {
    $errors[] = 'Vui lòng chọn vai trò hợp lệ cho tài khoản.';
  }

  $hasAvatarFile = isset($_FILES['avatar']) && is_array($_FILES['avatar']) && (int)($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
  if ($hasAvatarFile && (int)$_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = avatar_upload_error_message((int)$_FILES['avatar']['error']);
  }

  if (empty($errors) && $hasAvatarFile) {
    $avatarError = null;
    $pendingAvatarPath = handle_avatar_upload($_FILES['avatar'], $avatarError);
    if ($pendingAvatarPath === false) {
      $errors[] = $avatarError ?? 'Không thể xử lý ảnh đại diện được tải lên.';
      $pendingAvatarPath = null;
    }
  }

  if (empty($errors)) {
    $newId = $userModel->create($values['email'], $submittedPassword, $roleId, $values['name']);

    if ($newId) {
      if ($pendingAvatarPath !== null) {
        if (!$userModel->setAvatar($newId, $pendingAvatarPath)) {
          remove_avatar_file($pendingAvatarPath);
          $pendingAvatarPath = null;
          $avatarWarning = 'Không thể lưu ảnh đại diện cho tài khoản vừa tạo.';
        }
      }

      $_SESSION['admin_user_flash'] = [
        'type' => $avatarWarning ? 'warning' : 'success',
        'message' => $avatarWarning ? 'Tạo người dùng thành công nhưng ảnh đại diện chưa được lưu.' : 'Tạo người dùng mới thành công.'
      ];
      header('Location: ' . ADMIN_URL . '/user/users.php');
      exit;
    }

    if ($pendingAvatarPath !== null) {
      remove_avatar_file($pendingAvatarPath);
      $pendingAvatarPath = null;
    }

    $errors[] = 'Không thể tạo người dùng. Vui lòng thử lại sau.';
  }
}

ob_start();
?>

<div class="pagetitle">
  <h1>Thêm người dùng</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Trang chủ</a></li>
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/user/users.php">Người dùng</a></li>
      <li class="breadcrumb-item active">Thêm mới</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="row g-4">
    <div class="col-12">
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger shadow-sm">
          <h6 class="alert-heading mb-2">Không thể lưu tài khoản</h6>
          <ul class="mb-0 ps-3">
            <?php foreach ($errors as $error): ?>
              <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <form method="POST" enctype="multipart/form-data" class="row g-4" novalidate>
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center d-flex flex-column justify-content-center">
          <div id="avatarPreview" class="mx-auto d-flex align-items-center justify-content-center rounded-circle border bg-light" style="width: 140px; height: 140px; overflow: hidden;">
            <i class="bi bi-person fs-1 text-secondary"></i>
          </div>
          <p class="text-muted small mb-3 mt-3">Ảnh sẽ được hiển thị trong bảng quản trị và các khu vực yêu cầu danh tính.</p>
          <div class="mb-3 text-start">
            <label for="avatar" class="form-label fw-semibold">Ảnh đại diện (tùy chọn)</label>
            <input type="file" name="avatar" id="avatar" accept="image/png,image/jpeg,image/gif,image/webp" class="form-control">
            <div class="form-text">PNG, JPG, GIF, WEBP · tối đa 2MB.</div>
          </div>
          <div class="border rounded p-3 text-start bg-light-subtle small">
            <span class="fw-semibold mb-2 d-block">Mẹo:</span>
            <ul class="mb-0 ps-3">
              <li>Sử dụng nền đơn sắc, khuôn mặt rõ nét.</li>
              <li>Kích thước đề xuất tối thiểu 300x300px.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
            <div>
              <h2 class="h5 mb-1">Thông tin tài khoản</h2>
              <p class="text-muted mb-0">Điền thông tin chính xác để gửi thông tin đăng nhập cho người dùng.</p>
            </div>
            <span class="badge bg-success bg-opacity-10 text-success">Bước 1 / 1</span>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label for="name" class="form-label fw-semibold">Tên hiển thị <span class="text-danger">*</span></label>
              <input type="text" name="name" id="name" class="form-control" placeholder="Ví dụ: Nguyễn Văn A" value="<?= htmlspecialchars($values['name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label for="email" class="form-label fw-semibold">Email đăng nhập <span class="text-danger">*</span></label>
              <input type="email" name="email" id="email" class="form-control" placeholder="user@jobfind.vn" value="<?= htmlspecialchars($values['email']) ?>" required>
            </div>
            <div class="col-md-6">
              <label for="password" class="form-label fw-semibold">Mật khẩu tạm thời <span class="text-danger">*</span></label>
              <input type="password" name="password" id="password" class="form-control" placeholder="Tối thiểu 8 ký tự" required>
              <div class="form-text">Người dùng nên đổi mật khẩu sau lần đăng nhập đầu tiên.</div>
            </div>
            <div class="col-md-6">
              <label for="role_id" class="form-label fw-semibold">Vai trò hệ thống <span class="text-danger">*</span></label>
              <select name="role_id" id="role_id" class="form-select" required>
                <option value="">-- Chọn vai trò --</option>
                <?php foreach ($roleOptions as $value => $label): ?>
                  <option value="<?= (int)$value ?>" <?= (int)$values['role_id'] === (int)$value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Quyền hạn sẽ quyết định khu vực quản trị có thể truy cập.</div>
            </div>
          </div>

          <hr class="my-4">

          <div class="d-flex justify-content-end gap-2">
            <a href="<?= ADMIN_URL ?>/user/users.php" class="btn btn-light">
              <i class="bi bi-arrow-left"></i> Quay lại danh sách
            </a>
            <button type="submit" class="btn btn-success">
              <i class="bi bi-person-plus"></i> Tạo người dùng
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>
</section>

<script>
(function() {
  const fileInput = document.getElementById('avatar');
  const preview = document.getElementById('avatarPreview');
  if (!fileInput || !preview) {
    return;
  }

  const renderPlaceholder = function() {
    preview.innerHTML = '<i class="bi bi-person fs-1 text-secondary"></i>';
  };

  fileInput.addEventListener('change', function(event) {
    const file = event.target.files && event.target.files[0];
    if (!file) {
      renderPlaceholder();
      return;
    }
    if (!file.type || !file.type.startsWith('image/')) {
      renderPlaceholder();
      return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
      const img = document.createElement('img');
      img.src = e.target.result;
      img.alt = 'Xem trước ảnh đại diện';
      img.style.width = '100%';
      img.style.height = '100%';
      img.style.objectFit = 'cover';
      preview.innerHTML = '';
      preview.appendChild(img);
    };
    reader.readAsDataURL(file);
  });
})();
</script>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
