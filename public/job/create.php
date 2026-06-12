<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/controllers/JobController.php';

$userId = $_SESSION['user_id'] ?? null;
$roleId = $_SESSION['role_id'] ?? null;
if (!$userId) {
  header('Location: ' . BASE_URL . '/account/login.php');
  exit;
}
if ((int)$roleId !== 2) {
  header('Location: ' . BASE_URL . '/403.php');
  exit;
}

$jobController = new JobController();
$employer = $jobController->ensureEmployer((int)$userId);
if (!$employer) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$categoryOptions = $jobController->getCategories();
$categoryLookup = [];
foreach ($categoryOptions as $categoryOption) {
    $categoryId = (int)($categoryOption['id'] ?? 0);
    if ($categoryId > 0) {
        $categoryLookup[$categoryId] = $categoryOption;
    }
}

$errors = [];
$values = [
  'title' => '',
  'location' => '',
  'salary' => '',
  'employment_type' => 'Full-time',
  'status' => 'draft',
  'description' => '',
  'job_requirements' => '',
  'quantity' => '',
  'deadline' => '',
  'categories' => []
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['title'] = trim($_POST['title'] ?? '');
    $values['location'] = trim($_POST['location'] ?? '');
    $values['salary'] = trim($_POST['salary'] ?? '');
    $values['employment_type'] = trim($_POST['employment_type'] ?? 'Full-time');
    $values['description'] = trim($_POST['description'] ?? '');
  $values['job_requirements'] = trim($_POST['job_requirements'] ?? '');
  $values['quantity'] = trim($_POST['quantity'] ?? '');
  $values['deadline'] = trim($_POST['deadline'] ?? '');

  $selectedCategoryIds = [];
  $rawCategories = $_POST['categories'] ?? [];
  if (is_array($rawCategories)) {
    foreach ($rawCategories as $categoryId) {
      $categoryId = (int)$categoryId;
      if ($categoryId > 0 && isset($categoryLookup[$categoryId])) {
        $selectedCategoryIds[] = $categoryId;
      }
    }
  }
  $values['categories'] = array_values(array_unique($selectedCategoryIds));

    if ($values['title'] === '') {
        $errors['title'] = 'Vui lòng nhập tiêu đề tin tuyển dụng.';
    }
    if ($values['description'] === '') {
        $errors['description'] = 'Vui lòng mô tả chi tiết công việc.';
    }
  if ($values['job_requirements'] === '') {
    $errors['job_requirements'] = 'Vui lòng mô tả yêu cầu đối với ứng viên.';
  }

  if ($values['quantity'] !== '') {
    if (!ctype_digit($values['quantity']) || (int)$values['quantity'] <= 0) {
      $errors['quantity'] = 'Số lượng cần tuyển phải là số nguyên dương.';
    }
  }

  if ($values['deadline'] !== '') {
    $deadlineDate = \DateTime::createFromFormat('Y-m-d', $values['deadline']);
    $deadlineValid = $deadlineDate && $deadlineDate->format('Y-m-d') === $values['deadline'];
    if (!$deadlineValid) {
      $errors['deadline'] = 'Vui lòng nhập thời hạn đúng định dạng YYYY-MM-DD.';
    }
  }

    $values['status'] = 'draft';

  if (empty($values['categories'])) {
    $errors['categories'] = 'Vui lòng chọn ít nhất một ngành nghề phù hợp.';
  }

    if (empty($errors)) {
        $jobId = $jobController->createJob(
            (int)$userId,
            $values['title'],
            $values['description'],
      $values['job_requirements'] !== '' ? $values['job_requirements'] : null,
            $values['location'] !== '' ? $values['location'] : null,
            $values['salary'] !== '' ? $values['salary'] : null,
            $values['employment_type'] !== '' ? $values['employment_type'] : null,
      'draft',
      $values['quantity'] !== '' ? (int)$values['quantity'] : null,
      $values['deadline'] !== '' ? $values['deadline'] : null,
      $values['categories']
        );

        if ($jobId) {
            $_SESSION['job_flash'] = [
                'type' => 'success',
                'message' => 'Tin tuyển dụng đã được tạo và sẽ hiển thị sau khi quản trị viên phê duyệt.'
            ];
            header('Location: ' . BASE_URL . '/job/index.php');
            exit;
        }

        $errors['general'] = 'Không thể lưu tin tuyển dụng. Vui lòng thử lại.';
    }
}

$pageTitle = 'Đăng tin tuyển dụng mới | JobFind';
$bodyClass = 'job-manage-page';
$additionalScripts = $additionalScripts ?? [];
$additionalScripts[] = '<script src="https://cdn.tiny.cloud/1/d7chqy488l9bipext69mb6wn3a6znouyljw4wi660kj89lg8/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>';
$additionalScripts[] = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (!window.tinymce) {
    return;
  }
  tinymce.init({
    selector: 'textarea.tinymce-editor',
    height: 360,
    menubar: false,
    statusbar: false,
    plugins: 'lists link table code autoresize',
    toolbar: 'undo redo | bold italic underline | bullist numlist | link table | alignleft aligncenter alignright | removeformat code',
    branding: false,
    content_style: 'body { font-family: var(--bs-body-font-family); font-size: 15px; line-height: 1.6; }'
  });

  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function () {
      if (window.tinymce) {
        tinymce.triggerSave();
      }
    });
  });
});
</script>
HTML;
require_once dirname(__DIR__) . '/includes/header.php';

$employmentOptions = ['Full-time', 'Part-time', 'Internship', 'Contract', 'Freelance'];
?>

<main class="container py-5">
  <div class="row mb-4">
    <div class="col-12 col-lg-8">
      <h1 class="fw-semibold mb-1">Đăng tin tuyển dụng mới</h1>
      <p class="text-muted mb-0">Mô tả chi tiết vị trí để thu hút ứng viên phù hợp.</p>
      <!-- <div class="alert alert-warning mt-3">
        Tin mới sẽ ở trạng thái <strong>chờ duyệt</strong>. Quản trị viên sẽ kiểm tra trước khi tin được hiển thị với ứng viên.
      </div> -->
    </div>
    <div class="col-12 col-lg-4 text-lg-end mt-3 mt-lg-0">
      <a href="<?= BASE_URL ?>/job/index.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-2"></i>Quay lại danh sách
      </a>
    </div>
  </div>

  <?php if (!empty($errors['general'])) : ?>
    <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
  <?php endif; ?>

  <form method="post" class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <div class="mb-3">
        <label for="jobTitle" class="form-label">Tiêu đề<span class="text-danger">*</span></label>
        <input type="text" id="jobTitle" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($values['title']) ?>" placeholder="Ví dụ: Chuyên viên Marketing Digital">
        <?php if (isset($errors['title'])) : ?><div class="invalid-feedback"><?= htmlspecialchars($errors['title']) ?></div><?php endif; ?>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <label for="jobLocation" class="form-label">Địa điểm</label>
          <input type="text" id="jobLocation" name="location" class="form-control" value="<?= htmlspecialchars($values['location']) ?>" placeholder="Ví dụ: Hà Nội hoặc Remote">
        </div>
        <div class="col-md-6">
          <label for="jobSalary" class="form-label">Mức lương</label>
          <input type="text" id="jobSalary" name="salary" class="form-control" value="<?= htmlspecialchars($values['salary']) ?>" placeholder="Ví dụ: 15 - 25 triệu">
        </div>
      </div>

      <div class="row g-3 mt-0 mt-md-1">
        <div class="col-md-6">
          <label for="employmentType" class="form-label">Hình thức làm việc</label>
          <select id="employmentType" name="employment_type" class="form-select">
            <option value="">-- Chọn hình thức --</option>
            <?php foreach ($employmentOptions as $option) : ?>
              <option value="<?= htmlspecialchars($option) ?>" <?= $values['employment_type'] === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label for="jobQuantity" class="form-label">Số lượng cần tuyển</label>
          <input type="number" id="jobQuantity" name="quantity" class="form-control <?= isset($errors['quantity']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($values['quantity']) ?>" min="1" placeholder="Ví dụ: 3">
          <?php if (isset($errors['quantity'])) : ?><div class="invalid-feedback"><?= htmlspecialchars($errors['quantity']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-3">
          <label for="jobDeadline" class="form-label">Hạn nộp hồ sơ</label>
          <input type="date" id="jobDeadline" name="deadline" class="form-control <?= isset($errors['deadline']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($values['deadline']) ?>">
          <?php if (isset($errors['deadline'])) : ?><div class="invalid-feedback"><?= htmlspecialchars($errors['deadline']) ?></div><?php endif; ?>
        </div>
      </div>

      <div class="mt-3">
        <label for="jobDescription" class="form-label">Mô tả công việc<span class="text-danger">*</span></label>
        <textarea id="jobDescription" name="description" rows="8" class="form-control tinymce-editor <?= isset($errors['description']) ? 'is-invalid' : '' ?>" placeholder="Nêu rõ trách nhiệm, yêu cầu và quyền lợi của vị trí."><?= htmlspecialchars($values['description']) ?></textarea>
        <?php if (isset($errors['description'])) : ?><div class="invalid-feedback"><?= htmlspecialchars($errors['description']) ?></div><?php endif; ?>
      </div>

      <div class="mt-3">
        <label for="jobRequirements" class="form-label">Yêu cầu ứng viên<span class="text-danger">*</span></label>
        <textarea id="jobRequirements" name="job_requirements" rows="6" class="form-control tinymce-editor <?= isset($errors['job_requirements']) ? 'is-invalid' : '' ?>" placeholder="Liệt kê kỹ năng, kinh nghiệm tối thiểu, chứng chỉ bắt buộc và tố chất cần có."><?= htmlspecialchars($values['job_requirements']) ?></textarea>
        <?php if (isset($errors['job_requirements'])) : ?><div class="invalid-feedback"><?= htmlspecialchars($errors['job_requirements']) ?></div><?php endif; ?>
        <div class="form-text">Ví dụ: 2+ năm kinh nghiệm PHP/Laravel, khả năng đọc hiểu tài liệu tiếng Anh, ưu tiên từng làm việc với REST API.</div>
      </div>

      <div class="mt-4">
        <label class="form-label">Ngành nghề tuyển dụng<span class="text-danger">*</span></label>
        <?php if (empty($categoryOptions)) : ?>
          <div class="alert alert-warning mb-0">Hiện chưa có danh sách ngành nghề. Vui lòng liên hệ quản trị viên để được hỗ trợ.</div>
        <?php else : ?>
          <div class="row g-2">
            <?php foreach ($categoryOptions as $category) : ?>
              <?php $categoryId = (int)($category['id'] ?? 0); ?>
              <?php if ($categoryId <= 0) { continue; } ?>
              <div class="col-sm-6 col-lg-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="categories[]" id="category_<?= $categoryId ?>" value="<?= $categoryId ?>" <?= in_array($categoryId, $values['categories'], true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="category_<?= $categoryId ?>"><?= htmlspecialchars($category['name'] ?? ('Ngành nghề #' . $categoryId)) ?></label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if (isset($errors['categories'])) : ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['categories']) ?></div><?php endif; ?>
        <div class="form-text">Chọn 1-3 ngành nghề mô tả chính xác vị trí đang tuyển.</div>
      </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-end gap-2 p-3">
      <a href="<?= BASE_URL ?>/job/index.php" class="btn btn-light">Huỷ</a>
      <button type="submit" class="btn btn-success">Lưu tin tuyển dụng</button>
    </div>
  </form>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
