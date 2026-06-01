    </div><!-- .page-content -->
    </div><!-- .main-content -->

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Chart.js (already loaded in header) -->

    <!-- JS Constants -->
    <script>
    const API_URL = '<?= API_URL ?>';
    const ADMIN_URL = '<?= ADMIN_URL ?>';
    const CSRF_TOKEN = '<?= csrf_token() ?>';
    </script>

    <!-- Admin JS -->
    <script src="<?= ADMIN_URL ?>assets/js/admin.js"></script>
    
    <!-- Page Specific JS -->
    <?= $extraJs ?? '' ?>

    <script>
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });
    });
    </script>
</body>
</html>
