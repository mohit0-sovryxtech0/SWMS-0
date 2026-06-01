<?php
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>
<div class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7 hero-content">
                <div class="hero-badge"><i class="fas fa-shield-alt me-2"></i> Government of Nepal | Official Portal</div>
                <h1>Welcome to <span class="highlight">Smart Water</span> Management System</h1>
                <p class="hero-text">Access your water account online — check bills, make payments, submit complaints, and track service requests from anywhere, anytime.</p>
                <div class="d-flex flex-wrap gap-3">
                    <?php if (citizenLoggedIn()): ?>
                        <a href="<?= CITIZEN_URL ?>dashboard.php" class="btn btn-light btn-lg action-btn"><i class="fas fa-tachometer-alt"></i> My Dashboard</a>
                    <?php else: ?>
                        <a href="<?= CITIZEN_URL ?>login.php" class="btn btn-light btn-lg action-btn"><i class="fas fa-sign-in-alt"></i> Login</a>
                        <a href="<?= CITIZEN_URL ?>register.php" class="btn btn-outline-light btn-lg action-btn"><i class="fas fa-user-plus"></i> Register</a>
                    <?php endif; ?>
                    <a href="<?= CITIZEN_URL ?>complaint-track.php" class="btn btn-outline-light btn-lg action-btn"><i class="fas fa-search"></i> Track Complaint</a>
                </div>
            </div>
            <div class="col-lg-5 hero-icon-grid mt-4 mt-lg-0">
                <div class="row g-3">
                    <div class="col-6">
                        <a href="<?= CITIZEN_URL ?>bills.php" class="text-decoration-none">
                            <div class="icon-card">
                                <i class="fas fa-file-invoice-dollar"></i>
                                <h6>Check Bill</h6>
                                <small>View & download bills</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?= CITIZEN_URL ?>bills.php" class="text-decoration-none">
                            <div class="icon-card">
                                <i class="fas fa-credit-card"></i>
                                <h6>Pay Online</h6>
                                <small>Secure payment</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?= CITIZEN_URL ?>complaints.php" class="text-decoration-none">
                            <div class="icon-card">
                                <i class="fas fa-exclamation-circle"></i>
                                <h6>Submit Complaint</h6>
                                <small>Register issue</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?= CITIZEN_URL ?>complaint-track.php" class="text-decoration-none">
                            <div class="icon-card">
                                <i class="fas fa-tasks"></i>
                                <h6>Track Complaint</h6>
                                <small>Check status</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<section class="features-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Portal Features</h2>
            <p class="text-muted">Everything you need to manage your water account online</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4 col-lg-3">
                <div class="feature-card">
                    <div class="icon-circle" style="background:rgba(0,56,147,0.1);color:var(--primary);">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h5>View Bills</h5>
                    <p>Check your current and past water bills with full breakdown of charges.</p>
                    <a href="<?= CITIZEN_URL ?>bills.php" class="btn btn-sm btn-outline-primary">View Bills</a>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="feature-card">
                    <div class="icon-circle" style="background:rgba(40,167,69,0.1);color:#28a745;">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h5>Pay Online</h5>
                    <p>Pay your water bills securely using eSewa, Khalti, or other gateways.</p>
                    <a href="<?= CITIZEN_URL ?>bills.php" class="btn btn-sm btn-outline-success">Pay Now</a>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="feature-card">
                    <div class="icon-circle" style="background:rgba(255,193,7,0.1);color:#e0a800;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h5>Submit Complaint</h5>
                    <p>Report water-related issues like leakage, low pressure, or quality concerns.</p>
                    <a href="<?= CITIZEN_URL ?>complaints.php" class="btn btn-sm btn-outline-warning">Submit</a>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="feature-card">
                    <div class="icon-circle" style="background:rgba(23,162,184,0.1);color:#17a2b8;">
                        <i class="fas fa-search"></i>
                    </div>
                    <h5>Track Status</h5>
                    <p>Track the progress of your complaints using your unique ticket number.</p>
                    <a href="<?= CITIZEN_URL ?>complaint-track.php" class="btn btn-sm btn-outline-info">Track</a>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="feature-card">
                    <div class="icon-circle" style="background:rgba(108,117,125,0.1);color:#6c757d;">
                        <i class="fas fa-history"></i>
                    </div>
                    <h5>Payment History</h5>
                    <p>View your complete payment history with receipts and transaction details.</p>
                    <a href="<?= CITIZEN_URL ?>payment-history.php" class="btn btn-sm btn-outline-secondary">View History</a>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="feature-card">
                    <div class="icon-circle" style="background:rgba(0,56,147,0.1);color:var(--primary);">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <h5>My Profile</h5>
                    <p>Update your contact details and manage your account information.</p>
                    <a href="<?= CITIZEN_URL ?>profile.php" class="btn btn-sm btn-outline-primary">View Profile</a>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="feature-card">
                    <div class="icon-circle" style="background:rgba(220,53,69,0.1);color:#dc3545;">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h5>Due Alerts</h5>
                    <p>Get notified about upcoming bill due dates and avoid late penalties.</p>
                    <a href="<?= CITIZEN_URL ?>dashboard.php" class="btn btn-sm btn-outline-danger">Check Dues</a>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="feature-card">
                    <div class="icon-circle" style="background:rgba(23,162,184,0.1);color:#17a2b8;">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <h5>Service Area</h5>
                    <p>View ward-wise service areas and contact information for your area.</p>
                    <a href="#" class="btn btn-sm btn-outline-info">Learn More</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5" style="background:var(--gray-100);">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 mb-4 mb-md-0">
                <h3 class="fw-bold mb-3">About <?= APP_ORG ?></h3>
                <p class="text-muted">The Drinking Water & Sanitation Consumer Committee is a government-entity under the Government of Nepal dedicated to providing clean drinking water and sanitation services to all citizens.</p>
                <p class="text-muted">Our mission is to ensure sustainable water supply, efficient billing, and responsive complaint resolution for every household and business in our service area.</p>
            </div>
            <div class="col-md-6">
                <div class="row g-3 text-center">
                    <div class="col-4">
                        <div class="bg-white rounded-3 p-3 shadow-sm">
                            <div class="fw-bold text-primary fs-4">5,000+</div>
                            <small class="text-muted">Consumers</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-white rounded-3 p-3 shadow-sm">
                            <div class="fw-bold text-success fs-4">98%</div>
                            <small class="text-muted">Satisfaction</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-white rounded-3 p-3 shadow-sm">
                            <div class="fw-bold text-info fs-4">24/7</div>
                            <small class="text-muted">Service</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include_once __DIR__ . '/includes/footer.php'; ?>
