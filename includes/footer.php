<?php
/**
 * footer.php — Bottom HTML shell + scripts.
 */
$flashes = function_exists('flash_get_all') ? flash_get_all() : [];
?>
    </div><!-- /#page-content-wrapper -->
</div><!-- /#wrapper -->

<!-- Flash toast container -->
<?php if (!empty($flashes)): ?>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1090;">
    <?php foreach ($flashes as $f):
        $type = $f['type'];
        $bg = match ($type) {
            'success' => 'text-bg-success',
            'danger', 'error' => 'text-bg-danger',
            'warning' => 'text-bg-warning',
            'info'    => 'text-bg-info',
            default   => 'text-bg-primary',
        };
    ?>
    <div class="toast align-items-center <?= $bg ?> border-0 show" role="alert">
        <div class="d-flex">
            <div class="toast-body"><?= e($f['message']) ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- App config -->
<script>window.APP_URL = '<?= e(APP_URL) ?>';</script>
<!-- App JS -->
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
    // Initialise flash toasts
    document.querySelectorAll('.toast').forEach(t => new bootstrap.Toast(t, { delay: 4000 }).show());
    // Sidebar toggle (mobile)
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('show'));
    }
</script>
</body>
</html>
