    </div><!-- /.ea-content -->
    <footer class="py-4 text-center small text-muted">
      © <?= date('Y') ?> JobFind Employer Hub. Mọi quyền được bảo lưu.
    </footer>
  </div><!-- /.ea-main -->
</div><!-- /.ea-layout -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= ASSETS_URL ?>/js/form-validation.js" defer></script>
<?php if (!empty($additionalScripts)): ?>
  <?php foreach ($additionalScripts as $scriptTag): ?>
    <?= $scriptTag . "\n" ?>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
