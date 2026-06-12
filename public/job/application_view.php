<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Candidate.php';
require_once dirname(__DIR__, 2) . '/app/models/Application.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$roleId = (int)($_SESSION['role_id'] ?? 0);
if ($userId <= 0 || $roleId !== 3) {
    header('Location: ' . BASE_URL . '/account/login.php'); exit;
}

$candidateModel = new Candidate();
$candidate = $candidateModel->getByUserId($userId);
$candidateId = $candidate ? (int)$candidate['id'] : 0;

$appId = (int)($_GET['id'] ?? 0);
if ($appId <= 0 || $candidateId <= 0) {
    header('Location: ' . BASE_URL . '/job/applications.php'); exit;
}

$applicationModel = new Application();
$app = $applicationModel->getForCandidateById($appId, $candidateId);
if (!$app) {
    $_SESSION['app_flash'] = ['type'=>'warning','message'=>'Không tìm thấy ứng tuyển hoặc bạn không có quyền truy cập.'];
    header('Location: ' . BASE_URL . '/job/applications.php'); exit;
}

// CSRF token for actions on this page
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$pageTitle = 'Chi tiết đơn ứng tuyển';
$bodyClass = 'application-view';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<main class="py-5">
  <div class="container">
    <div class="pagetitle mb-4">
      <h1>Chi tiết ứng tuyển</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Trang cá nhân</a></li>
          <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/job/applications.php">Ứng tuyển</a></li>
          <li class="breadcrumb-item active">Chi tiết</li>
        </ol>
      </nav>
    </div>

    <div class="row">
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
          <div class="card-body p-4">
            <h3 class="h5 mb-2"><a href="<?= BASE_URL ?>/job/share/view.php?id=<?= (int)$app['job_id'] ?>" class="text-decoration-none"><?= htmlspecialchars($app['job_title'] ?? '') ?></a></h3>
            <div class="small text-muted mb-3"><?= htmlspecialchars($app['employer_name'] ?? '') ?> · <?= htmlspecialchars($app['job_location'] ?? '') ?></div>

            <div class="mb-3">
              <strong>Trạng thái:</strong>
              <?php $st = trim((string)($app['status'] ?? '')) !== '' ? $app['status'] : 'applied';
                $map = ['applied'=>'secondary','viewed'=>'info','shortlisted'=>'success','rejected'=>'danger','hired'=>'primary','withdrawn'=>'dark'];
                $cls = $map[$st] ?? 'secondary';
              ?>
              <span class="badge bg-<?= htmlspecialchars($cls) ?>"><?= htmlspecialchars($applicationModel->getStatusLabel($st)) ?></span>
            </div>

            <?php if (!empty($app['cover_letter'])): ?>
              <div class="mb-3">
                <h6>Thư ứng tuyển</h6>
                <div class="text-muted small"><?= nl2br(htmlspecialchars($app['cover_letter'])) ?></div>
              </div>
            <?php endif; ?>

            <?php if (!empty($app['resume_snapshot'])): ?>
              <div class="mb-3">
                <h6>Snapshot CV</h6>
                <div class="text-muted small"><?= nl2br(htmlspecialchars($app['resume_snapshot'])) ?></div>
              </div>
            <?php endif; ?>

            <?php if (!empty($app['decision_note'])): ?>
              <div class="mb-3">
                <h6>Phản hồi từ nhà tuyển dụng</h6>
                <div class="text-muted small"><?= nl2br(htmlspecialchars($app['decision_note'])) ?></div>
              </div>
            <?php endif; ?>

            <div class="text-muted small">Ngày nộp: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($app['applied_at'] ?? 'now'))) ?></div>

            <div class="mt-4 d-flex gap-2">
              <a href="<?= BASE_URL ?>/job/applications.php" class="btn btn-light">Quay lại</a>
              <?php if (($app['status'] ?? '') !== 'withdrawn' && ($app['status'] ?? '') !== 'hired' && ($app['status'] ?? '') !== 'rejected'): ?>
                <form method="post" action="<?= BASE_URL ?>/job/withdraw_application.php" onsubmit="return confirm('Bạn có chắc muốn rút đơn ứng tuyển này?');">
                  <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                  <button type="submit" class="btn btn-danger">Rút đơn</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h6 class="mb-2">Thông tin công việc</h6>
            <p class="small text-muted mb-0"><?= htmlspecialchars(mb_strimwidth($app['job_description'] ?? '', 0, 300, '...')) ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
