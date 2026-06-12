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
$id = (int)($_GET['id'] ?? 0);
$employer = $employerModel->getById($id);
if (!$employer) {
  header('Location: ' . ADMIN_URL . '/employers/employers.php');
  exit;
}

$associatedUser = $userModel->getById((int)($employer['user_id'] ?? 0));

$errors = [];
$values = [
  'company_name' => trim((string)($employer['company_name'] ?? '')),
  'website' => trim((string)($employer['website'] ?? '')),
  'address' => trim((string)($employer['address'] ?? '')),
  'about' => (string)($employer['about'] ?? '')
];
$existingLogo = trim((string)($employer['logo_path'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $values['company_name'] = trim((string)($_POST['company_name'] ?? ''));
  $values['website'] = trim((string)($_POST['website'] ?? ''));
  $values['address'] = trim((string)($_POST['address'] ?? ''));
  $values['about'] = trim((string)($_POST['about'] ?? ''));

  if ($values['company_name'] === '') {
    $errors[] = 'Vui lòng nhập tên công ty.';
  }

  if ($values['website'] !== '' && !filter_var($values['website'], FILTER_VALIDATE_URL)) {
    $errors[] = 'Địa chỉ website không hợp lệ. Vui lòng nhập dạng https://example.com.';
  }

  $newLogoPath = null;
  if (isset($_FILES['company_logo']) && is_array($_FILES['company_logo']) && (int)($_FILES['company_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $logoError = null;
    $uploadResult = employer_handle_logo_upload($_FILES['company_logo'], $logoError);
    if ($uploadResult === false) {
      $errors[] = $logoError ?? 'Không thể tải lên logo doanh nghiệp.';
    } else {
      $newLogoPath = $uploadResult;
    }
  }

  if (empty($errors)) {
    $updated = $employerModel->update(
      $id,
      $values['company_name'],
      $values['website'] !== '' ? $values['website'] : null,
      $values['address'] !== '' ? $values['address'] : null,
      $values['about'] !== '' ? $values['about'] : null,
      $newLogoPath
    );

    if ($updated) {
      if ($newLogoPath !== null && $existingLogo !== '' && $existingLogo !== $newLogoPath) {
        employer_remove_logo($existingLogo);
      }
      $_SESSION['admin_employer_flash'] = [
        'type' => 'success',
        'message' => 'Cập nhật thông tin nhà tuyển dụng thành công.'
      ];
      header('Location: ' . ADMIN_URL . '/employers/employers.php');
      exit;
    }

    if ($newLogoPath !== null) {
      employer_remove_logo($newLogoPath);
    }
    $errors[] = 'Không thể cập nhật thông tin doanh nghiệp. Vui lòng thử lại.';
  } else {
    if ($newLogoPath !== null) {
      employer_remove_logo($newLogoPath);
    }
  }
}

ob_start();
?>
<div class="pagetitle">
  <h1>Sửa thông tin nhà tuyển dụng</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Trang chủ</a></li>
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/employers/employers.php">Nhà tuyển dụng</a></li>
      <li class="breadcrumb-item active">Sửa</li>
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
      <div class="col-lg-4">
        <label class="form-label fw-semibold">Logo doanh nghiệp</label>
        <div class="border rounded p-3 text-center">
          <?php if ($existingLogo !== ''): ?>
            <img src="<?= BASE_URL . '/' . ltrim($existingLogo, '/') ?>" alt="Logo" class="img-fluid" style="max-height: 140px;">
          <?php else: ?>
            <span class="text-muted small d-block">Chưa có logo</span>
          <?php endif; ?>
        </div>
        <input type="file" name="company_logo" class="form-control mt-3" accept="image/png,image/jpeg,image/gif,image/webp">
        <div class="form-text">Tùy chọn · PNG, JPG, GIF, WEBP · tối đa 3MB.</div>
      </div>
      <div class="col-lg-8">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold" for="company_name">Tên công ty <span class="text-danger">*</span></label>
            <input type="text" name="company_name" id="company_name" class="form-control" value="<?= htmlspecialchars($values['company_name']) ?>" required>
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
            <textarea name="about" id="about" class="form-control" rows="5" placeholder="Tầm nhìn, sứ mệnh, phúc lợi nổi bật..."><?= htmlspecialchars($values['about']) ?></textarea>
          </div>
          <?php if ($associatedUser): ?>
            <div class="col-12">
              <div class="alert alert-light border d-flex align-items-center gap-3 mb-0">
                <i class="bi bi-person-badge fs-4 text-primary"></i>
                <div>
                  <div class="fw-semibold mb-1">Tài khoản liên kết</div>
                  <div class="small text-muted">Email: <?= htmlspecialchars($associatedUser['email'] ?? 'N/A') ?></div>
                  <?php if (!empty($associatedUser['name'])): ?>
                    <div class="small text-muted">Người liên hệ: <?= htmlspecialchars($associatedUser['name']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($associatedUser['phone'])): ?>
                    <div class="small text-muted">SĐT: <?= htmlspecialchars($associatedUser['phone']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-12 d-flex justify-content-end gap-2">
        <a href="<?= ADMIN_URL ?>/employers/employers.php" class="btn btn-light"><i class="bi bi-arrow-left"></i> Quay lại</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Lưu thay đổi</button>
      </div>
    </form>
  </div>
</section>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
