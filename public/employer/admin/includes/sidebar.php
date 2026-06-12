<?php
$employerNavActive = $employerNavActive ?? '';
$sidebarItems = [
    'dashboard' => [
        'label' => 'Bảng điều khiển',
        'icon' => 'fa-solid fa-gauge-high',
        'href' => BASE_URL . '/employer/admin/dashboard.php',
    ],
    'jobs' => [
        'label' => 'Tin tuyển dụng',
        'icon' => 'fa-solid fa-briefcase',
        'href' => BASE_URL . '/employer/admin/jobs.php',
    ],
    'applications' => [
        'label' => 'Hồ sơ ứng viên',
        'icon' => 'fa-regular fa-id-card',
        'href' => BASE_URL . '/employer/admin/applications.php',
    ],
    'company' => [
        'label' => 'Hồ sơ doanh nghiệp',
        'icon' => 'fa-regular fa-building',
        'href' => BASE_URL . '/employer/edit.php',
    ],
    'logout' => [
        'label' => 'Đăng xuất',
        'icon' => 'fa-solid fa-arrow-right-from-bracket',
        'href' => BASE_URL . '/account/logout.php',
    ],
    'Quay lại trang chính' => [
        'label' => 'Quay lại trang chính',
        'icon' => 'fa-solid fa-arrow-right-from-bracket',
        'href' => BASE_URL . '/',
    ],
];
?>
<aside class="ea-sidebar">
  <a class="ea-sidebar__brand" href="<?= BASE_URL ?>/employer/admin/dashboard.php">
    <img src="<?= ASSETS_URL ?>/img/logo.png" alt="JobFind">
    <span>JobFind Employer</span>
  </a>
  <ul class="ea-sidebar__menu">
    <?php foreach ($sidebarItems as $key => $item): ?>
      <li>
        <a class="ea-sidebar__link <?= $employerNavActive === $key ? 'ea-sidebar__link--active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>">
          <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
          <span><?= htmlspecialchars($item['label']) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</aside>
