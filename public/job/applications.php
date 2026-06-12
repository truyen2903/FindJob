<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Candidate.php';
require_once dirname(__DIR__, 2) . '/app/models/Application.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$roleId = (int)($_SESSION['role_id'] ?? 0);
if ($userId <= 0 || $roleId !== 3) {
    // only candidates can view their applications
    header('Location: ' . BASE_URL . '/account/login.php');
    exit;
}

$candidateModel = new Candidate();
$candidate = $candidateModel->getByUserId($userId);
if (!$candidate) {
    // candidate profile missing — show empty state
    $candidateId = 0;
} else {
    $candidateId = (int)$candidate['id'];
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$filters = [
    'status' => trim((string)($_GET['status'] ?? '')),
    'keyword' => trim((string)($_GET['q'] ?? ''))
];

$applicationModel = new Application();
$list = $applicationModel->listForCandidate($candidateId, $filters, $page, $perPage);

$rows = $list['rows'] ?? [];
$total = (int)($list['total'] ?? 0);
$totalPages = (int)($list['total_pages'] ?? 1);

$pageTitle = 'Ứng tuyển của tôi';
$bodyClass = 'candidate-applications';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<main class="py-5">
  <div class="container">
    <div class="pagetitle mb-4">
      <h1>Ứng tuyển của tôi</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Trang cá nhân</a></li>
          <li class="breadcrumb-item active">Ứng tuyển</li>
        </ol>
      </nav>
    </div>

    <div class="row g-4">
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h2 class="h6 mb-0">Danh sách đơn đã nộp</h2>
              <form method="get" class="d-flex gap-2" style="max-width:420px;">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Tìm theo vị trí hoặc công ty" value="<?= htmlspecialchars($filters['keyword']) ?>">
                <select name="status" class="form-select form-select-sm">
                  <option value="">Tất cả trạng thái</option>
                  <?php foreach ($applicationModel->getStatusLabels() as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search"></i></button>
              </form>
            </div>

            <?php if ($total === 0): ?>
              <div class="text-center py-5 text-muted">
                <p class="mb-2">Bạn chưa ứng tuyển công việc nào.</p>
                <a href="<?= BASE_URL ?>/job/share/index.php" class="btn btn-outline-success">Tìm việc ngay</a>
              </div>
            <?php else: ?>
              <div class="list-group list-group-flush">
                <?php foreach ($rows as $r): ?>
                  <div class="list-group-item py-3">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                      <div class="flex-grow-1">
                        <a href="<?= BASE_URL ?>/job/application_view.php?id=<?= (int)$r['id'] ?>" class="h6 d-block text-decoration-none mb-1"><?= htmlspecialchars($r['job_title'] ?? '—') ?></a>
                        <div class="small text-muted mb-1"><?= htmlspecialchars($r['employer_name'] ?? '') ?> · <?= htmlspecialchars($r['job_location'] ?? '') ?></div>
                        <?php if (!empty($r['cover_letter'])): ?>
                          <div class="small text-muted">Thư ứng tuyển: <?= htmlspecialchars(mb_strimwidth($r['cover_letter'], 0, 200, '...')) ?></div>
                        <?php endif; ?>
                      </div>
                      <div class="text-end">
                        <div>
                          <?php $st = trim((string)($r['status'] ?? '')) !== '' ? $r['status'] : 'applied'; ?>
                          <?php
                            $map = ['applied'=>'secondary','viewed'=>'info','shortlisted'=>'success','rejected'=>'danger','hired'=>'primary','withdrawn'=>'dark'];
                            $cls = $map[$st] ?? 'secondary';
                          ?>
                          <span class="badge bg-<?= htmlspecialchars($cls) ?>"><?= htmlspecialchars($applicationModel->getStatusLabel($st)) ?></span>
                        </div>
                        <div class="small text-muted mt-2"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($r['applied_at'] ?? 'now'))) ?></div>
                      </div>
                    </div>
                    <?php if (!empty($r['decision_note'])): ?>
                      <div class="mt-2 small text-muted">Phản hồi: <?= nl2br(htmlspecialchars($r['decision_note'])) ?></div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>

              <?php if ($totalPages > 1): ?>
                <nav class="mt-3" aria-label="Trang">
                  <ul class="pagination justify-content-center">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                      <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $p ?>&q=<?= urlencode($filters['keyword']) ?>&status=<?= urlencode($filters['status']) ?>"><?= $p ?></a>
                      </li>
                    <?php endfor; ?>
                  </ul>
                </nav>
              <?php endif; ?>

            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-body">
            <h5 class="mb-2">Trợ giúp</h5>
            <p class="small text-muted mb-0">Tại đây hiển thị tất cả đơn bạn đã nộp, trạng thái và phản hồi từ nhà tuyển dụng. Bạn có thể tìm nhanh theo tên công việc hoặc công ty.</p>
          </div>
        </div>
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h6 class="mb-2">Mẹo ứng tuyển</h6>
            <ul class="small mb-0">
              <li>Cập nhật CV và thông tin cá nhân trước khi ứng tuyển.</li>
              <li>Viết thư ứng tuyển ngắn, nêu rõ điểm mạnh liên quan.</li>
              <li>Theo dõi trạng thái để kịp thời phản hồi khi được mời phỏng vấn.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
