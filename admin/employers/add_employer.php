<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Employer.php';
require_once dirname(__DIR__, 2) . '/app/models/User.php';
require_once dirname(__DIR__, 2) . '/app/helpers/company_logo.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
  header('Location: ' . BASE_URL . '/403.php');
  exit;
}

$employerModel = new Employer();
$userModel = new User();

$errors = [];
$values = [
  'user_id' => '',
  'company_name' => '',
  'website' => '',
  'address' => '',
  'about' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $values['user_id'] = trim((string)($_POST['user_id'] ?? ''));
  $values['company_name'] = trim((string)($_POST['company_name'] ?? ''));
  $values['website'] = trim((string)($_POST['website'] ?? ''));
  $values['address'] = trim((string)($_POST['address'] ?? ''));
  $values['about'] = trim((string)($_POST['about'] ?? ''));

  $userId = (int)$values['user_id'];
  if ($userId <= 0) {
    $errors[] = 'Vui lòng chọn tài khoản nhà tuyển dụng.';
  } else {
    $user = $userModel->getById($userId);
    if (!$user || (int)($user['role_id'] ?? 0) !== 2) {
      $errors[] = 'Tài khoản được chọn không hợp lệ hoặc không phải nhà tuyển dụng.';
    } elseif ($employerModel->getByUserId($userId)) {
      $errors[] = 'Tài khoản này đã có hồ sơ doanh nghiệp.';
    }
  }

  if ($values['company_name'] === '') {
    $errors[] = 'Vui lòng nhập tên công ty.';
  }

  if ($values['website'] !== '' && !filter_var($values['website'], FILTER_VALIDATE_URL)) {
    $errors[] = 'Đường dẫn website không hợp lệ. Vui lòng nhập dạng https://example.com.';
  }

  $logoPath = null;
  if (isset($_FILES['company_logo']) && is_array($_FILES['company_logo']) && (int)($_FILES['company_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $logoError = null;
    $logoResult = employer_handle_logo_upload($_FILES['company_logo'], $logoError);
    if ($logoResult === false) {
      $errors[] = $logoError ?? 'Không thể tải lên logo doanh nghiệp.';
    } else {
      $logoPath = $logoResult;
    }
  }

  if (empty($errors)) {
    $insertId = $employerModel->createForUser(
      $userId,
      $values['company_name'],
      $values['website'] !== '' ? $values['website'] : null,
      $values['address'] !== '' ? $values['address'] : null,
      $values['about'] !== '' ? $values['about'] : null,
      $logoPath
    );

    if ($insertId) {
      $_SESSION['admin_employer_flash'] = [
        'type' => 'success',
        'message' => 'Thêm mới hồ sơ nhà tuyển dụng thành công.'
      ];
      header('Location: ' . ADMIN_URL . '/employers/employers.php');
      exit;
    }

    if ($logoPath !== null) {
      employer_remove_logo($logoPath);
    }
    $errors[] = 'Không thể tạo hồ sơ nhà tuyển dụng. Vui lòng thử lại.';
  } else {
    if ($logoPath !== null) {
      employer_remove_logo($logoPath);
    }
  }
}

$usersResult = $employerModel->conn->query("SELECT id, email FROM users WHERE role_id = 2 ORDER BY email ASC");
$employerUsers = [];
if ($usersResult) {
  while ($row = $usersResult->fetch_assoc()) {
    $employerUsers[] = $row;
  }
}

ob_start();
?>
<div class="pagetitle">
  <h1>Thêm nhà tuyển dụng</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Trang chủ</a></li>
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/employers/employers.php">Nhà tuyển dụng</a></li>
      <li class="breadcrumb-item active">Thêm</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="card p-4 shadow-sm">
    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="row g-4" novalidate>
      <div class="col-md-6">
        <label class="form-label fw-semibold" for="user_id">Tài khoản nhà tuyển dụng <span class="text-danger">*</span></label>
        <select name="user_id" id="user_id" class="form-select" required>
          <option value="">-- Chọn người dùng --</option>
          <?php foreach ($employerUsers as $userItem): ?>
            <option value="<?= (int)$userItem['id'] ?>" <?= $values['user_id'] === (string)$userItem['id'] ? 'selected' : '' ?>><?= htmlspecialchars($userItem['email']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Chỉ hiển thị tài khoản có vai trò nhà tuyển dụng.</div>
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold" for="company_name">Tên công ty <span class="text-danger">*</span></label>
        <input type="text" name="company_name" id="company_name" class="form-control" value="<?= htmlspecialchars($values['company_name']) ?>" required>
      </div>

      <div class="col-12">
        <label class="form-label fw-semibold" for="company_logo">Logo doanh nghiệp</label>
        <input type="file" class="form-control" name="company_logo" id="company_logo" accept="image/png,image/jpeg,image/gif,image/webp">
        <div class="form-text">Tùy chọn · Hỗ trợ PNG, JPG, GIF, WEBP (tối đa 3MB).</div>
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold" for="website">Website</label>
        <input type="url" name="website" id="website" class="form-control" placeholder="https://example.com" value="<?= htmlspecialchars($values['website']) ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold" for="address">Địa chỉ</label>
        <input type="text" name="address" id="address" class="form-control" placeholder="Tòa nhà, đường, thành phố" value="<?= htmlspecialchars($values['address']) ?>">
      </div>

      <div class="col-12">
        <label class="form-label fw-semibold" for="about">Giới thiệu doanh nghiệp</label>
        <textarea name="about" id="about" class="form-control" rows="4" placeholder="Tầm nhìn, sứ mệnh, đội ngũ cốt lõi..."><?= htmlspecialchars($values['about']) ?></textarea>
      </div>

      <div class="col-12 d-flex justify-content-end gap-2">
        <a href="<?= ADMIN_URL ?>/employers/employers.php" class="btn btn-light"><i class="bi bi-arrow-left"></i> Huỷ</a>
        <button type="submit" class="btn btn-success" name="add"><i class="bi bi-save"></i> Lưu hồ sơ</button>
      </div>
    </form>
  </div>
</section>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
