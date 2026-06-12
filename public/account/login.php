<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/controllers/AuthController.php';

$auth = new AuthController();
$error = null;
$email = '';
$authCssVersion = filemtime(dirname(__DIR__) . '/assets/css/auth.css') ?: time();

if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = $auth->login($email, $password);
    if ($result['success']) {
        $role = $_SESSION['role_id'] ?? 3;
        if ($role == 1) {
            header('Location: ' . ADMIN_URL . '/index.php');
        } elseif ($role == 2) {
            header('Location: ' . BASE_URL . '/employer/admin/dashboard.php');
        } else {
            header('Location: ' . BASE_URL . '/dashboard.php');
        }
        exit;
    }
    $error = $result['message'] ?? 'Không thể đăng nhập. Vui lòng thử lại.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập JobFind</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/auth.css?v=<?= $authCssVersion ?>" rel="stylesheet">
</head>
<body>
    <main class="auth-page">
        <section class="auth-shell" aria-label="Trang đăng nhập JobFind">
            <div class="auth-panel">
                <a class="auth-brand" href="<?= BASE_URL ?>/index.php">
                    <span class="auth-brand-mark" aria-hidden="true"><i class="fa-solid fa-briefcase"></i></span>
                    JobFind
                </a>

                <div class="auth-headline">
                    <h1>Chào mừng bạn quay trở lại</h1>
                    <p>Đăng nhập để quản lý hồ sơ, theo dõi tin tuyển dụng và nhận các cơ hội nghề nghiệp phù hợp hơn.</p>
                </div>

                <?php if ($error): ?>
                    <div class="auth-alert" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" class="auth-form">
                    <div class="auth-field">
                        <div class="auth-field-label"><label for="email">Email</label></div>
                        <div class="auth-input">
                            <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                            <input id="email" type="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="Email của bạn" autocomplete="email" required>
                        </div>
                    </div>

                    <div class="auth-field">
                        <div class="auth-field-label">
                            <label for="password">Mật khẩu</label>
                            <a href="#">Quên mật khẩu?</a>
                        </div>
                        <div class="auth-input">
                            <i class="fa-solid fa-shield" aria-hidden="true"></i>
                            <input id="password" type="password" name="password" class="js-password" placeholder="Mật khẩu" autocomplete="current-password" required>
                            <button class="auth-eye toggle-password" type="button" aria-label="Hiển thị mật khẩu">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button class="auth-submit" type="submit">Đăng nhập</button>
                </form>

                <div class="auth-divider"><span>Hoặc đăng nhập bằng</span></div>

                <div class="auth-socials">
                    <a href="<?= BASE_URL ?>/google_login.php" class="auth-social is-google">
                        <i class="fa-brands fa-google"></i> Google
                    </a>
                    <a href="#" class="auth-social is-facebook is-disabled" aria-disabled="true">
                        <i class="fa-brands fa-facebook-f"></i> Facebook
                    </a>
                    <a href="#" class="auth-social is-linkedin is-disabled" aria-disabled="true">
                        <i class="fa-brands fa-linkedin-in"></i> LinkedIn
                    </a>
                </div>

                <p class="auth-signup">Bạn chưa có tài khoản? <a href="<?= BASE_URL ?>/account/register.php">Đăng ký ngay</a></p>

                <div class="auth-help">
                    Bạn gặp khó khăn khi tạo tài khoản?<br>
                    Vui lòng liên hệ <a href="tel:02471076480"><strong>(024) 7107 6480</strong></a> trong giờ hành chính.
                </div>
            </div>

            <aside class="auth-hero">
                <div class="auth-floating">
                    <small>Hồ sơ nổi bật</small>
                    <b>+1.240 ứng viên mới</b>
                </div>

                <div class="auth-hero-content">
                    <div class="auth-hero-logo">
                        <i class="fa-solid fa-briefcase" aria-hidden="true"></i>
                        JobFind
                    </div>
                    <h2>Tiếp lợi thế.<br>Nối thành công.</h2>
                    <p>Hệ sinh thái tuyển dụng giúp ứng viên xây dựng hồ sơ chuyên nghiệp và giúp doanh nghiệp tiếp cận đúng nhân sự.</p>

                    <div class="auth-stats">
                        <div class="auth-stat"><strong>18K+</strong><span>việc làm đang mở</span></div>
                        <div class="auth-stat"><strong>6K+</strong><span>doanh nghiệp xác thực</span></div>
                        <div class="auth-stat"><strong>92%</strong><span>hồ sơ được gợi ý phù hợp</span></div>
                    </div>
                </div>
            </aside>
        </section>

        <footer class="auth-footer">© 2016. All Rights Reserved. JobFind Vietnam JSC.</footer>
    </main>

    <script>
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function () {
                const input = this.parentElement.querySelector('.js-password');
                const icon = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
    </script>
    <script src="<?= ASSETS_URL ?>/js/form-validation.js" defer></script>
</body>
</html>
