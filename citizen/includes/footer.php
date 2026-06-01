    <!-- Footer -->
    <footer class="citizen-footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="footer-brand">
                        <i class="fas fa-water footer-brand-icon"></i>
                        <h5><?= APP_NAME ?></h5>
                    </div>
                    <p class="footer-desc"><?= APP_ORG ?>, Government of Nepal. Providing quality drinking water and sanitation services to the community.</p>
                </div>
                <div class="col-md-4">
                    <h5 class="footer-heading">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="<?= CITIZEN_URL ?>"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="<?= CITIZEN_URL ?>bills.php"><i class="fas fa-chevron-right"></i> Check Bill</a></li>
                        <li><a href="<?= CITIZEN_URL ?>complaint-track.php"><i class="fas fa-chevron-right"></i> Track Complaint</a></li>
                        <li><a href="<?= BASE_URL ?>"><i class="fas fa-chevron-right"></i> Main Portal</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="footer-heading">Contact</h5>
                    <ul class="footer-contact">
                        <li><i class="fas fa-map-marker-alt"></i> Kathmandu, Nepal</li>
                        <li><i class="fas fa-phone"></i> +977-1-4XXXXXX</li>
                        <li><i class="fas fa-envelope"></i> info@swms.gov.np</li>
                        <li><i class="fas fa-clock"></i> Sun-Thu: 10:00 AM - 5:00 PM</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <p class="mb-0">&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="gov-text">Government of Nepal</span>
                        <img src="<?= CITIZEN_URL ?>assets/images/nepal-flag.png" alt="Nepal Flag" class="nepal-flag" onerror="this.style.display='none'">
                        <span class="flags-placeholder"><i class="fas fa-flag"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= CITIZEN_URL ?>assets/js/citizen.js"></script>
    <?= $extraJs ?? '' ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });
    });
    </script>
</body>
</html>
