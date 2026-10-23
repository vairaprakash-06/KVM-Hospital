<?php
/**
 * KVM Hospital Management System — NexusCare
 * Shared Layout: Footer & Scripts
 */

declare(strict_types=1);
?>
        </div><!-- /.content-body -->

        <!-- Footer -->
        <footer class="py-3 px-4 bg-white border-top text-muted d-flex flex-wrap justify-content-between align-items-center" style="font-size: 0.8rem;">
            <div>
                <strong><?= APP_NAME ?></strong> — <?= APP_TAGLINE ?> (v<?= APP_VERSION ?>)
            </div>
            <div>
                <span class="badge bg-light text-dark border me-2">DBMS College Capstone</span>
                <span>Relational Schema: <code>kvm_hospital</code></span>
            </div>
        </footer>
    </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<!-- Toast Notification Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 1090;"></div>

<!-- Bootstrap 5.3.2 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js 4.4.0 -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- NexusCare Core JS -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
