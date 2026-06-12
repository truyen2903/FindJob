<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trang quản trị - JobFinder</title>

  <!-- Vendor CSS Files -->
  <link href="<?= ASSETS_URL ?>/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= ASSETS_URL ?>/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= ASSETS_URL ?>/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="<?= ASSETS_URL ?>/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="<?= ASSETS_URL ?>/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="<?= ASSETS_URL ?>/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="<?= ASSETS_URL ?>/css/style.css" rel="stylesheet">
</head>

<body>

  <?php include dirname(__DIR__) . '/app/views/admin/includes/header.php'; ?>
  <?php include dirname(__DIR__) . '/app/views/admin/includes/sidebar.php'; ?>

  <main id="main" class="main">
    <?php echo  $content; ?>
  </main>

  <?php include dirname(__DIR__) . '/app/views/admin/includes/footer.php'; ?>

  <!-- Vendor JS Files -->
  <script src="<?= ASSETS_URL ?>/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="<?= ASSETS_URL ?>/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="<?= ASSETS_URL ?>/vendor/quill/quill.min.js"></script>
  <script src="<?= ASSETS_URL ?>/vendor/tinymce/tinymce.min.js"></script>

  <script src="<?= ASSETS_URL ?>/js/form-validation.js" defer></script>

  <!-- Template Main JS File -->
  <script src="<?= ASSETS_URL ?>/js/main.js"></script>

</body>
</html>
