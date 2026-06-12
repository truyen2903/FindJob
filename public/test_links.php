<?php
// Test Page - JobFind
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Links - JobFind</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        .test-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .test-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin-bottom: 2rem;
        }
        .test-header {
            background: linear-gradient(135deg, #00b14f, #33d687);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 2rem;
        }
        .test-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid #e9ecef;
        }
        .test-section:last-child {
            border-bottom: none;
        }
        .test-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        .test-link:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        .badge-custom {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
        }
        .btn-action {
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <div class="test-container">
        
        <div class="test-header">
            <h1 class="mb-3">
                <i class="fa-solid fa-flask me-3"></i>
                JobFind Test Center
            </h1>
            <p class="mb-0">Kiểm tra tất cả các đường dẫn và chức năng</p>
        </div>
        
        <div class="test-card">
            <div class="test-section">
                <h4 class="mb-3">
                    <i class="fa-solid fa-gear me-2 text-primary"></i>
                    Cấu hình Constants
                </h4>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="test-link">
                            <span><strong>BASE_URL:</strong></span>
                            <code><?= BASE_URL ?></code>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="test-link">
                            <span><strong>ASSETS_URL:</strong></span>
                            <code><?= ASSETS_URL ?></code>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="test-section">
                <h4 class="mb-3">
                    <i class="fa-solid fa-house me-2 text-success"></i>
                    Trang chính
                </h4>
                <a href="<?= BASE_URL ?>/index.php" class="btn btn-success btn-action w-100 justify-content-center">
                    <i class="fa-solid fa-home"></i>
                    Mở Trang chủ
                </a>
            </div>
            
            <div class="test-section">
                <h4 class="mb-3">
                    <i class="fa-solid fa-user me-2 text-info"></i>
                    Xác thực & Dashboard
                </h4>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?= BASE_URL ?>/account/login.php" class="btn btn-info btn-action">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        Đăng nhập
                    </a>
                    <a href="<?= BASE_URL ?>/account/register.php" class="btn btn-primary btn-action">
                        <i class="fa-solid fa-user-plus"></i>
                        Đăng ký
                    </a>
                    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-warning btn-action">
                        <i class="fa-solid fa-gauge-high"></i>
                        Dashboard
                    </a>
                </div>
            </div>
            
            <div class="test-section">
                <h4 class="mb-3">
                    <i class="fa-solid fa-image me-2 text-warning"></i>
                    Assets (CSS & JS)
                </h4>
                <div class="row g-2">
                    <div class="col-md-6">
                        <a href="<?= ASSETS_URL ?>/style.css" target="_blank" class="test-link text-decoration-none">
                            <span><i class="fa-brands fa-css3-alt me-2 text-primary"></i>style.css</span>
                            <span class="badge bg-primary badge-custom">CSS</span>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="<?= ASSETS_URL ?>/css/dashboard.css" target="_blank" class="test-link text-decoration-none">
                            <span><i class="fa-brands fa-css3-alt me-2 text-primary"></i>dashboard.css</span>
                            <span class="badge bg-primary badge-custom">CSS</span>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="<?= ASSETS_URL ?>/js/homepage.js" target="_blank" class="test-link text-decoration-none">
                            <span><i class="fa-brands fa-js me-2 text-warning"></i>homepage.js</span>
                            <span class="badge bg-warning badge-custom">JS</span>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="<?= ASSETS_URL ?>/js/dashboard.js" target="_blank" class="test-link text-decoration-none">
                            <span><i class="fa-brands fa-js me-2 text-warning"></i>dashboard.js</span>
                            <span class="badge bg-warning badge-custom">JS</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="test-section">
                <h4 class="mb-3">
                    <i class="fa-solid fa-vial me-2 text-danger"></i>
                    Test Accounts
                </h4>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Password</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>user@test.com</code></td>
                                <td><code>123456</code></td>
                                <td><span class="badge bg-info">Candidate</span></td>
                            </tr>
                            <tr>
                                <td><code>employer@test.com</code></td>
                                <td><code>123456</code></td>
                                <td><span class="badge bg-success">Employer</span></td>
                            </tr>
                            <tr>
                                <td><code>admin@test.com</code></td>
                                <td><code>123456</code></td>
                                <td><span class="badge bg-danger">Admin</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="alert alert-success border-0 mb-0">
                <i class="fa-solid fa-circle-check me-2"></i>
                <strong>Hướng dẫn:</strong> Click vào các link phía trên để kiểm tra. Nếu CSS/JS load đúng, bạn sẽ thấy giao diện đẹp với animations!
            </div>
        </div>
        
        <div class="text-center">
            <p class="text-white">
                <i class="fa-solid fa-code me-2"></i>
                Made with <i class="fa-solid fa-heart text-danger"></i> by JobFind Team
            </p>
        </div>
        
    </div>
</body>
</html>
