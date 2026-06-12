<aside id="sidebar" class="sidebar">
  <ul class="sidebar-nav" id="sidebar-nav">

    <li class="nav-item">
      <a class="nav-link" href="<?= ADMIN_URL ?>/dashboard.php">
        <i class="bi bi-grid"></i>
        <span>Bảng điều khiển</span>
      </a>
    </li>

    <!-- Quản lý người dùng -->
    <li class="nav-item">
      <a class="nav-link collapsed"
         data-bs-toggle="collapse"
         href="#userSubmenu"
         aria-expanded="false"
         aria-controls="userSubmenu">
        <i class="bi bi-people"></i>
        <span>Quản lý người dùng</span>
        <i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul class="collapse list-unstyled ps-4" id="userSubmenu" data-bs-parent="#sidebar-nav">
        <li><a class="nav-link" href="<?= ADMIN_URL ?>/user/users.php">Danh sách người dùng</a></li>
        <li><a class="nav-link" href="<?= ADMIN_URL ?>/user/add_user.php">Thêm người dùng</a></li>
        <li><a class="nav-link" href="<?= ADMIN_URL ?>/roles/index.php">Vai trò &amp; phân quyền</a></li>
      </ul>
    </li>

    <!-- Quản lý nhà tuyển dụng -->
    <li class="nav-item">
      <a class="nav-link collapsed"
         data-bs-toggle="collapse"
         href="#employerSubmenu"
         aria-expanded="false"
         aria-controls="employerSubmenu">
        <i class="bi bi-building"></i>
        <span>Quản lý nhà tuyển dụng</span>
        <i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul class="collapse list-unstyled ps-4" id="employerSubmenu" data-bs-parent="#sidebar-nav">
        <li><a class="nav-link" href="<?= ADMIN_URL ?>/employers/employers.php">Danh sách nhà tuyển dụng</a></li>
        <li><a class="nav-link" href="<?= ADMIN_URL ?>/employers/add_employer.php">Thêm nhà tuyển dụng</a></li>
      </ul>
    </li>

    <!-- Quản lý ứng viên -->
    <li class="nav-item">
      <a class="nav-link collapsed"
         data-bs-toggle="collapse"
         href="#candidateSubmenu"
         aria-expanded="false"
         aria-controls="candidateSubmenu">
        <i class="bi bi-person-lines-fill"></i>
        <span>Quản lý ứng viên</span>
        <i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul class="collapse list-unstyled ps-4" id="candidateSubmenu" data-bs-parent="#sidebar-nav">
        <li><a class="nav-link" href="<?= ADMIN_URL ?>/candidates/index.php">Danh sách ứng viên</a></li>
      </ul>
    </li>

    <!-- Quản lý tin tuyển dụng -->
    <li class="nav-item">
      <a class="nav-link collapsed"
         data-bs-toggle="collapse"
         href="#jobSubmenu"
         aria-expanded="false"
         aria-controls="jobSubmenu">
        <i class="bi bi-briefcase"></i>
        <span>Quản lý tin tuyển dụng</span>
        <i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul class="collapse list-unstyled ps-4" id="jobSubmenu" data-bs-parent="#sidebar-nav">
        <li><a class="nav-link" href="<?= ADMIN_URL ?>/jobs/index.php">Duyệt tin tuyển dụng</a></li>
      </ul>
    </li>

    <li class="nav-item">
      <a class="nav-link" href="<?= ADMIN_URL ?>/applications/index.php">
        <i class="bi bi-kanban"></i>
        <span>Theo dõi ứng tuyển</span>
      </a>
    </li>
    

    <li class="nav-item">
      <a class="nav-link" href="<?= BASE_URL ?>/index.php">
        <i class="bi bi-shield-lock"></i>
        <span>Quay lại trang người dùng</span>
      </a>
    </li>

  </ul>
</aside>
