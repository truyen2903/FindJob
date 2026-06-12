<header id="header" class="header fixed-top d-flex align-items-center">
  <div class="d-flex align-items-center justify-content-between">
    <a href="<?= ADMIN_URL ?>/index.php" class="logo d-flex align-items-center">
      <img src="<?= ASSETS_URL ?>/img/logo.png" alt="">
      <span class="d-none d-lg-block">JobFinder Admin</span>
    </a>
    <i class="bi bi-list toggle-sidebar-btn"></i>
  </div>

  <nav class="header-nav ms-auto">
    <ul class="d-flex align-items-center">
      <li class="nav-item dropdown pe-3">
        <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
          <span class="d-none d-md-block dropdown-toggle ps-2">Admin</span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
          <li><a class="dropdown-item d-flex align-items-center" href="<?= BASE_URL ?>/account/logout.php">Đăng xuất</a></li>
        </ul>
      </li>
    </ul>
  </nav>
</header>
