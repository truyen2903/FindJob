<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Candidate.php';
require_once dirname(__DIR__, 2) . '/app/helpers/cv.php';

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
$profile = $candidateModel->getProfileByUserId($userId);
if (!$profile) {
    $candidateModel->createOrUpdate($userId);
    $profile = $candidateModel->getProfileByUserId($userId);
}

$currentCv = $profile['cv_path'] ?? null;
$errors = [];
$success = isset($_GET['uploaded']);

function delete_cv_file($relativePath)
{
    if (empty($relativePath)) {
        return;
    }
    $relativePath = ltrim($relativePath, '/\\');
    if ($relativePath === '') {
        return;
    }
    $publicDir = dirname(__DIR__);
    $fullPath = $publicDir . DIRECTORY_SEPARATOR . $relativePath;
    $uploadDir = $publicDir . DIRECTORY_SEPARATOR . trim(CV_UPLOAD_DIR, '/\\');
    $resolvedUpload = realpath($uploadDir);
    $resolvedFile = realpath($fullPath);
    if ($resolvedFile && $resolvedUpload && strpos($resolvedFile, $resolvedUpload) === 0) {
        @unlink($resolvedFile);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['cv_file']['name'])) {
        $errors[] = 'Vui lòng chọn một tệp CV để tải lên.';
    } else {
        $uploadError = null;
        $newCvPath = handle_cv_upload($_FILES['cv_file'], $uploadError);
        if (!$newCvPath) {
            $errors[] = $uploadError ?: 'Không thể tải lên CV. Vui lòng thử lại.';
        } else {
            $updated = $candidateModel->updateCv($userId, $newCvPath);
            if ($updated) {
                if (!empty($currentCv) && $currentCv !== $newCvPath) {
                    delete_cv_file($currentCv);
                }
                header('Location: ' . BASE_URL . '/candidate/upload_cv.php?uploaded=1');
                exit;
            }
            delete_cv_file($newCvPath);
            $errors[] = 'Không thể lưu đường dẫn CV vào hồ sơ. Vui lòng thử lại.';
        }
    }
}

if ($success) {
    $profile = $candidateModel->getProfileByUserId($userId) ?: $profile;
    $currentCv = $profile['cv_path'] ?? $currentCv;
}

$currentCvUrl = '';
if (!empty($currentCv)) {
    $currentCvUrl = BASE_URL . '/' . ltrim($currentCv, '/');
}

$cvUpdatedText = null;
if (!empty($currentCv)) {
  $publicDir = dirname(__DIR__);
  $cvAbsolute = $publicDir . DIRECTORY_SEPARATOR . ltrim($currentCv, '/\\');
  if (file_exists($cvAbsolute)) {
    $cvUpdatedText = date('d/m/Y', filemtime($cvAbsolute));
  }
  if (!$cvUpdatedText && !empty($profile['updated_at'])) {
    $timestamp = strtotime($profile['updated_at']);
    if ($timestamp) {
      $cvUpdatedText = date('d/m/Y', $timestamp);
    }
  }
}

$pageTitle = 'Tải lên CV | JobFind';
$bodyClass = 'candidate-page';
$additionalCSS = $additionalCSS ?? [];
$additionalCSS[] = '<link rel="stylesheet" href="' . ASSETS_URL . '/css/candidate-profile.css">';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<main class="candidate-page py-5">
  <div class="container" style="max-width: 720px;">
    <a href="<?= BASE_URL ?>/candidate/profile.php" class="text-decoration-none d-inline-flex align-items-center text-muted mb-3">
      <i class="fa-solid fa-arrow-left-long me-2"></i>Quay lại hồ sơ ứng viên
    </a>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger" role="alert">
        <ul class="mb-0">
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i>CV đã được tải lên thành công. Nhà tuyển dụng sẽ thấy phiên bản mới nhất ngay lập tức.
      </div>
      <div class="mb-4 d-flex gap-2">
        <a class="btn btn-success" href="<?= BASE_URL ?>/candidate/profile.php?cv=uploaded"><i class="fa-solid fa-user-check me-2"></i>Xem hồ sơ của tôi</a>
      </div>
    <?php endif; ?>

    <div class="section-card">
      <h1 class="h3">Tải lên CV của bạn</h1>
      <p class="text-muted">Chỉ chấp nhận định dạng <strong>PDF</strong>, <strong>DOC</strong> hoặc <strong>DOCX</strong>. Dung lượng tối đa <strong><?= (int)(CV_MAX_SIZE / 1024 / 1024) ?></strong> MB.</p>

      <?php if (!empty($currentCvUrl)): ?>
        <div class="resume-card mb-4">
          <div class="resume-meta">
            <div class="resume-icon"><i class="fa-regular fa-file-lines"></i></div>
            <div>
              <h2 class="h6 mb-1">CV hiện tại</h2>
              <p class="text-muted mb-0">Được cập nhật lần cuối vào <?= htmlspecialchars($cvUpdatedText ?? 'gần đây') ?>.</p>
            </div>
          </div>
          <a class="btn btn-outline-success" href="<?= htmlspecialchars($currentCvUrl) ?>" target="_blank" rel="noopener">Xem CV</a>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="d-flex flex-column gap-3">
        <div>
          <label for="cv_file" class="form-label fw-semibold">Chọn tệp CV</label>
          <input type="file" class="form-control" id="cv_file" name="cv_file" accept=".pdf,.doc,.docx" required>
        </div>
        <div>
          <button type="submit" class="btn btn-success">
            <i class="fa-solid fa-cloud-arrow-up me-2"></i>Tải lên CV
          </button>
        </div>
      </form>

      <div class="mt-4 p-3 bg-light rounded-4">
        <p class="mb-2 fw-semibold"><i class="fa-solid fa-lightbulb text-warning me-2"></i>Mẹo nhỏ</p>
        <ul class="text-muted mb-0">
          <li>Kiểm tra định dạng PDF/DOCX trước khi tải lên để đảm bảo không lỗi font.</li>
          <li>Cập nhật CV mỗi khi bạn có thành tích mới để nổi bật hơn trong mắt nhà tuyển dụng.</li>
          <li>Đừng quên bổ sung đường dẫn Portfolio vào phần Giới thiệu trong hồ sơ.</li>
        </ul>
      </div>
    </div>
  </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
