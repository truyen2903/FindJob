<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Employer.php';
require_once dirname(__DIR__, 2) . '/app/models/User.php';
require_once dirname(__DIR__, 2) . '/app/helpers/company_logo.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/account/login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userRole = (int)($_SESSION['role_id'] ?? 0);
if ($userRole !== 2) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$employerModel = new Employer();
$userModel = new User();

$user = $userModel->getById($userId);
if (!$user) {
    header('Location: ' . BASE_URL . '/account/login.php');
    exit;
}

$employer = $employerModel->getByUserId($userId);
if (!$employer) {
    $defaultName = !empty($user['name']) ? $user['name'] : 'Doanh nghiệp mới';
    $createdId = $employerModel->createForUser($userId, $defaultName);
    if ($createdId) {
        $employer = $employerModel->getById((int)$createdId);
    }
}

if (!$employer) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$employerId = (int)$employer['id'];

$supportsBenefits = array_key_exists('benefits', $employer);
$supportsContactPhone = array_key_exists('contact_phone', $employer);
$supportsCulture = array_key_exists('culture_highlights', $employer) || array_key_exists('culture_story', $employer);
$supportsHiringNotes = array_key_exists('hiring_message', $employer);

$errors = [];

$companyNameValue = trim((string)($employer['company_name'] ?? ''));
$websiteValue = trim((string)($employer['website'] ?? ''));
$addressValue = trim((string)($employer['address'] ?? ''));
$aboutValue = (string)($employer['about'] ?? '');
$benefitsValue = $supportsBenefits ? (string)($employer['benefits'] ?? '') : '';
$cultureValue = '';
if (array_key_exists('culture_highlights', $employer)) {
    $cultureValue = (string)$employer['culture_highlights'];
} elseif (array_key_exists('culture_story', $employer)) {
    $cultureValue = (string)$employer['culture_story'];
}
$hiringNotesValue = $supportsHiringNotes ? (string)($employer['hiring_message'] ?? '') : '';
$contactNameValue = trim((string)($user['name'] ?? ''));
$contactPhoneValue = '';
if (!empty($user['phone'])) {
    $contactPhoneValue = (string)$user['phone'];
} elseif ($supportsContactPhone && !empty($employer['contact_phone'])) {
    $contactPhoneValue = (string)$employer['contact_phone'];
}
$logoPathValue = trim((string)($employer['logo_path'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = trim((string)($_POST['company_name'] ?? ''));
    $website = trim((string)($_POST['website'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $about = trim((string)($_POST['about'] ?? ''));
    $benefits = $supportsBenefits ? trim((string)($_POST['benefits'] ?? '')) : null;
    $culture = $supportsCulture ? trim((string)($_POST['culture'] ?? '')) : null;
    $hiringNotes = $supportsHiringNotes ? trim((string)($_POST['hiring_message'] ?? '')) : null;
    $contactName = trim((string)($_POST['contact_name'] ?? ''));
    $contactPhone = trim((string)($_POST['contact_phone'] ?? ''));

  $newLogoPath = null;
  if (isset($_FILES['company_logo']) && is_array($_FILES['company_logo'])) {
    $logoFile = $_FILES['company_logo'];
    if ((int)($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE && (int)$logoFile['size'] > 0) {
      $logoError = null;
      $uploadResult = employer_handle_logo_upload($logoFile, $logoError);
      if ($uploadResult === false) {
        $errors[] = $logoError ?? 'Không thể tải lên logo doanh nghiệp.';
      } else {
        $newLogoPath = $uploadResult;
      }
    }
  }

  $updatedEmployer = false;

  if ($companyName === '') {
        $errors[] = 'Vui lòng nhập tên công ty.';
    }

    if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = 'Địa chỉ website không hợp lệ.';
    }

  if (empty($errors)) {
        $updateData = [
            'company_name' => $companyName,
            'website' => $website,
            'address' => $address,
            'about' => $about,
        ];

        if ($supportsBenefits) {
            $updateData['benefits'] = $benefits;
        }
        if ($supportsCulture) {
            if (array_key_exists('culture_highlights', $employer)) {
                $updateData['culture_highlights'] = $culture;
            } elseif (array_key_exists('culture_story', $employer)) {
                $updateData['culture_story'] = $culture;
            }
        }
        if ($supportsHiringNotes) {
            $updateData['hiring_message'] = $hiringNotes;
        }
        if ($supportsContactPhone) {
            $updateData['contact_phone'] = $contactPhone;
        }
    if ($newLogoPath !== null) {
      $updateData['logo_path'] = $newLogoPath;
    }

  $updatedEmployer = $employerModel->updateProfileByUserId($userId, $updateData);

        $userUpdate = [];
        if ($contactName !== '') {
            $userUpdate['name'] = $contactName;
        }
        $userUpdate['phone'] = $contactPhone;
        $userModel->updateBasicInfo($userId, $userUpdate);

        if ($updatedEmployer) {
      if ($newLogoPath !== null && $logoPathValue !== '' && $logoPathValue !== $newLogoPath) {
        employer_remove_logo($logoPathValue);
      }
            header('Location: ' . BASE_URL . '/employer/show.php?id=' . $employerId . '&updated=1');
            exit;
        }

    if ($newLogoPath !== null && !$updatedEmployer) {
      employer_remove_logo($newLogoPath);
    }

        $errors[] = 'Không thể lưu thông tin doanh nghiệp. Vui lòng thử lại.';
  } else {
    if ($newLogoPath !== null) {
      employer_remove_logo($newLogoPath);
    }
    }

    $companyNameValue = $companyName;
    $websiteValue = $website;
    $addressValue = $address;
    $aboutValue = $about;
    if ($supportsBenefits) {
        $benefitsValue = $benefits;
    }
    if ($supportsCulture && $culture !== null) {
        $cultureValue = $culture;
    }
    if ($supportsHiringNotes && $hiringNotes !== null) {
        $hiringNotesValue = $hiringNotes;
    }
    $contactNameValue = $contactName;
    $contactPhoneValue = $contactPhone;
}

$pageTitle = 'Chỉnh sửa hồ sơ nhà tuyển dụng | JobFind';
$bodyClass = 'employer-profile';
$additionalCSS = $additionalCSS ?? [];
$additionalCSS[] = '<link rel="stylesheet" href="' . ASSETS_URL . '/css/employer-profile.css">';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<main class="employer-profile py-5">
  <div class="container" style="max-width: 900px;">
    <a href="<?= BASE_URL ?>/employer/show.php?id=<?= $employerId ?>" class="text-decoration-none d-inline-flex align-items-center text-muted mb-4">
      <i class="fa-solid fa-arrow-left-long me-2"></i>Quay lại hồ sơ doanh nghiệp
    </a>

    <div class="section-card p-4 p-md-5 shadow-sm">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
          <h1 class="h3 mb-1">Cập nhật hồ sơ doanh nghiệp</h1>
          <p class="text-muted mb-0">Chia sẻ văn hóa, phúc lợi và thông tin liên hệ để thu hút ứng viên phù hợp.</p>
        </div>
        <a class="btn btn-outline-success" href="<?= BASE_URL ?>/employer/show.php?id=<?= $employerId ?>" target="_blank" rel="noopener">
          <i class="fa-solid fa-eye me-2"></i>Xem trước hồ sơ
        </a>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert">
          <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
              <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" class="row g-4" novalidate enctype="multipart/form-data">
        <div class="col-12">
          <label class="form-label fw-semibold" for="company_logo">Logo doanh nghiệp</label>
          <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
            <div class="company-logo-preview border rounded p-3 text-center" style="width: 140px; height: 140px; display: flex; align-items: center; justify-content: center;">
              <?php if ($logoPathValue !== ''): ?>
                <img src="<?= BASE_URL . '/' . ltrim($logoPathValue, '/') ?>" alt="Logo doanh nghiệp" class="img-fluid" style="max-height: 120px;">
              <?php else: ?>
                <span class="text-muted small">Chưa có logo</span>
              <?php endif; ?>
            </div>
            <div class="flex-grow-1">
              <input type="file" class="form-control" name="company_logo" id="company_logo" accept="image/png,image/jpeg,image/gif,image/webp">
              <div class="form-text">Hỗ trợ PNG, JPG, GIF hoặc WEBP · Tối đa 3MB · Nên dùng nền trong suốt.</div>
            </div>
          </div>
        </div>
        <div class="col-12">
          <label for="company_name" class="form-label fw-semibold">Tên công ty <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="company_name" name="company_name" value="<?= htmlspecialchars($companyNameValue) ?>" required>
        </div>

        <div class="col-md-6">
          <label for="website" class="form-label fw-semibold">Website</label>
          <input type="url" class="form-control" id="website" name="website" value="<?= htmlspecialchars($websiteValue) ?>" placeholder="https://example.com">
        </div>

        <div class="col-md-6">
          <label for="address" class="form-label fw-semibold">Địa chỉ</label>
          <input type="text" class="form-control" id="address" name="address" value="<?= htmlspecialchars($addressValue) ?>" placeholder="Tòa nhà, đường, thành phố">
        </div>

        <div class="col-12">
          <label for="about" class="form-label fw-semibold">Giới thiệu doanh nghiệp</label>
          <textarea class="form-control" id="about" name="about" rows="5" placeholder="Tầm nhìn, sứ mệnh, dự án nổi bật..."><?= htmlspecialchars($aboutValue) ?></textarea>
        </div>

        <?php if ($supportsBenefits): ?>
          <div class="col-12">
            <label for="benefits" class="form-label fw-semibold">Phúc lợi nổi bật</label>
            <textarea class="form-control" id="benefits" name="benefits" rows="4" placeholder="Mỗi dòng một phúc lợi hoặc phân tách bằng dấu phẩy"><?= htmlspecialchars($benefitsValue) ?></textarea>
            <div class="form-text">Ví dụ: Thưởng tháng 13, Bảo hiểm sức khỏe, Hybrid 3 ngày/tuần, Ngân sách đào tạo 10 triệu/năm.</div>
          </div>
        <?php endif; ?>

        <?php if ($supportsCulture): ?>
          <div class="col-12">
            <label for="culture" class="form-label fw-semibold">Văn hóa &amp; môi trường làm việc</label>
            <textarea class="form-control" id="culture" name="culture" rows="4" placeholder="Chia sẻ điều khiến đội ngũ của bạn khác biệt"><?= htmlspecialchars($cultureValue) ?></textarea>
          </div>
        <?php endif; ?>

        <?php if ($supportsHiringNotes): ?>
          <div class="col-12">
            <label for="hiring_message" class="form-label fw-semibold">Thông điệp tuyển dụng</label>
            <textarea class="form-control" id="hiring_message" name="hiring_message" rows="3" placeholder="Bật mí vì sao ứng viên nên gia nhập đội ngũ"><?= htmlspecialchars($hiringNotesValue) ?></textarea>
          </div>
        <?php endif; ?>

        <div class="col-md-6">
          <label for="contact_name" class="form-label fw-semibold">Người liên hệ</label>
          <input type="text" class="form-control" id="contact_name" name="contact_name" value="<?= htmlspecialchars($contactNameValue) ?>" placeholder="Tên người phụ trách tuyển dụng">
        </div>

        <div class="col-md-6">
          <label for="contact_phone" class="form-label fw-semibold">Số điện thoại liên hệ</label>
          <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($contactPhoneValue) ?>" placeholder="VD: 0901 234 567">
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
          <a href="<?= BASE_URL ?>/employer/show.php?id=<?= $employerId ?>" class="btn btn-light">Hủy</a>
          <button type="submit" class="btn btn-success">
            <i class="fa-solid fa-floppy-disk me-2"></i>Lưu cập nhật
          </button>
        </div>
      </form>
    </div>
  </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
