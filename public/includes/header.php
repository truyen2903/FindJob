<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = $_SESSION['role_id'] ?? null;
$pageTitle = $pageTitle ?? 'JobFind - Việc làm chất lượng, chuẩn TopCV';
$bodyClass = $bodyClass ?? '';
$additionalCSS = isset($additionalCSS) && is_array($additionalCSS) ? $additionalCSS : [];
$additionalScripts = isset($additionalScripts) && is_array($additionalScripts) ? $additionalScripts : [];
$avatarUrl = $_SESSION['avatar_url'] ?? null;
$avatarChecked = $_SESSION['avatar_checked'] ?? false;
$dashboardUrl = BASE_URL . '/dashboard.php';
if ((int)$role === 2) {
    $dashboardUrl = BASE_URL . '/employer/admin/dashboard.php';
}

if (!$avatarChecked && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../../app/models/User.php';
    $headerUserModel = new User();
    $headerUser = $headerUserModel->getById((int)$_SESSION['user_id']);
    if ($headerUser) {
        if (!empty($headerUser['name'])) {
            $_SESSION['user_name'] = $headerUser['name'];
        }
        if (!empty($headerUser['avatar_path'])) {
            $avatarUrl = BASE_URL . '/' . ltrim($headerUser['avatar_path'], '/');
            $_SESSION['avatar_url'] = $avatarUrl;
        } else {
            $_SESSION['avatar_url'] = null;
        }
    }
    $_SESSION['avatar_checked'] = true;
}

$avatarUrl = $_SESSION['avatar_url'] ?? $avatarUrl;

$savedJobsCount = 0;
if (isset($_SESSION['user_id']) && (int)($_SESSION['role_id'] ?? 0) === 3) {
    require_once __DIR__ . '/../../app/models/SavedJob.php';
    $headerSavedJobModel = new SavedJob();
    $savedJobsCount = count($headerSavedJobModel->getSavedJobIdsForUser((int)$_SESSION['user_id']));
}

$notificationItems = [];
$notificationUnreadCount = 0;
$currentUri = $_SERVER['REQUEST_URI'] ?? BASE_URL . '/index.php';
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../../app/models/Notification.php';
    $headerNotificationModel = new Notification();
    $notificationUnreadCount = $headerNotificationModel->countUnread((int)$_SESSION['user_id']);
    $notificationItems = $headerNotificationModel->getRecent((int)$_SESSION['user_id'], 6);
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/style.css">
    <?php foreach ($additionalCSS as $cssTag): ?>
        <?= $cssTag . "\n" ?>
    <?php endforeach; ?>
</head>
<body class="<?= htmlspecialchars(trim($bodyClass)) ?>">
<header class="jf-header shadow-sm">
    
    
    <nav class="navbar navbar-expand-lg navbar-light jf-navbar">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>/index.php">
                <img src="<?= ASSETS_URL ?>/img/logo.png" alt="JobFind" class="me-2" height="36">
                <span>JobFind</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#jfMainNav" aria-controls="jfMainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="jfMainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 jf-main-menu">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="jobsMenu" data-bs-toggle="dropdown" aria-expanded="false">
                            Việc làm
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="jobsMenu">
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/job/share/index.php">Tất cả việc làm</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/job/share/hot.php?filter=hot">Việc làm hot</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/job/share/index.php?filter=remote">Việc làm remote</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/job/share/index.php?filter=intern">Việc làm thực tập</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="cvMenu" data-bs-toggle="dropdown" aria-expanded="false">CV &amp; Hồ sơ</a>
                        <ul class="dropdown-menu" aria-labelledby="cvMenu">
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/candidate/profile.php">Tạo CV online</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/candidate/profile.php">Quản lý hồ sơ</a></li>
                            <li><a class="dropdown-item" href="#">Thư viện mẫu CV</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="careerMenu" data-bs-toggle="dropdown" aria-expanded="false">Phát triển sự nghiệp</a>
                        <ul class="dropdown-menu" aria-labelledby="careerMenu">
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/index.php#career">Cẩm nang nghề nghiệp</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/index.php#career">Tính lương gộp - ròng</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/index.php#career">Bí quyết phỏng vấn</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/employer/index.php">Top công ty</a>
                    </li>
                    <?php if ($role == 1): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= ADMIN_URL ?>/index.php">Quản trị</a>
                        </li>
                    <?php endif; ?>
                </ul>
                    <div class="d-flex align-items-center gap-2 gap-lg-3 jf-nav-actions">
                    <div class="d-none d-lg-flex flex-column text-end me-2 jf-recruiter-cta">
                        <span class="small text-muted">Bạn là nhà tuyển dụng?</span>
                        <a class="jf-link-arrow" href="<?= BASE_URL ?>/job/create.php">
                            Đăng tuyển ngay <i class="fa-solid fa-angles-right ms-1"></i>
                        </a>
                    </div>
                    <?php if (isset($_SESSION['user_id'])): ?>
                                                <?php $displayName = $_SESSION['user_name'] ?? $_SESSION['email'] ?? 'Tài khoản của bạn'; ?>
                                                <div class="dropdown">
                                                    <button class="btn btn-icon position-relative" type="button" id="jfNotificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Thông báo">
                                                        <i class="fa-solid fa-bell"></i>
                                                        <?php if ($notificationUnreadCount > 0): ?>
                                                            <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle"><?= $notificationUnreadCount > 99 ? '99+' : $notificationUnreadCount ?></span>
                                                        <?php endif; ?>
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-end shadow jf-notification-menu" aria-labelledby="jfNotificationDropdown" style="min-width: 320px;">
                                                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                                                            <span class="fw-semibold">Thông báo</span>
                                                            <?php if ($notificationUnreadCount > 0): ?>
                                                                <a class="small" href="<?= BASE_URL ?>/account/notifications_mark_read.php?redirect=<?= rawurlencode($currentUri) ?>">Đánh dấu đã đọc</a>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (empty($notificationItems)): ?>
                                                            <div class="px-3 py-4 text-muted small">Chưa có thông báo mới.</div>
                                                        <?php else: ?>
                                                            <div class="list-group list-group-flush">
                                                                <?php foreach ($notificationItems as $notif): ?>
                                                                                                        <?php
                                                                                                            $messageLines = preg_split("/(\r\n|\r|\n)/", (string)($notif['message'] ?? ''));
                                                                                                            $actionUrl = '';
                                                                                                            if (!empty($messageLines)) {
                                                                                                                    $lastLine = trim((string)end($messageLines));
                                                                                                                    $isAbsoluteUrl = $lastLine !== '' && filter_var($lastLine, FILTER_VALIDATE_URL);
                                                                                                                    $isRelativeUrl = $lastLine !== '' && ($lastLine[0] ?? '') === '/';
                                                                                                                    $isInternalUrl = $lastLine !== '' && strpos($lastLine, BASE_URL) === 0;
                                                                                                                    if ($isAbsoluteUrl || $isRelativeUrl || $isInternalUrl) {
                                                                                                                            $actionUrl = $lastLine;
                                                                                                                            array_pop($messageLines);
                                                                                                                    }
                                                                                                            }
                                                                        $messageBody = trim(implode("\n", $messageLines));
                                                                        $iconClass = $notif['icon_path'] ?? 'fa-solid fa-bell';
                                                                    ?>
                                                                    <div class="list-group-item px-3 py-3 <?= !$notif['is_read'] ? 'bg-light' : '' ?>">
                                                                        <div class="d-flex gap-3">
                                                                            <span class="text-success"><i class="<?= htmlspecialchars($iconClass) ?>"></i></span>
                                                                            <div class="flex-fill">
                                                                                <div class="fw-semibold small mb-1"><?= htmlspecialchars($notif['title'] ?? 'Thông báo') ?></div>
                                                                                <div class="small text-muted"><?= nl2br(htmlspecialchars($messageBody)) ?></div>
                                                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                                                    <span class="text-muted" style="font-size: 12px;"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($notif['created_at'] ?? 'now'))) ?></span>
                                                                                                                                <?php if ($actionUrl !== ''): ?>
                                                                                                                                    <a class="small" href="<?= BASE_URL ?>/account/notifications_mark_read.php?id=<?= (int)$notif['id'] ?>&redirect=<?= rawurlencode($actionUrl) ?>">Xem chi tiết</a>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <button class="btn btn-icon" type="button" aria-label="Tin nhắn">
                                                    <i class="fa-solid fa-envelope"></i>
                                                </button>
                                                <?php if ((int)($_SESSION['role_id'] ?? 0) === 3): ?>
                                                    <a class="btn btn-icon position-relative" href="<?= BASE_URL ?>/job/share/index.php?saved=1" aria-label="Việc làm yêu thích">
                                                        <i class="fa-solid fa-heart"></i>
                                                        <?php if ($savedJobsCount > 0): ?>
                                                            <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle"><?= $savedJobsCount > 99 ? '99+' : $savedJobsCount ?></span>
                                                        <?php endif; ?>
                                                    </a>
                                                <?php else: ?>
                                                    <a class="btn btn-icon" href="<?= BASE_URL ?>/account/login.php" aria-label="Đăng nhập để lưu việc">
                                                        <i class="fa-regular fa-heart"></i>
                                                    </a>
                                                <?php endif; ?>
                        <div class="dropdown">
                            <button class="btn btn-icon jf-avatar-trigger p-0" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Tài khoản của bạn">
                              <?php if (!empty($avatarUrl)): ?>
                                <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar" class="jf-avatar-image" style="width:38px;height:38px;object-fit:cover;border-radius:50%;display:block;border:1px solid rgba(0,0,0,0.06);">
                              <?php else: ?>
                                <span class="jf-avatar-initials d-inline-flex align-items-center justify-content-center" style="width:38px;height:38px;border-radius:50%;background:#f1f3f5;color:#495057;font-weight:600;font-size:14px;">
                                  <?= htmlspecialchars(strtoupper(substr(trim($displayName), 0, 1))) ?>
                                </span>
                              <?php endif; ?>
                              <i class="fa-solid fa-chevron-down jf-avatar-caret ms-2"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end jf-profile-menu p-0 overflow-hidden">
                                <div class="jf-profile-section">
                                    <h6 class="dropdown-header">Quản lý tìm việc</h6>
                                    <a class="dropdown-item" href="<?= htmlspecialchars($dashboardUrl) ?>"><i class="fa-solid fa-gauge-high me-2"></i>Bảng điều khiển</a>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>/job/share/index.php?saved=1"><i class="fa-regular fa-bookmark me-2"></i>Việc làm đã lưu</a>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>/job/applications.php"><i class="fa-regular fa-paper-plane me-2"></i>Việc làm đã ứng tuyển</a>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>/job/share/index.php?filter=match"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Việc làm phù hợp</a>
                                </div>
                                <div class="jf-profile-section">
                                    <h6 class="dropdown-header">CV &amp; Cover Letter</h6>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>/candidate/profile.php"><i class="fa-regular fa-file-lines me-2"></i>CV của tôi</a>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>/candidate/profile.php#cover-letter"><i class="fa-regular fa-note-sticky me-2"></i>Cover Letter</a>
                                </div>
                                <div class="jf-profile-section">
                                    <a class="dropdown-item" href="<?= BASE_URL ?>/dashboard.php#settings"><i class="fa-solid fa-gear me-2"></i>Cài đặt tài khoản</a>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>/account/logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Đăng xuất</a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <a class="btn btn-link text-decoration-none" href="<?= BASE_URL ?>/account/login.php">Đăng nhập</a>
                        <a class="btn btn-success" href="<?= BASE_URL ?>/account/register.php">Đăng ký ngay</a>
                        <a class="btn btn-outline-success d-none d-lg-inline-flex" href="<?= BASE_URL ?>/job/create.php">Dành cho NTD</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>
