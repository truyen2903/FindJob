<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Candidate.php';
require_once dirname(__DIR__, 2) . '/app/models/User.php';
require_once dirname(__DIR__, 2) . '/app/helpers/avatar.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/account/login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userRole = (int)($_SESSION['role_id'] ?? 0);
if ($userRole !== 3) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$candidateModel = new Candidate();
$userModel = new User();

$profile = $candidateModel->getProfileByUserId($userId);
if (!$profile) {
    $candidateModel->createOrUpdate($userId);
    $profile = $candidateModel->getProfileByUserId($userId);
}

$errors = [];

$currentAvatarPath = null;
if (!empty($profile['profile_picture'])) {
  $currentAvatarPath = $profile['profile_picture'];
} elseif (!empty($profile['avatar_path'])) {
  $currentAvatarPath = $profile['avatar_path'];
}
$currentAvatarUrl = $currentAvatarPath ? BASE_URL . '/' . ltrim($currentAvatarPath, '/') : null;
$removeAvatarChecked = false;

$fullNameValue = trim((string)($profile['full_name'] ?? ''));
if ($fullNameValue === '' && !empty($profile['name'])) {
    $fullNameValue = (string)$profile['name'];
}
$headlineValue = (string)($profile['headline'] ?? '');
$summaryValue = (string)($profile['summary'] ?? '');
$locationValue = (string)($profile['location'] ?? '');
$phoneValue = (string)($profile['phone'] ?? '');

$skillsValue = '';
if (!empty($profile['skills'])) {
    $decodedSkills = json_decode($profile['skills'], true);
    if (is_array($decodedSkills)) {
        $skillsValueParts = array_filter(array_map('trim', $decodedSkills));
        if (!empty($skillsValueParts)) {
            $skillsValue = implode(', ', $skillsValueParts);
        }
    }
}

$experienceValue = '';
if (!empty($profile['experience'])) {
    $decodedExperience = json_decode($profile['experience'], true);
    if (is_array($decodedExperience)) {
        $experienceLines = [];
        foreach ($decodedExperience as $item) {
            $title = trim((string)($item['title'] ?? ''));
            $company = trim((string)($item['company'] ?? ''));
            $start = trim((string)($item['start'] ?? ''));
            $end = trim((string)($item['end'] ?? ''));
            $description = trim((string)($item['description'] ?? ''));

            if ($title === '' && $company === '' && $description === '') {
                continue;
            }

            if ($start !== '') {
        $startTs = strtotime($start);
        $startMonth = $startTs ? date('Y-m', $startTs) : $start;
            } else {
                $startMonth = '';
            }

            if ($end !== '') {
        $endTs = strtotime($end);
        $endMonth = $endTs ? date('Y-m', $endTs) : $end;
            } else {
                $endMonth = '';
            }

            $experienceLines[] = $title . ' | ' . $company . ' | ' . $startMonth . ' | ' . $endMonth . ' | ' . $description;
        }
        if (!empty($experienceLines)) {
            $experienceValue = implode(PHP_EOL, $experienceLines);
        }
    }
}

$avatarInitial = 'J';
$initialSource = $fullNameValue !== '' ? $fullNameValue : (string)($profile['name'] ?? '');
if ($initialSource !== '') {
  if (function_exists('mb_substr')) {
    $avatarInitial = mb_strtoupper(mb_substr($initialSource, 0, 1, 'UTF-8'), 'UTF-8');
  } else {
    $avatarInitial = strtoupper(substr($initialSource, 0, 1));
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fullName = trim((string)($_POST['full_name'] ?? ''));
  $headline = trim((string)($_POST['headline'] ?? ''));
  $summary = trim((string)($_POST['summary'] ?? ''));
  $location = trim((string)($_POST['location'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $skillsInput = trim((string)($_POST['skills'] ?? ''));
  $experienceInput = trim((string)($_POST['experience'] ?? ''));
  $removeAvatar = isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1';
  $removeAvatarChecked = $removeAvatar;
  $newAvatarPath = null;
  $shouldCleanupNewAvatar = false;

  if (!empty($_FILES['avatar']) && isset($_FILES['avatar']['error']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ((int)$_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
      $uploadErr = null;
      $newAvatarPath = handle_avatar_upload($_FILES['avatar'], $uploadErr);
      if (!$newAvatarPath) {
        $errors[] = 'Không thể tải ảnh đại diện: ' . ($uploadErr ?? 'Vui lòng thử lại.');
      } else {
        $shouldCleanupNewAvatar = true;
      }
    } else {
      $errors[] = 'Không thể tải ảnh đại diện: ' . avatar_upload_error_message((int)$_FILES['avatar']['error']);
    }
  }

  if ($fullName === '') {
    $errors[] = 'Vui lòng nhập họ tên của bạn.';
  }

  $skillsArray = [];
  if ($skillsInput !== '') {
    $skillsArray = array_filter(array_map('trim', explode(',', $skillsInput)), static function ($value) {
      return $value !== '';
    });
    $skillsArray = array_values(array_unique($skillsArray));
  }

  $experienceArray = [];
  if ($experienceInput !== '') {
    $lines = preg_split('/\r\n|\r|\n/', $experienceInput);
    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '') {
        continue;
      }
      $parts = array_map('trim', explode('|', $line));
      $parts = array_pad($parts, 5, '');

      if ($parts[0] === '') {
        continue;
      }

      $start = $parts[2];
      $end = $parts[3];

      if ($start !== '') {
        if (preg_match('/^\d{4}-\d{2}$/', $start)) {
          $start .= '-01';
        }
      } else {
        $start = null;
      }

      if ($end !== '') {
        if (preg_match('/^\d{4}-\d{2}$/', $end)) {
          $end .= '-01';
        }
        if (in_array(strtolower($end), ['present', 'hiện tại'], true)) {
          $end = null;
        }
      } else {
        $end = null;
      }

      $experienceArray[] = [
        'title' => $parts[0],
        'company' => $parts[1],
        'start' => $start,
        'end' => $end,
        'description' => $parts[4]
      ];
    }
  }

  if (empty($errors)) {
    $updateData = [
      'full_name' => $fullName,
      'phone' => $phone,
      'headline' => $headline,
      'summary' => $summary,
      'location' => $location,
      'skills' => !empty($skillsArray) ? json_encode($skillsArray, JSON_UNESCAPED_UNICODE) : null,
      'experience' => !empty($experienceArray) ? json_encode($experienceArray, JSON_UNESCAPED_UNICODE) : null,
    ];

    if ($newAvatarPath) {
      $updateData['profile_picture'] = $newAvatarPath;
    } elseif ($removeAvatar && $currentAvatarPath) {
      $updateData['profile_picture'] = null;
    }

    $updated = $candidateModel->updateProfile($userId, $updateData);
    if ($updated) {
      if (array_key_exists('profile_picture', $updateData)) {
        $candidateModel->updateAvatar($userId, $updateData['profile_picture'] ?? null);
        if (!empty($updateData['profile_picture'])) {
          $_SESSION['avatar_url'] = BASE_URL . '/' . ltrim($updateData['profile_picture'], '/');
        } else {
          $_SESSION['avatar_url'] = null;
        }
        $_SESSION['avatar_checked'] = true;
      }

      if ($newAvatarPath && $currentAvatarPath && $currentAvatarPath !== $newAvatarPath) {
        remove_avatar_file($currentAvatarPath);
      } elseif (!$newAvatarPath && $removeAvatar && $currentAvatarPath) {
        remove_avatar_file($currentAvatarPath);
      }

      header('Location: ' . BASE_URL . '/candidate/profile.php?updated=1');
      exit;
    }

    $errors[] = 'Không thể lưu hồ sơ. Vui lòng thử lại sau.';
    if ($newAvatarPath && $shouldCleanupNewAvatar) {
      remove_avatar_file($newAvatarPath);
    }
  } else {
    if ($newAvatarPath && $shouldCleanupNewAvatar) {
      remove_avatar_file($newAvatarPath);
    }
  }

  $fullNameValue = $fullName;
  $headlineValue = $headline;
  $summaryValue = $summary;
  $locationValue = $location;
  $phoneValue = $phone;
  $skillsValue = $skillsInput;
  $experienceValue = $experienceInput;
}

$pageTitle = 'Chỉnh sửa hồ sơ ứng viên | JobFind';
$bodyClass = 'candidate-page';
$additionalCSS = $additionalCSS ?? [];
$additionalCSS[] = '<link rel="stylesheet" href="' . ASSETS_URL . '/css/candidate-profile.css">';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<main class="candidate-page py-5">
  <div class="container" style="max-width: 860px;">
    <a href="<?= BASE_URL ?>/candidate/profile.php" class="text-decoration-none d-inline-flex align-items-center text-muted mb-4">
      <i class="fa-solid fa-arrow-left-long me-2"></i>Quay lại hồ sơ
    </a>

    <div class="section-card p-4 p-md-5 shadow-sm">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
          <h1 class="h3 mb-1">Cập nhật hồ sơ ứng viên</h1>
          <p class="text-muted mb-0">Điền các thông tin nổi bật để nhà tuyển dụng hiểu rõ hơn về bạn.</p>
        </div>
        <a class="btn btn-outline-success" href="<?= BASE_URL ?>/candidate/profile.php" target="_blank" rel="noopener">
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
          <label class="form-label fw-semibold">Ảnh đại diện</label>
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <div>
              <?php if (!empty($currentAvatarUrl)): ?>
                <img src="<?= htmlspecialchars($currentAvatarUrl) ?>" alt="Ảnh đại diện" class="rounded-circle border" style="width:96px;height:96px;object-fit:cover;">
              <?php else: ?>
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle border bg-light text-muted" style="width:96px;height:96px;font-weight:600;">
                  <?= htmlspecialchars($avatarInitial) ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="flex-grow-1" style="min-width:220px;">
              <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
              <div class="form-text">Hỗ trợ JPG, PNG, GIF hoặc WEBP với dung lượng tối đa 2MB.</div>
              <?php if (!empty($currentAvatarPath)): ?>
                <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" value="1" id="remove_avatar" name="remove_avatar" <?= $removeAvatarChecked ? 'checked' : '' ?>>
                  <label class="form-check-label" for="remove_avatar">Xóa ảnh đại diện hiện tại</label>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-12">
          <label for="full_name" class="form-label fw-semibold">Họ tên <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($fullNameValue) ?>" required>
        </div>

        <div class="col-md-6">
          <label for="headline" class="form-label fw-semibold">Tiêu đề nổi bật</label>
          <input type="text" class="form-control" id="headline" name="headline" value="<?= htmlspecialchars($headlineValue) ?>" placeholder="VD: Chuyên viên Marketing Digital">
        </div>

        <div class="col-md-6">
          <label for="location" class="form-label fw-semibold">Địa điểm làm việc</label>
          <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($locationValue) ?>" placeholder="VD: Hà Nội, Việt Nam">
        </div>

        <div class="col-md-6">
          <label for="phone" class="form-label fw-semibold">Số điện thoại</label>
          <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($phoneValue) ?>" placeholder="VD: 0901 234 567">
        </div>

        <div class="col-12">
          <label for="summary" class="form-label fw-semibold">Giới thiệu ngắn</label>
          <textarea class="form-control" id="summary" name="summary" rows="4" placeholder="Tóm tắt mục tiêu nghề nghiệp, kinh nghiệm và thành tích nổi bật..."><?= htmlspecialchars($summaryValue) ?></textarea>
        </div>

        <div class="col-12">
          <label for="skills" class="form-label fw-semibold">Kỹ năng chuyên môn</label>
          <input type="text" class="form-control" id="skills" name="skills" value="<?= htmlspecialchars($skillsValue) ?>" placeholder="Nhập kỹ năng, ngăn cách bằng dấu phẩy">
          <div class="form-text">Ví dụ: PHP, Laravel, Digital Marketing, SEO, Excel nâng cao</div>
        </div>

        <div class="col-12">
          <label for="experience" class="form-label fw-semibold">Kinh nghiệm làm việc</label>
          <textarea class="form-control" id="experience" name="experience" rows="6" placeholder="Vị trí | Công ty | YYYY-MM | YYYY-MM hoặc để trống | Mô tả chi tiết"><?= htmlspecialchars($experienceValue) ?></textarea>
          <div class="form-text">
            Mỗi dòng tương ứng một vị trí. Ví dụ:<br>
            "Digital Marketing Executive | TopGrow Agency | 2021-06 | 2024-03 | Quản lý ngân sách quảng cáo 200 triệu/tháng"
          </div>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
          <a href="<?= BASE_URL ?>/candidate/profile.php" class="btn btn-light">Hủy</a>
          <button type="submit" class="btn btn-success">
            <i class="fa-solid fa-floppy-disk me-2"></i>Lưu cập nhật
          </button>
        </div>
      </form>
    </div>
  </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
