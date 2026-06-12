<?php
require_once dirname(__DIR__, 3) . '/config/config.php';
require_once dirname(__DIR__, 3) . '/app/models/Employer.php';
require_once dirname(__DIR__, 3) . '/app/models/Job.php';

if (empty($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 2) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$employerModel = new Employer();
$jobModel = new Job();
$employer = $employerModel->getByUserId($userId);
if (!$employer) {
    header('Location: ' . BASE_URL . '/employer/edit.php');
    exit;
}
$employerId = (int)$employer['id'];
$_SESSION['employer_company_name'] = $employer['company_name'];
$_SESSION['employer_profile_url'] = BASE_URL . '/employer/show.php?id=' . $employerId;

$jobId = (int)($_GET['id'] ?? 0);
$editing = $jobId > 0;
$job = $editing ? $jobModel->getById($jobId) : null;
if ($editing && (!$job || (int)$job['employer_id'] !== $employerId)) {
    header('Location: ' . BASE_URL . '/employer/admin/jobs.php');
    exit;
}

$categoryOptions = $jobModel->getAllCategories();
$categoryLookup = [];
foreach ($categoryOptions as $categoryOption) {
  $categoryId = (int)($categoryOption['id'] ?? 0);
  if ($categoryId > 0) {
    $categoryLookup[$categoryId] = $categoryOption;
  }
}

$errors = [];
$title = $job['title'] ?? '';
$description = $job['description'] ?? '';
$jobRequirements = $job['job_requirements'] ?? '';
$location = $job['location'] ?? '';
$salary = $job['salary'] ?? '';
$employment_type = $job['employment_type'] ?? 'Full-time';
$status = $job['status'] ?? 'draft';
$quantity = isset($job['quantity']) ? (string)$job['quantity'] : '';
$deadline = $job['deadline'] ?? '';
$selectedCategories = $editing ? $jobModel->getCategoryIdsForJob($jobId) : [];

$statusOptions = [
  'draft' => 'Nháp',
  'published' => 'Đăng tuyển',
  'closed' => 'Đã đóng',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string)($_POST['title'] ?? ''));
  $description = trim((string)($_POST['description'] ?? ''));
  $jobRequirements = trim((string)($_POST['job_requirements'] ?? ''));
    $location = trim((string)($_POST['location'] ?? ''));
    $salary = trim((string)($_POST['salary'] ?? ''));
    $employment_type = trim((string)($_POST['employment_type'] ?? ''));
  $quantity = trim((string)($_POST['quantity'] ?? ''));
  $deadline = trim((string)($_POST['deadline'] ?? ''));
  $status = trim((string)($_POST['status'] ?? 'draft'));

  $selectedCategories = [];
  $rawCategories = $_POST['categories'] ?? [];
  if (is_array($rawCategories)) {
    foreach ($rawCategories as $categoryId) {
      $categoryId = (int)$categoryId;
      if ($categoryId > 0 && isset($categoryLookup[$categoryId])) {
        $selectedCategories[] = $categoryId;
      }
    }
  }
  $selectedCategories = array_values(array_unique($selectedCategories));

    if ($title === '') {
        $errors[] = 'Tiêu đề công việc không được để trống.';
    }
    if ($description === '') {
        $errors[] = 'Mô tả công việc không được để trống.';
    }
  if ($jobRequirements === '') {
    $errors[] = 'Yêu cầu ứng viên không được để trống.';
  }

  if ($quantity !== '') {
    if (!ctype_digit($quantity) || (int)$quantity <= 0) {
      $errors[] = 'Số lượng cần tuyển phải là số nguyên dương.';
    }
  }

  if ($deadline !== '') {
    $deadlineDate = \DateTime::createFromFormat('Y-m-d', $deadline);
    $deadlineValid = $deadlineDate && $deadlineDate->format('Y-m-d') === $deadline;
    if (!$deadlineValid) {
      $errors[] = 'Hạn nộp hồ sơ phải đúng định dạng YYYY-MM-DD.';
    }
  }

    if (empty($selectedCategories)) {
        $errors[] = 'Vui lòng chọn ít nhất một ngành nghề phù hợp.';
    }

    if (empty($errors)) {
    $jobRequirementsForSave = $jobRequirements !== '' ? $jobRequirements : null;
    $locationForSave = $location !== '' ? $location : null;
    $salaryForSave = $salary !== '' ? $salary : null;
    $employmentTypeForSave = $employment_type !== '' ? $employment_type : null;
    $quantityForSave = $quantity !== '' ? (int)$quantity : null;
    $deadlineForSave = $deadline !== '' ? $deadline : null;
    if ($editing) {
      $ok = $jobModel->update($jobId, $employerId, $title, $description, $jobRequirementsForSave, $locationForSave, $salaryForSave, $employmentTypeForSave, $status, $quantityForSave, $deadlineForSave);
      if ($ok) {
        $jobModel->syncCategories($jobId, $selectedCategories);
        $_SESSION['employer_job_flash'] = [
          'type' => 'success',
          'message' => 'Tin tuyển dụng đã được cập nhật.'
        ];
        header('Location: ' . BASE_URL . '/employer/admin/jobs.php');
        exit;
      }
    } else {
      $newId = $jobModel->create($employerId, $title, $description, $jobRequirementsForSave, $locationForSave, $salaryForSave, $employmentTypeForSave, $status, $quantityForSave, $deadlineForSave);
      if ($newId) {
        $jobModel->syncCategories((int)$newId, $selectedCategories);
        $_SESSION['employer_job_flash'] = [
          'type' => 'success',
          'message' => 'Tin tuyển dụng đã được tạo mới.'
        ];
        header('Location: ' . BASE_URL . '/employer/admin/jobs.php');
        exit;
      }
    }
        $errors[] = 'Không thể lưu tin tuyển dụng, vui lòng thử lại sau.';
    }
}

$pageTitle = $editing ? 'Cập nhật tin tuyển dụng' : 'Đăng tin tuyển dụng mới';
$employerNavActive = 'jobs';
$employerCompanyName = $employer['company_name'];
$employerProfileUrl = BASE_URL . '/employer/show.php?id=' . $employerId;

require_once __DIR__ . '/includes/header.php';
?>

<a class="btn btn-link text-decoration-none ps-0 mb-3" href="<?= BASE_URL ?>/employer/admin/jobs.php">
  <i class="fa-solid fa-arrow-left-long me-2"></i>Quay lại danh sách tin
</a>

<div class="ea-card">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div>
      <span class="text-muted text-uppercase small fw-semibold"><?= $editing ? 'Chỉnh sửa tin tuyển dụng' : 'Tạo tin mới' ?></span>
      <h2 class="h4 mb-0"><?= $editing ? 'Cập nhật tin tuyển dụng' : 'Đăng tin tuyển dụng mới' ?></h2>
    </div>
    <?php if ($editing): ?>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/job/share/view.php?id=<?= $jobId ?>" target="_blank" rel="noopener">
        <i class="fa-regular fa-eye me-2"></i>Xem tin tuyển dụng
      </a>
    <?php endif; ?>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" class="row g-4">
    <div class="col-12">
      <label class="form-label fw-semibold" for="title">Tiêu đề tin tuyển dụng <span class="text-danger">*</span></label>
      <input type="text" id="title" name="title" class="form-control" value="<?= htmlspecialchars($title) ?>" required>
    </div>

    <div class="col-12">
      <label class="form-label fw-semibold" for="description">Mô tả chi tiết <span class="text-danger">*</span></label>
      <textarea id="description" name="description" rows="8" class="form-control" required><?= htmlspecialchars($description) ?></textarea>
      <div class="form-text">Gợi ý: nêu rõ nhiệm vụ, yêu cầu, quyền lợi và quy trình ứng tuyển.</div>
    </div>

      <div class="col-12">
        <label class="form-label fw-semibold" for="job_requirements">Yêu cầu ứng viên <span class="text-danger">*</span></label>
        <textarea id="job_requirements" name="job_requirements" rows="6" class="form-control" required><?= htmlspecialchars($jobRequirements) ?></textarea>
        <div class="form-text">Liệt kê kỹ năng, kinh nghiệm và tố chất cần thiết. Ví dụ: 3+ năm kinh nghiệm, thành thạo PHP/Laravel, giao tiếp tiếng Anh.</div>
      </div>

    <div class="col-md-4">
      <label class="form-label fw-semibold" for="location">Địa điểm làm việc</label>
      <input type="text" id="location" name="location" class="form-control" value="<?= htmlspecialchars($location) ?>" placeholder="Hà Nội, Remote...">
    </div>

    <div class="col-md-4">
      <label class="form-label fw-semibold" for="salary">Mức lương</label>
      <input type="text" id="salary" name="salary" class="form-control" value="<?= htmlspecialchars($salary) ?>" placeholder="Ví dụ: 15 - 20 triệu">
    </div>

    <div class="col-md-4">
      <label class="form-label fw-semibold" for="employment_type">Hình thức làm việc</label>
      <input type="text" id="employment_type" name="employment_type" class="form-control" value="<?= htmlspecialchars($employment_type) ?>" placeholder="Full-time, Part-time...">
    </div>

    <div class="col-md-4">
      <label class="form-label fw-semibold" for="quantity">Số lượng cần tuyển</label>
      <input type="number" id="quantity" name="quantity" class="form-control" min="1" value="<?= htmlspecialchars($quantity) ?>" placeholder="Ví dụ: 5">
    </div>

    <div class="col-md-4">
      <label class="form-label fw-semibold" for="deadline">Hạn nộp hồ sơ</label>
      <input type="date" id="deadline" name="deadline" class="form-control" value="<?= htmlspecialchars($deadline) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label fw-semibold" for="status">Trạng thái tin</label>
      <select id="status" name="status" class="form-select">
        <?php foreach ($statusOptions as $value => $label): ?>
          <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12">
      <label class="form-label fw-semibold">Ngành nghề tuyển dụng <span class="text-danger">*</span></label>
      <?php if (empty($categoryOptions)): ?>
        <div class="alert alert-warning mb-0">Hiện chưa có danh sách ngành nghề. Vui lòng liên hệ quản trị viên để được hỗ trợ.</div>
      <?php else: ?>
        <div class="row g-2">
          <?php foreach ($categoryOptions as $category): ?>
            <?php $categoryId = (int)($category['id'] ?? 0); ?>
            <?php if ($categoryId <= 0) { continue; } ?>
            <div class="col-sm-6 col-lg-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="categories[]" id="category_<?= $categoryId ?>" value="<?= $categoryId ?>" <?= in_array($categoryId, $selectedCategories, true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="category_<?= $categoryId ?>"><?= htmlspecialchars($category['name'] ?? ('Ngành nghề #' . $categoryId)) ?></label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="form-text">Chọn 1-3 ngành nghề phản ánh chính xác lĩnh vực của vị trí.</div>
    </div>

    <div class="col-12 d-flex justify-content-end gap-2">
      <a class="btn btn-light" href="<?= BASE_URL ?>/employer/admin/jobs.php">Hủy</a>
      <button class="btn btn-success" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Lưu tin tuyển dụng</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
