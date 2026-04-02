            </div><!-- /.page-content -->
        </main><!-- /.main-content -->
    </div><!-- /.app-container -->

    <!-- Overlay para mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Modal Container -->
    <div class="modal-backdrop" id="modalBackdrop"></div>
    <div class="modal" id="modal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Modal</h3>
                <button class="modal-close" id="modalClose">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-footer" id="modalFooter"></div>
        </div>
    </div>

    <!-- Modal de Confirmação -->
    <div class="modal-backdrop" id="confirmBackdrop"></div>
    <div class="modal confirm-modal" id="confirmModal">
        <div class="modal-dialog">
            <div class="confirm-modal-content">
                <div class="confirm-icon" id="confirmIcon">
                    <i data-lucide="alert-triangle"></i>
                </div>
                <h3 class="confirm-title" id="confirmTitle">Confirmar ação</h3>
                <p class="confirm-message" id="confirmMessage">Tem certeza que deseja realizar esta ação?</p>
                <div class="confirm-actions">
                    <button type="button" class="btn btn-secondary" id="confirmCancelBtn">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmOkBtn">
                        <i data-lucide="trash-2"></i> Excluir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Scripts -->
    <script src="<?= url('/assets/js/app.js') ?>"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // CSRF Token para requisições AJAX
        window.csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';

        // Auto-hide flash messages
        const flashAlert = document.getElementById('flashAlert');
        if (flashAlert) {
            setTimeout(() => {
                flashAlert.style.opacity = '0';
                setTimeout(() => flashAlert.remove(), 300);
            }, 5000);
        }
        
    </script>
    <?php if (isset($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
            <script src="<?= url($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
