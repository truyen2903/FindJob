<footer class="jf-footer mt-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="jf-footer-brand">
          <img src="<?= ASSETS_URL ?>/img/logo.png" alt="JobFind" height="40" class="mb-3">
          <p>JobFind mang tới trải nghiệm tìm việc và tuyển dụng chuyên nghiệp, chuẩn phong cách TopCV cho người Việt.</p>
          <div class="d-flex gap-3 mt-3">
            <a href="#" class="social-link"><i class="fa-brands fa-facebook-f"></i></a>
            <a href="#" class="social-link"><i class="fa-brands fa-linkedin-in"></i></a>
            <a href="#" class="social-link"><i class="fa-brands fa-tiktok"></i></a>
            <a href="#" class="social-link"><i class="fa-brands fa-youtube"></i></a>
          </div>
        </div>
      </div>
      <div class="col-lg-2 col-md-4">
        <h6 class="fw-semibold mb-3">Dành cho ứng viên</h6>
        <ul class="list-unstyled jf-footer-links">
          <li><a href="<?= BASE_URL ?>/job/share/index.php">Tìm việc làm</a></li>
          <li><a href="<?= BASE_URL ?>/candidate/profile.php">Tạo CV chuẩn TopCV</a></li>
          <li><a href="<?= BASE_URL ?>/index.php#career">Cẩm nang nghề nghiệp</a></li>
          <li><a href="#">Hỏi đáp sự nghiệp</a></li>
        </ul>
      </div>
      <div class="col-lg-2 col-md-4">
        <h6 class="fw-semibold mb-3">Dành cho NTD</h6>
        <ul class="list-unstyled jf-footer-links">
          <li><a href="<?= BASE_URL ?>/job/create.php">Đăng tin tuyển dụng</a></li>
          <li><a href="#">Tìm hồ sơ ứng viên</a></li>
          <li><a href="#">Giải pháp nhân sự</a></li>
          <li><a href="#">Tư vấn tuyển dụng</a></li>
        </ul>
      </div>
      <div class="col-lg-4 col-md-4">
        <h6 class="fw-semibold mb-3">Nhận bản tin việc làm</h6>
        <p>Đừng bỏ lỡ cơ hội nghề nghiệp mới. Đăng ký nhận email mỗi tuần.</p>
        <form class="d-flex flex-column flex-sm-row gap-2">
          <input type="email" class="form-control" placeholder="Nhập email của bạn">
          <button type="submit" class="btn btn-success">Đăng ký</button>
        </form>
        <div class="mt-4">
          <p class="fw-semibold mb-1">Tổng đài hỗ trợ</p>
          <a href="tel:0978843662" class="text-decoration-none d-block">0978.843.662</a>
          <a href="mailto:support@jobfind.vn" class="text-decoration-none">support@jobfind.vn</a>
        </div>
      </div>
    </div>
    <div class="jf-footer-bottom mt-4 pt-4">
      <div class="row align-items-center g-3">
        <div class="col-md-6">
          <small>&copy; <?= date('Y') ?> JobFind. Giữ vững cam kết đồng hành cùng sự nghiệp của bạn.</small>
        </div>
        <div class="col-md-6">
          <div class="d-flex justify-content-md-end gap-3 jf-footer-bottom-links">
            <a href="#">Điều khoản sử dụng</a>
            <a href="#">Chính sách bảo mật</a>
            <a href="#">Quy chế hoạt động</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= ASSETS_URL ?>/js/form-validation.js" defer></script>
<?php
if (!empty($additionalScripts)) {
  foreach ($additionalScripts as $scriptTag) {
    echo $scriptTag . "\n";
  }
}
?>
<script>
window.addEventListener('load', function () {
  document.body.classList.add('page-loaded');
});
</script>
</body>
</html>
