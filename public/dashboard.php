<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Candidate.php';
require_once __DIR__ . '/../app/models/Application.php';
require_once __DIR__ . '/../app/models/SavedJob.php';
require_once __DIR__ . '/../app/models/Notification.php';
require_once __DIR__ . '/../app/services/JobRecommendationService.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/account/login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$role = (int)($_SESSION['role_id'] ?? 0);

if ($role === 2) {
    header('Location: ' . BASE_URL . '/employer/admin/dashboard.php');
    exit;
}

$userModel = new User();
$user = $userModel->getById($userId) ?: [];
$displayName = trim((string)($user['name'] ?? '')) ?: (string)($user['email'] ?? 'Tài khoản');

$recommendedJobs = [];
$recentApplications = [];
$stats = [
    'applications' => 0,
    'saved_jobs' => 0,
    'notifications' => 0,
];
$profileCompletion = 0;
$profileMissing = [];
$profileUpdated = null;
$candidateProfile = null;

if ($role === 3) {
    $candidateModel = new Candidate();
    $candidate = $candidateModel->getByUserId($userId);
    $candidateProfile = $candidateModel->getProfileByUserId($userId);
    $candidateId = $candidate ? (int)$candidate['id'] : 0;

    $applicationModel = new Application();
    $applicationResult = $applicationModel->listForCandidate($candidateId, [], 1, 4);
    $stats['applications'] = (int)($applicationResult['total'] ?? 0);
    $recentApplications = $applicationResult['rows'] ?? [];

    $savedJobModel = new SavedJob();
    $stats['saved_jobs'] = count($savedJobModel->getSavedJobIdsForUser($userId));

    $notificationModel = new Notification();
    $stats['notifications'] = $notificationModel->countUnread($userId);

    $recommendationService = new JobRecommendationService();
    $recommendedJobs = $recommendationService->getRecommendationsForUser($userId, 3);

    $profileChecks = [
        'Họ tên' => !empty($candidateProfile['full_name']),
        'Số điện thoại' => !empty($candidateProfile['phone']),
        'Tiêu đề nghề nghiệp' => !empty($candidateProfile['headline']),
        'Địa điểm làm việc' => !empty($candidateProfile['location']),
        'Kỹ năng' => !empty($candidateProfile['skills']) && $candidateProfile['skills'] !== '[]',
        'Kinh nghiệm' => !empty($candidateProfile['experience']) && $candidateProfile['experience'] !== '[]',
        'CV' => !empty($candidateProfile['cv_path']),
    ];
    $completedItems = count(array_filter($profileChecks));
    $profileCompletion = (int)round(($completedItems / max(1, count($profileChecks))) * 100);
    $profileMissing = array_keys(array_filter($profileChecks, static fn($isDone) => !$isDone));
    $profileUpdated = $candidateProfile['updated_at'] ?? $candidateProfile['created_at'] ?? null;
}

function dashboard_status_label(string $status): string
{
    $labels = [
        'applied' => 'Đã nộp',
        'viewed' => 'Đã xem',
        'shortlisted' => 'Qua vòng lọc',
        'rejected' => 'Từ chối',
        'hired' => 'Trúng tuyển',
        'withdrawn' => 'Đã rút',
    ];

    return $labels[$status] ?? 'Đang xử lý';
}

function dashboard_date(?string $date): string
{
    if (!$date) {
        return 'Chưa cập nhật';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y', $timestamp) : 'Chưa cập nhật';
}
?>

<?php
$pageTitle = 'Bảng điều khiển - JobFind';
$bodyClass = 'home-page dashboard-page';
$homeCssVersion = @filemtime(__DIR__ . '/assets/css/home.css') ?: time();
$dashboardCssVersion = @filemtime(__DIR__ . '/assets/css/dashboard.css') ?: time();
$additionalCSS = [
    '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">',
    '<link href="' . ASSETS_URL . '/css/home.css?v=' . $homeCssVersion . '" rel="stylesheet">',
    '<link href="' . ASSETS_URL . '/css/dashboard.css?v=' . $dashboardCssVersion . '" rel="stylesheet">',
];
require_once __DIR__ . '/includes/header.php';
?>

<main class="dashboard-shell">
    <div class="container">
        <section class="dashboard-hero">
            <div class="dashboard-hero__content">
                <span class="dashboard-eyebrow">Bảng điều khiển</span>
                <h1>Xin chào, <?= htmlspecialchars($displayName) ?></h1>
                <p>
                    Theo dõi hồ sơ, việc đã lưu và các cơ hội phù hợp trong một màn hình gọn hơn.
                </p>
                <div class="dashboard-meta">
                    <span><?= $role === 1 ? 'Quản trị viên' : 'Ứng viên' ?></span>
                    <span><?= date('d/m/Y') ?></span>
                </div>
            </div>
            <div class="dashboard-hero__actions">
                <?php if ($role === 1): ?>
                    <a class="dashboard-btn dashboard-btn--primary" href="<?= ADMIN_URL ?>/index.php">Vào trang quản trị</a>
                    <a class="dashboard-btn dashboard-btn--ghost" href="<?= BASE_URL ?>/account/logout.php">Đăng xuất</a>
                <?php else: ?>
                    <a class="dashboard-btn dashboard-btn--primary" href="<?= BASE_URL ?>/job/share/index.php">Tìm việc phù hợp</a>
                    <a class="dashboard-btn dashboard-btn--ghost" href="<?= BASE_URL ?>/candidate/profile.php">Cập nhật hồ sơ</a>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($role === 1): ?>
            <section class="dashboard-card dashboard-admin-card">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="dashboard-section-kicker">Quản trị</p>
                        <h2>Truy cập nhanh</h2>
                    </div>
                </div>
                <div class="dashboard-link-grid">
                    <a href="<?= ADMIN_URL ?>/index.php">Tổng quan hệ thống</a>
                    <a href="<?= ADMIN_URL ?>/user/users.php">Quản lý người dùng</a>
                    <a href="<?= ADMIN_URL ?>/jobs/index.php">Quản lý việc làm</a>
                    <a href="<?= ADMIN_URL ?>/applications/index.php">Quản lý ứng tuyển</a>
                </div>
            </section>
        <?php else: ?>
            <section class="dashboard-stats" aria-label="Tổng quan tài khoản">
                <article class="dashboard-stat">
                    <span>Đơn ứng tuyển</span>
                    <strong><?= number_format($stats['applications']) ?></strong>
                </article>
                <article class="dashboard-stat">
                    <span>Việc đã lưu</span>
                    <strong><?= number_format($stats['saved_jobs']) ?></strong>
                </article>
                <article class="dashboard-stat">
                    <span>Thông báo mới</span>
                    <strong><?= number_format($stats['notifications']) ?></strong>
                </article>
            </section>

            <div class="dashboard-layout">
                <div class="dashboard-main">
                    <section class="dashboard-card">
                        <div class="dashboard-section-heading">
                            <div>
                                <p class="dashboard-section-kicker">Gợi ý</p>
                                <h2>Việc làm phù hợp</h2>
                            </div>
                            <a href="<?= BASE_URL ?>/job/share/index.php">Xem tất cả</a>
                        </div>

                        <?php if (empty($recommendedJobs)): ?>
                            <div class="dashboard-empty">
                                <strong>Chưa có đủ dữ liệu để gợi ý.</strong>
                                <span>Cập nhật kỹ năng hoặc lưu vài việc làm để hệ thống hiểu bạn hơn.</span>
                            </div>
                        <?php else: ?>
                            <div class="dashboard-list">
                                <?php foreach ($recommendedJobs as $job): ?>
                                    <article class="dashboard-list-item">
                                        <div>
                                            <h3><?= htmlspecialchars($job['title'] ?? 'Tin tuyển dụng') ?></h3>
                                            <p>
                                                <?= htmlspecialchars($job['company_name'] ?? 'Nhà tuyển dụng JobFind') ?>
                                                · <?= htmlspecialchars($job['location'] ?? 'Toàn quốc') ?>
                                            </p>
                                            <span><?= htmlspecialchars($job['salary'] ?? 'Lương thỏa thuận') ?></span>
                                        </div>
                                        <a href="<?= BASE_URL ?>/job/share/view.php?id=<?= (int)($job['id'] ?? 0) ?>">Chi tiết</a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="dashboard-card">
                        <div class="dashboard-section-heading">
                            <div>
                                <p class="dashboard-section-kicker">Theo dõi</p>
                                <h2>Ứng tuyển gần đây</h2>
                            </div>
                            <a href="<?= BASE_URL ?>/job/applications.php">Quản lý đơn</a>
                        </div>

                        <?php if (empty($recentApplications)): ?>
                            <div class="dashboard-empty">
                                <strong>Bạn chưa ứng tuyển việc nào.</strong>
                                <span>Khi nộp hồ sơ, trạng thái ứng tuyển sẽ hiển thị tại đây.</span>
                            </div>
                        <?php else: ?>
                            <div class="dashboard-list">
                                <?php foreach ($recentApplications as $application): ?>
                                    <article class="dashboard-list-item dashboard-list-item--compact">
                                        <div>
                                            <h3><?= htmlspecialchars($application['job_title'] ?? 'Tin tuyển dụng') ?></h3>
                                            <p>
                                                <?= htmlspecialchars($application['employer_name'] ?? 'Nhà tuyển dụng') ?>
                                                · <?= dashboard_date($application['applied_at'] ?? null) ?>
                                            </p>
                                        </div>
                                        <span class="dashboard-status">
                                            <?= htmlspecialchars(dashboard_status_label((string)($application['status'] ?? ''))) ?>
                                        </span>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <aside class="dashboard-side">
                    <section class="dashboard-card dashboard-profile-card">
                        <div class="dashboard-section-heading">
                            <div>
                                <p class="dashboard-section-kicker">Hồ sơ</p>
                                <h2>Hoàn thiện hồ sơ</h2>
                            </div>
                            <strong><?= $profileCompletion ?>%</strong>
                        </div>
                        <div class="dashboard-progress" aria-label="Mức độ hoàn thiện hồ sơ">
                            <span style="width: <?= $profileCompletion ?>%;"></span>
                        </div>
                        <p class="dashboard-card-note">
                            Cập nhật gần nhất: <?= dashboard_date($profileUpdated) ?>
                        </p>
                        <?php if (!empty($profileMissing)): ?>
                            <div class="dashboard-missing">
                                <span>Cần bổ sung:</span>
                                <p><?= htmlspecialchars(implode(', ', array_slice($profileMissing, 0, 4))) ?></p>
                            </div>
                        <?php endif; ?>
                        <a class="dashboard-text-link" href="<?= BASE_URL ?>/candidate/profile.php">Cập nhật hồ sơ</a>
                    </section>

                    <section class="dashboard-card">
                        <div class="dashboard-section-heading">
                            <div>
                                <p class="dashboard-section-kicker">Liên kết</p>
                                <h2>Thao tác nhanh</h2>
                            </div>
                        </div>
                        <div class="dashboard-link-list">
                            <a href="<?= BASE_URL ?>/job/share/index.php">Tìm việc làm</a>
                            <a href="<?= BASE_URL ?>/job/share/index.php?saved=1">Việc làm đã lưu</a>
                            <a href="<?= BASE_URL ?>/job/applications.php">Đơn ứng tuyển</a>
                            <a href="<?= BASE_URL ?>/candidate/upload_cv.php">Tải CV</a>
                        </div>
                    </section>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
