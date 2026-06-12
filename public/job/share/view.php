<?php
require_once dirname(__DIR__, 3) . '/config/config.php';
require_once dirname(__DIR__, 3) . '/app/models/Job.php';
require_once dirname(__DIR__, 3) . '/app/models/Employer.php';
require_once dirname(__DIR__, 3) . '/app/models/Candidate.php';
require_once dirname(__DIR__, 3) . '/app/models/Application.php';

$jobId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($jobId <= 0) {
    header('Location: ' . BASE_URL . '/job/share/index.php');
    exit;
}

$jobModel = new Job();
$job = $jobModel->getById($jobId);
if (!$job || !Job::isActive($job)) {
    $_SESSION['job_share_flash'] = [
      'type' => 'warning',
      'message' => 'Tin tuyển dụng đã hết hạn hoặc không còn hiển thị.'
    ];
    header('Location: ' . BASE_URL . '/job/share/index.php');
    exit;
}

$viewerIp = null;
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
  $forwardedIps = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
  $viewerIp = trim($forwardedIps[0]);
} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
  $viewerIp = (string)$_SERVER['REMOTE_ADDR'];
}
$jobModel->recordView($jobId, $viewerIp);

$jobCategories = $jobModel->getCategoriesForJob($jobId);
$jobCategoryNames = [];
foreach ($jobCategories as $jobCategory) {
  $name = trim((string)($jobCategory['name'] ?? ''));
  if ($name !== '') {
    $jobCategoryNames[] = $name;
  }
}
$jobCategorySummary = 'Chưa cập nhật';
if (!empty($jobCategoryNames)) {
  $jobCategorySummary = implode(', ', array_slice($jobCategoryNames, 0, 3));
  if (count($jobCategoryNames) > 3) {
    $jobCategorySummary .= ' +' . (count($jobCategoryNames) - 3);
  }
}
$jobRequirements = trim((string)($job['job_requirements'] ?? ''));

$employerModel = new Employer();
$employer = $employerModel->getById((int)$job['employer_id']);

$jobLocationText = trim((string)($job['location'] ?? ''));
$employerAddress = trim((string)($employer['address'] ?? ''));
$mapDisplayAddress = $jobLocationText !== '' ? $jobLocationText : $employerAddress;

if (!function_exists('jf_job_map_embed_url')) {
  function jf_job_map_embed_url(?string $address): ?string {
    $address = trim((string)$address);
    if ($address === '') {
      return null;
    }
    $query = rawurlencode($address);
    return 'https://www.google.com/maps?q=' . $query . '&output=embed';
  }
}

$jobMapEmbedUrl = jf_job_map_embed_url($mapDisplayAddress);

$applicationFlash = $_SESSION['job_application_flash'] ?? null;
if (isset($_SESSION['job_application_flash'])) {
  unset($_SESSION['job_application_flash']);
}

$applicationFormData = $_SESSION['job_application_form'] ?? null;
if (isset($_SESSION['job_application_form'])) {
  unset($_SESSION['job_application_form']);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$roleId = (int)($_SESSION['role_id'] ?? 0);
$isLoggedIn = $userId > 0;

$candidate = null;
$candidateProfile = null;
$candidateHasCv = false;
$candidateSkills = [];
$existingApplication = null;
$existingApplicationStatusClass = 'info';

$applicationModel = new Application();
$statusLabels = $applicationModel->getStatusLabels();

if ($isLoggedIn && $roleId === 3) {
  $candidateModel = new Candidate();
  $candidate = $candidateModel->getByUserId($userId);
  $candidateProfile = $candidateModel->getProfileByUserId($userId);

  if ($candidateProfile && !empty($candidateProfile['skills'])) {
    $decodedSkills = json_decode((string)$candidateProfile['skills'], true);
    if (is_array($decodedSkills)) {
      $candidateSkills = array_slice(array_filter(array_map('trim', $decodedSkills)), 0, 8);
    }
  }

  if ($candidateProfile && !empty($candidateProfile['cv_path'])) {
    $candidateHasCv = true;
  } elseif ($candidate && !empty($candidate['cv_path'])) {
    $candidateHasCv = true;
  }

  if ($candidate) {
    $candidateId = (int)($candidate['id'] ?? 0);
    if ($candidateId > 0) {
      $applicationModel = new Application();
      $existingApplication = $applicationModel->getForCandidate($jobId, $candidateId);
      if ($existingApplication) {
        $statusValue = $existingApplication['status'] ?? 'applied';
        switch ($statusValue) {
          case 'rejected':
            $existingApplicationStatusClass = 'danger';
            break;
          case 'shortlisted':
          case 'hired':
            $existingApplicationStatusClass = 'success';
            break;
          case 'viewed':
            $existingApplicationStatusClass = 'secondary';
            break;
          default:
            $existingApplicationStatusClass = 'info';
            break;
        }
      }
    }
  }
}

$coverLetterDraft = '';
$formCvOption = $candidateHasCv ? 'existing' : 'upload';
if (is_array($applicationFormData)) {
  if (isset($applicationFormData['cover_letter'])) {
    $coverLetterDraft = (string)$applicationFormData['cover_letter'];
  }
  if (isset($applicationFormData['cv_option']) && $applicationFormData['cv_option'] !== '') {
    $formCvOption = (string)$applicationFormData['cv_option'];
  }
}

$candidateDisplayName = $candidateProfile['full_name'] ?? ($_SESSION['user_name'] ?? ($_SESSION['email'] ?? 'Ứng viên')); 
$candidateEmail = $candidateProfile['email'] ?? ($_SESSION['email'] ?? '');
$candidatePhone = $candidateProfile['phone'] ?? ''; 
$candidateLocation = $candidateProfile['location'] ?? ($candidate['location'] ?? '');
$candidateHeadline = $candidateProfile['headline'] ?? ($candidate['headline'] ?? '');
$candidateCvPath = trim((string)($candidateProfile['cv_path'] ?? ($candidate['cv_path'] ?? '')));
$candidateCvUpdatedAt = $candidateProfile['updated_at'] ?? null;
$candidateCvFileName = $candidateCvPath !== '' ? basename($candidateCvPath) : '';

function jf_job_format_description(?string $text): string {
    if (!$text) {
        return '<p class="text-muted">Nhà tuyển dụng chưa cập nhật mô tả chi tiết cho vị trí này.</p>';
    }
    $text = trim($text);
    if ($text === '') {
        return '<p class="text-muted">Nhà tuyển dụng chưa cập nhật mô tả chi tiết cho vị trí này.</p>';
    }
    $text = htmlspecialchars($text);
    $text = preg_replace("/(\r\n|\r|\n){2,}/", "</p><p>", nl2br($text));
    return '<p>' . $text . '</p>';
}
  if (!function_exists('jf_job_format_requirements')) {
    function jf_job_format_requirements(?string $text): string {
      if (!$text) {
        return '<p class="text-muted">Nhà tuyển dụng chưa cập nhật yêu cầu chi tiết cho vị trí này.</p>';
      }
      $text = trim($text);
      if ($text === '') {
        return '<p class="text-muted">Nhà tuyển dụng chưa cập nhật yêu cầu chi tiết cho vị trí này.</p>';
      }
      $text = htmlspecialchars($text);
      $text = preg_replace("/(\r\n|\r|\n){2,}/", "</p><p>", nl2br($text));
      return '<p>' . $text . '</p>';
    }
  }

function jf_job_meta_row(string $icon, string $label, ?string $value): string {
    $value = trim((string)$value);
    if ($value === '') {
        $value = 'Chưa cập nhật';
    }
    return '<div class="d-flex align-items-center gap-3"><span class="text-success"><i class="fa-solid ' . $icon . '"></i></span><div><div class="small text-muted">' . htmlspecialchars($label) . '</div><div class="fw-semibold">' . htmlspecialchars($value) . '</div></div></div>';
}

$pageTitle = htmlspecialchars($job['title'] . ' | ' . ($employer['company_name'] ?? 'JobFind'));
$bodyClass = 'job-detail-page';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<main class="job-detail-page py-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-8">
        <article class="card border-0 shadow-sm mb-4">
          <div class="card-body p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
              <!-- <div class="job-logo-lg">
                <?php
                  $logoPath = trim((string)($employer['logo_path'] ?? ''));
                  if ($logoPath !== '') {
                      $logoUrl = BASE_URL . '/' . ltrim($logoPath, '/');
                      echo '<img src="' . htmlspecialchars($logoUrl) . '" alt="' . htmlspecialchars($employer['company_name'] ?? 'Nhà tuyển dụng') . '">';
                  } else {
                      echo '<span class="job-logo-fallback">' . htmlspecialchars(strtoupper(substr($employer['company_name'] ?? 'JF', 0, 2))) . '</span>';
                  }
                ?>
              </div> -->
              <div>
                <h1 class="h3 mb-1"><?= htmlspecialchars($job['title']) ?></h1>
                <div class="text-muted fw-semibold"><?= htmlspecialchars($employer['company_name'] ?? 'Nhà tuyển dụng JobFind') ?></div>
              </div>
            </div>

            <div class="row row-cols-1 row-cols-md-2 g-3 mb-4 job-meta">
              <div><?= jf_job_meta_row('fa-location-dot', 'Địa điểm', $job['location'] ?? '') ?></div>
              <div><?= jf_job_meta_row('fa-coins', 'Mức lương', $job['salary'] ?? '') ?></div>
              <div><?= jf_job_meta_row('fa-suitcase', 'Hình thức làm việc', $job['employment_type'] ?? '') ?></div>
              <div><?= jf_job_meta_row('fa-users', 'Số lượng cần tuyển', $job['quantity'] ? $job['quantity'] . ' vị trí' : 'Không giới hạn') ?></div>
              <div><?= jf_job_meta_row('fa-calendar-day', 'Hạn nộp hồ sơ', $job['deadline'] ? date('d/m/Y', strtotime($job['deadline'])) : 'Không giới hạn') ?></div>
              <div><?= jf_job_meta_row('fa-clock', 'Đăng vào', date('d/m/Y', strtotime($job['created_at'] ?? 'now'))) ?></div>
            </div>

            <?php if (!empty($jobCategories)): ?>
              <div class="d-flex flex-wrap gap-2 mb-4">
                <?php foreach ($jobCategories as $category): ?>
                  <span class="badge bg-success bg-opacity-10 text-success border border-success"><?= htmlspecialchars($category['name'] ?? '') ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <h2 class="h5 mb-3">Mô tả công việc</h2>
            <div class="job-description">
              <?= jf_job_format_description($job['description'] ?? '') ?>
            </div>

            <h2 class="h5 mt-4 mb-3">Yêu cầu ứng viên</h2>
            <div class="job-requirements">
              <?= jf_job_format_requirements($jobRequirements) ?>
            </div>
          </div>
        </article>

        <section class="card border-0 shadow-sm">
          <div class="card-body p-4">
            <h2 class="h5 mb-3">Cách ứng tuyển</h2>
            <?php if ($isLoggedIn && $roleId === 3): ?>
              <p class="mb-0">Điền thư giới thiệu (không bắt buộc) và gửi CV để nhà tuyển dụng xem xét. Bạn sẽ nhận được thông báo khi có phản hồi.</p>
            <?php else: ?>
              <p class="mb-0">Nhấn nút <strong>Ứng tuyển ngay</strong> để đăng nhập/đăng ký và gửi CV đến nhà tuyển dụng.</p>
            <?php endif; ?>
          </div>
        </section>

        <?php if ($jobMapEmbedUrl): ?>
          <section class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
              <h2 class="h5 mb-3">Vị trí trên bản đồ</h2>
              <div class="ratio ratio-16x9">
                <iframe
                  src="<?= htmlspecialchars($jobMapEmbedUrl) ?>"
                  allowfullscreen
                  loading="lazy"
                  referrerpolicy="no-referrer-when-downgrade"
                  aria-label="Bản đồ vị trí công việc"
                  style="border:0;"
                ></iframe>
              </div>
              <p class="small text-muted mb-0 mt-3">
                <i class="fa-solid fa-location-dot me-2"></i><?= htmlspecialchars($mapDisplayAddress) ?>
              </p>
            </div>
          </section>
        <?php endif; ?>
      </div>

      <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-body p-4">
            <h5 class="card-title">Thông tin chung</h5>
            <ul class="list-unstyled mb-0 small text-muted">
              <li class="d-flex justify-content-between mb-2"><span>Loại công việc</span><strong><?= htmlspecialchars($job['employment_type'] ?: 'Chưa cập nhật') ?></strong></li>
              <li class="d-flex justify-content-between mb-2"><span>Địa điểm</span><strong><?= htmlspecialchars($job['location'] ?: 'Toàn quốc') ?></strong></li>
              <li class="d-flex justify-content-between mb-2"><span>Ngành nghề</span><strong><?= htmlspecialchars($jobCategorySummary) ?></strong></li>
              <li class="d-flex justify-content-between mb-2"><span>Mức lương</span><strong><?= htmlspecialchars($job['salary'] ?: 'Thỏa thuận') ?></strong></li>
              <li class="d-flex justify-content-between mb-2"><span>Số lượng cần tuyển</span><strong><?= htmlspecialchars($job['quantity'] ? $job['quantity'] . ' vị trí' : 'Không giới hạn') ?></strong></li>
              <li class="d-flex justify-content-between mb-2"><span>Hạn nộp hồ sơ</span><strong><?= $job['deadline'] ? htmlspecialchars(date('d/m/Y', strtotime($job['deadline']))) : 'Không giới hạn' ?></strong></li>
              <li class="d-flex justify-content-between mb-0"><span>Ngày đăng</span><strong><?= htmlspecialchars(date('d/m/Y', strtotime($job['created_at'] ?? 'now'))) ?></strong></li>
            </ul>
          </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
          <div class="card-body p-4">
            <h5 class="card-title mb-3">Về nhà tuyển dụng</h5>
            <p class="mb-2"><strong><?= htmlspecialchars($employer['company_name'] ?? 'Nhà tuyển dụng JobFind') ?></strong></p>
            <p class="text-muted small mb-0"><?= htmlspecialchars($employer['about'] ?? 'Doanh nghiệp đang cập nhật thông tin giới thiệu.') ?></p>
          </div>
        </div>

        <div class="card border-0 shadow-sm">
          <div class="card-body p-4">
            <?php if ($applicationFlash): ?>
              <div class="alert alert-<?= htmlspecialchars($applicationFlash['type']) ?>" role="alert">
                <?= htmlspecialchars($applicationFlash['message']) ?>
              </div>
            <?php endif; ?>

            <?php if (!$isLoggedIn): ?>
              <p>Đăng nhập để ứng tuyển và theo dõi trạng thái hồ sơ của bạn.</p>
              <div class="d-grid gap-2">
                <a class="btn btn-success" href="<?= BASE_URL ?>/account/login.php">Đăng nhập &amp; ứng tuyển</a>
                <a class="btn btn-outline-success" href="<?= BASE_URL ?>/account/register.php">Tạo tài khoản miễn phí</a>
              </div>
            <?php elseif ($roleId !== 3): ?>
              <div class="alert alert-warning" role="alert">
                Chỉ tài khoản ứng viên mới có thể ứng tuyển công việc.
              </div>
              <div class="d-grid gap-2">
                <a class="btn btn-outline-success" href="<?= BASE_URL ?>/job/share/index.php">Xem thêm việc làm</a>
              </div>
            <?php elseif ($existingApplication): ?>
              <div class="alert alert-<?= $existingApplicationStatusClass ?>" role="alert">
                <strong><?= htmlspecialchars($statusLabels[$existingApplication['status']] ?? 'Đã ứng tuyển') ?></strong>
                <?php if (!empty($existingApplication['applied_at'])): ?>
                  <br><span class="small">Ứng tuyển lúc <?= htmlspecialchars(date('d/m/Y H:i', strtotime($existingApplication['applied_at']))) ?></span>
                <?php endif; ?>
                <?php if (!empty($existingApplication['decision_note'])): ?>
                  <br><span class="small text-muted">Phản hồi: <?= nl2br(htmlspecialchars($existingApplication['decision_note'])) ?></span>
                <?php endif; ?>
              </div>
              <div class="d-grid gap-2">
                <a class="btn btn-outline-success" href="<?= BASE_URL ?>/job/share/index.php">Khám phá việc làm khác</a>
              </div>
            <?php else: ?>
              <?php $showUploadBlock = ($formCvOption === 'upload') || !$candidateHasCv; ?>
              <form method="post" action="<?= BASE_URL ?>/job/apply.php" enctype="multipart/form-data" class="apply-widget">
                <input type="hidden" name="job_id" value="<?= $jobId ?>">

                <div class="apply-widget__candidate">
                  <div class="apply-widget__candidate-info">
                    <div class="fw-semibold"><?= htmlspecialchars($candidateDisplayName) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($candidateEmail) ?></div>
                    <?php if ($candidateLocation !== ''): ?>
                      <div class="text-muted small"><i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($candidateLocation) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="apply-widget__actions">
                    <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/candidate/profile.php" target="_blank" rel="noopener">Xem hồ sơ</a>
                    <a class="btn btn-outline-success btn-sm" href="<?= BASE_URL ?>/candidate/upload_cv.php" target="_blank" rel="noopener">Quản lý CV</a>
                  </div>
                </div>

                <?php if ($candidateHeadline !== ''): ?>
                  <div class="apply-widget__headline">
                    <i class="fa-solid fa-quote-left me-2 text-success"></i><?= htmlspecialchars($candidateHeadline) ?>
                  </div>
                <?php endif; ?>

                <?php if (!empty($candidateSkills)): ?>
                  <div class="apply-widget__skills">
                    <?php foreach ($candidateSkills as $skill): ?>
                      <span class="badge bg-light text-dark border">#<?= htmlspecialchars($skill) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <div class="apply-widget__section">
                  <label class="form-label fw-semibold">Chọn CV gửi cho nhà tuyển dụng</label>
                  <div class="apply-widget__cv-options">
                    <?php if ($candidateHasCv): ?>
                      <div class="form-check apply-widget__radio">
                        <input class="form-check-input" type="radio" name="cv_option" id="cvOptionExisting" value="existing" <?= $formCvOption === 'existing' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="cvOptionExisting">
                          Sử dụng CV đã lưu trên JobFind
                          <?php if ($candidateCvFileName !== ''): ?>
                            <div class="text-muted small">Tệp: <?= htmlspecialchars($candidateCvFileName) ?><?php if (!empty($candidateCvUpdatedAt)): ?> · cập nhật <?= htmlspecialchars(date('d/m/Y H:i', strtotime($candidateCvUpdatedAt))) ?><?php endif; ?></div>
                          <?php endif; ?>
                        </label>
                      </div>
                    <?php endif; ?>

                    <div class="form-check apply-widget__radio">
                      <input class="form-check-input" type="radio" name="cv_option" id="cvOptionUpload" value="upload" <?= (!$candidateHasCv || $formCvOption === 'upload') ? 'checked' : '' ?>>
                      <label class="form-check-label" for="cvOptionUpload">
                        Tải CV mới từ máy của bạn
                        <div class="text-muted small">Hỗ trợ PDF, DOC, DOCX (tối đa 5MB).</div>
                      </label>
                    </div>

                    <div id="cvUploadWrapper" class="apply-widget__upload <?= $showUploadBlock ? '' : 'd-none' ?>">
                      <label for="cv_file" class="form-label">Chọn tệp CV</label>
                      <input class="form-control" type="file" id="cv_file" name="cv_file" accept=".pdf,.doc,.docx" <?= $showUploadBlock ? 'required' : '' ?>>
                      <div class="form-text">Đảm bảo thông tin cá nhân, kinh nghiệm và kỹ năng được cập nhật mới nhất.</div>
                    </div>

                    <div class="form-check apply-widget__radio">
                      <input class="form-check-input" type="radio" name="cv_option" id="cvOptionSkip" value="skip" <?= $formCvOption === 'skip' ? 'checked' : '' ?>>
                      <label class="form-check-label" for="cvOptionSkip">
                        Gửi đơn không kèm CV
                        <div class="text-muted small">Không khuyến khích. Nhà tuyển dụng có thể bỏ qua hồ sơ thiếu CV.</div>
                      </label>
                    </div>
                  </div>
                </div>

                <div class="apply-widget__section">
                  <label for="cover_letter" class="form-label fw-semibold">Thư giới thiệu (không bắt buộc)</label>
                  <textarea id="cover_letter" name="cover_letter" class="form-control" rows="6" maxlength="4000" placeholder="Tóm tắt kinh nghiệm nổi bật, thành tựu cụ thể và lý do bạn phù hợp với vị trí này."><?= htmlspecialchars($coverLetterDraft) ?></textarea>
                  <div class="d-flex justify-content-between align-items-center mt-2 small text-muted">
                    <span>Gợi ý: liên hệ người tham chiếu, thời gian có thể đi làm, kỳ vọng nổi bật.</span>
                    <span><span id="coverLetterCounter">0</span>/4000 ký tự</span>
                  </div>
                </div>

                <button class="btn btn-success w-100" type="submit">
                  <i class="fa-solid fa-paper-plane me-2"></i>Gửi đơn ứng tuyển ngay
                </button>
                <div class="apply-widget__disclaimer text-muted small">
                  Khi gửi đơn, bạn đồng ý chia sẻ thông tin hồ sơ cho nhà tuyển dụng và tuân thủ quy định bảo mật của JobFind.
                </div>
              </form>
            <?php endif; ?>

            <hr class="my-4">
            <a class="btn btn-outline-success w-100" href="<?= BASE_URL ?>/job/share/index.php">Quay lại danh sách</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php if ($isLoggedIn && $roleId === 3 && !$existingApplication): ?>
<script>
(function() {
  var form = document.querySelector('.apply-widget');
  if (!form) {
    return;
  }

  var coverLetter = document.getElementById('cover_letter');
  var counter = document.getElementById('coverLetterCounter');
  var maxLength = 4000;

  function updateCounter() {
    if (!coverLetter || !counter) {
      return;
    }
    if (coverLetter.value.length > maxLength) {
      coverLetter.value = coverLetter.value.substring(0, maxLength);
    }
    counter.textContent = coverLetter.value.length;
  }

  if (coverLetter && counter) {
    coverLetter.addEventListener('input', updateCounter);
    updateCounter();
  }

  var uploadWrapper = document.getElementById('cvUploadWrapper');
  var fileInput = document.getElementById('cv_file');
  var optionRadios = form.querySelectorAll('input[name="cv_option"]');

  function toggleUpload() {
    if (!uploadWrapper) {
      return;
    }
    var selected = form.querySelector('input[name="cv_option"]:checked');
    var isUpload = selected && selected.value === 'upload';
    if (isUpload) {
      uploadWrapper.classList.remove('d-none');
      if (fileInput) {
        fileInput.required = true;
      }
    } else {
      uploadWrapper.classList.add('d-none');
      if (fileInput) {
        fileInput.required = false;
        fileInput.value = '';
      }
    }
  }

  optionRadios.forEach(function(radio) {
    radio.addEventListener('change', toggleUpload);
  });
  toggleUpload();
})();
</script>
<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
