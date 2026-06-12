<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 4) . '/config/config.php';

if (empty($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 2) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$employerNavActive = $employerNavActive ?? '';
$pageTitle = $pageTitle ?? 'Bảng điều khiển nhà tuyển dụng';
$bodyClass = trim('employer-admin ' . ($bodyClass ?? ''));
$additionalCSS = isset($additionalCSS) && is_array($additionalCSS) ? $additionalCSS : [];
$additionalScripts = isset($additionalScripts) && is_array($additionalScripts) ? $additionalScripts : [];
$employerAdminCssVersion = filemtime(dirname(__DIR__, 3) . '/assets/css/employer-admin.css') ?: time();

$employerCompanyName = $employerCompanyName ?? ($_SESSION['employer_company_name'] ?? 'Nhà tuyển dụng JobFind');
$employerInitial = strtoupper(substr($employerCompanyName, 0, 1));
if ($employerInitial === '') {
  $employerInitial = 'J';
}
$employerProfileUrl = $employerProfileUrl ?? ($_SESSION['employer_profile_url'] ?? BASE_URL . '/employer/show.php');

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
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/employer-admin.css?v=<?= $employerAdminCssVersion ?>">
    <?php foreach ($additionalCSS as $cssTag): ?>
        <?= $cssTag . "\n" ?>
    <?php endforeach; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">
<div class="ea-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="ea-main">
    <header class="ea-topbar">
      <div>
        <h1 class="ea-topbar__title"><?= htmlspecialchars($pageTitle) ?></h1>
        <div class="ea-topbar__meta">
          <span><i class="fa-regular fa-building me-2"></i><?= htmlspecialchars($employerCompanyName) ?></span>
          <span><i class="fa-regular fa-calendar me-2"></i><?= date('d/m/Y') ?></span>
        </div>
      </div>
      <div class="ea-topbar__actions">
  <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($employerProfileUrl) ?>">Xem hồ sơ công ty</a>
        <div class="ea-avatar" title="<?= htmlspecialchars($employerCompanyName) ?>"><?= htmlspecialchars($employerInitial) ?></div>
      </div>
    </header>
    <div class="ea-content">
