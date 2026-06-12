<?php
require_once dirname(__DIR__) . '/../config/config.php';
require_once dirname(__DIR__) . '/../app/models/Employer.php';

$employerModel = new Employer();

$searchTerm = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$locationFilter = isset($_GET['location']) ? trim((string)$_GET['location']) : '';
$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'featured';
$allowedSorts = ['featured', 'recent', 'alphabet'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'featured';
}

$perPage = 6;
$page = max(1, (int)($_GET['page'] ?? 1));
$directoryResult = $employerModel->getDirectoryPaginated([
  'search' => $searchTerm,
  'location' => $locationFilter,
  'sort' => $sort,
], $page, $perPage);

$companies = $directoryResult['rows'];
$matchedEmployers = (int)($directoryResult['total'] ?? 0);
$page = (int)($directoryResult['page'] ?? $page);
$totalPages = (int)($directoryResult['total_pages'] ?? 1);
$directoryQueryError = $directoryResult['query_error'] ?? null;

$directoryDataset = $employerModel->getDirectoryList();
$totalEmployers = $employerModel->countAll();
$totalOpenings = 0;
$locationMap = [];
foreach ($directoryDataset as $company) {
    $totalOpenings += (int)($company['job_count'] ?? 0);
    $address = trim((string)($company['address'] ?? ''));
    if ($address !== '') {
        $locationMap[$address] = $address;
    }
}
$activeLocationCount = count($locationMap);
$locationSuggestions = [];
if (!empty($locationMap)) {
    $locationValues = array_values($locationMap);
    sort($locationValues, SORT_NATURAL | SORT_FLAG_CASE);
    $locationSuggestions = array_slice($locationValues, 0, 8);
}

$baseQueryParams = [
  'q' => $searchTerm !== '' ? $searchTerm : null,
  'location' => $locationFilter !== '' ? $locationFilter : null,
  'sort' => $sort !== 'featured' ? $sort : null,
];

$buildQuery = static function (array $overrides = []) use ($baseQueryParams) {
  $params = array_merge($baseQueryParams, $overrides);
    $params = array_filter($params, static function ($value) {
        return $value !== null && $value !== '';
    });
    if (empty($params)) {
        return '';
    }
    return '?' . http_build_query($params);
};

$pageTitle = 'Danh sách nhà tuyển dụng hàng đầu | JobFind';
$bodyClass = 'employer-directory';
$additionalCSS = $additionalCSS ?? [];
$additionalCSS[] = '<link rel="stylesheet" href="' . ASSETS_URL . '/css/employers.css">';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<main class="employer-directory">
  <section class="directory-hero">
    <div class="container">
      <div class="row align-items-center g-4">
        <div class="col-lg-7">
          <span class="badge text-uppercase mb-3"><i class="fa-solid fa-building me-2"></i> Nhà tuyển dụng uy tín</span>
          <h1>Khám phá doanh nghiệp hàng đầu trên JobFind</h1>
          <p>Từ startup sáng tạo đến tập đoàn lớn, JobFind kết nối bạn với hàng trăm doanh nghiệp đang tuyển dụng mỗi ngày. Tìm hiểu văn hóa, vị trí tuyển dụng và ứng tuyển nhanh chóng chỉ trong vài bước.</p>
        </div>
        <div class="col-lg-5">
          <div class="row directory-stats g-3">
            <div class="col-sm-6">
              <div class="stat-card">
                <div class="stat-value"><?= number_format($totalEmployers) ?></div>
                <p class="stat-label mb-0">Nhà tuyển dụng đang hoạt động</p>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="stat-card">
                <div class="stat-value"><?= number_format($totalOpenings) ?></div>
                <p class="stat-label mb-0">Vị trí tuyển dụng đang mở</p>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="stat-card">
                <div class="stat-value"><?= number_format($activeLocationCount) ?></div>
                <p class="stat-label mb-0">Tỉnh thành có doanh nghiệp</p>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="stat-card">
                <div class="stat-value"><i class="fa-solid fa-shield-heart text-success me-2"></i>100%</div>
                <p class="stat-label mb-0">Doanh nghiệp xác thực</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="directory-filters">
    <div class="container">
      <div class="filter-card">
        <form class="row g-3 align-items-end" method="get" action="">
          <div class="col-lg-5">
            <label for="filter-keyword" class="form-label">Tìm theo tên công ty hoặc lĩnh vực</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
              <input type="text" id="filter-keyword" name="q" value="<?= htmlspecialchars($searchTerm) ?>" class="form-control" placeholder="Ví dụ: Công nghệ, Marketing, TopCV" autocomplete="off">
            </div>
          </div>
          <div class="col-lg-3">
            <label for="filter-location" class="form-label">Địa điểm</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fa-solid fa-location-dot"></i></span>
              <input type="text" id="filter-location" name="location" value="<?= htmlspecialchars($locationFilter) ?>" class="form-control" placeholder="Toàn quốc">
            </div>
          </div>
          <div class="col-lg-2">
            <label for="filter-sort" class="form-label">Sắp xếp</label>
            <select id="filter-sort" name="sort" class="form-select">
              <option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>Nhiều việc làm nhất</option>
              <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Tin tuyển dụng mới</option>
              <option value="alphabet" <?= $sort === 'alphabet' ? 'selected' : '' ?>>Tên công ty (A-Z)</option>
            </select>
          </div>
          <div class="col-lg-2 d-grid">
            <button type="submit" class="btn btn-success">Tìm kiếm</button>
          </div>
        </form>

        <?php if (!empty($locationSuggestions)): ?>
          <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
            <span class="text-muted small">Địa điểm phổ biến:</span>
            <?php foreach ($locationSuggestions as $suggestedLocation): ?>
                <a class="badge bg-light text-dark" href="<?= $buildQuery(['location' => $suggestedLocation, 'page' => null]) ?>"><?= htmlspecialchars($suggestedLocation) ?></a>
            <?php endforeach; ?>
              <a class="badge bg-light text-dark" href="<?= $buildQuery(['location' => null, 'page' => null]) ?>">Toàn quốc</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="directory-results">
    <div class="container">
      <?php if (!empty($searchTerm) || !empty($locationFilter)): ?>
        <p class="text-muted mb-4">Đang hiển thị <strong><?= count($companies) ?></strong> / <strong><?= number_format($matchedEmployers) ?></strong> doanh nghiệp cho từ khóa <strong><?= htmlspecialchars($searchTerm ?: 'Tất cả') ?></strong><?php if ($locationFilter !== ''): ?> tại <strong><?= htmlspecialchars($locationFilter) ?></strong><?php endif; ?>.</p>
      <?php endif; ?>

      <?php if ($directoryQueryError): ?>
        <div class="alert alert-danger">Không thể tải danh sách doanh nghiệp lúc này. (<?= htmlspecialchars($directoryQueryError) ?>)</div>
      <?php elseif (empty($companies)): ?>
        <div class="company-empty">
          <i class="fa-regular fa-circle-question"></i>
          <h3 class="fw-semibold">Chưa tìm thấy doanh nghiệp phù hợp</h3>
          <p>Hãy thử điều chỉnh từ khóa tìm kiếm hoặc chọn địa điểm khác để khám phá thêm nhiều nhà tuyển dụng tiềm năng trên JobFind.</p>
          <a class="btn btn-outline-success mt-3" href="<?= $buildQuery(['q' => null, 'location' => null, 'page' => null]) ?>">Xóa bộ lọc</a>
        </div>
      <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
          <?php foreach ($companies as $company): ?>
            <?php
              $jobCount = (int)($company['job_count'] ?? 0);
              $address = trim((string)($company['address'] ?? ''));
              $website = trim((string)($company['website'] ?? ''));
              $about = trim((string)($company['about'] ?? ''));
              $logoPath = trim((string)($company['logo_path'] ?? ''));
              $logoUrl = '';
              if ($logoPath !== '') {
                  $logoUrl = BASE_URL . '/' . ltrim($logoPath, '/');
              }
              $companyInitial = strtoupper(substr($company['company_name'], 0, 1));
              if ($about !== '') {
                  if (function_exists('mb_strimwidth')) {
                      $aboutPreview = mb_strimwidth($about, 0, 180, '...');
                  } else {
                      $aboutPreview = strlen($about) > 180 ? substr($about, 0, 177) . '...' : $about;
                  }
              } else {
                  $aboutPreview = 'Doanh nghiệp đang cập nhật thông tin giới thiệu.';
              }
            ?>
            <div class="col">
              <article class="company-card">
                <div class="company-card-header">
                  <div class="company-logo">
                    <?php if ($logoUrl !== ''): ?>
                      <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($company['company_name']) ?>">
                    <?php else: ?>
                      <span class="company-logo-fallback"><?= htmlspecialchars($companyInitial) ?></span>
                    <?php endif; ?>
                  </div>
                  <div>
                    <h3><?= htmlspecialchars($company['company_name']) ?></h3>
                    <ul class="company-meta">
                      <?php if ($address !== ''): ?>
                        <li><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($address) ?></li>
                      <?php endif; ?>
                      <?php if ($website !== ''): ?>
                        <li><i class="fa-solid fa-globe"></i><a href="<?= htmlspecialchars($website) ?>" target="_blank" rel="noopener">Website</a></li>
                      <?php endif; ?>
                    </ul>
                  </div>
                </div>
                <p class="company-about"><?= htmlspecialchars($aboutPreview) ?></p>
                <div class="company-tags">
                  <span class="tag"><i class="fa-solid fa-briefcase me-1"></i><?= $jobCount ?> việc làm đang mở</span>
                  <?php if (!empty($company['latest_job_at'])): ?>
                    <?php $latestDate = date('d/m/Y', strtotime($company['latest_job_at'])); ?>
                    <span class="tag"><i class="fa-solid fa-clock me-1"></i>Cập nhật <?= $latestDate ?></span>
                  <?php endif; ?>
                </div>
                <div class="company-card-footer">
                  <div class="text-muted small">Ứng tuyển phù hợp tại JobFind</div>
                  <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-success" href="<?= BASE_URL ?>/employer/show.php?id=<?= (int)$company['id'] ?>">Hồ sơ doanh nghiệp</a>
                    <a class="btn btn-success" href="<?= BASE_URL ?>/job/share/index.php">Việc làm nổi bật</a>
                  </div>
                </div>
              </article>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1): ?>
          <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $startPage + 4);
            $startPage = max(1, $endPage - 4);
          ?>
          <nav class="mt-4" aria-label="Phân trang nhà tuyển dụng">
            <ul class="pagination justify-content-center">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page <= 1 ? '#' : $buildQuery(['page' => $page - 1]) ?>" tabindex="<?= $page <= 1 ? '-1' : '0' ?>" aria-label="Trang trước">&laquo;</a>
              </li>
              <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                  <a class="page-link" href="<?= $buildQuery(['page' => $p]) ?>"><?= $p ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page >= $totalPages ? '#' : $buildQuery(['page' => $page + 1]) ?>" tabindex="<?= $page >= $totalPages ? '-1' : '0' ?>" aria-label="Trang sau">&raquo;</a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
