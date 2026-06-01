<?php
require_once __DIR__ . '/../includes/config.php';

$pageTitle = 'Track Complaint';
$complaint = null;
$updates = [];
$error = '';
$searched = false;

if (isPost()) {
    $ticketNo = trim(post('ticket_no'));
    $csrf = post('csrf_token');

    if (!verify_csrf($csrf)) {
        $error = 'Invalid security token.';
    } elseif (empty($ticketNo)) {
        $error = 'Please enter a ticket number.';
    } else {
        $searched = true;
        $db = db();

        $complaint = $db->fetchOne(
            "SELECT c.*, cc.name as category_name
             FROM complaints c
             LEFT JOIN complaint_categories cc ON c.category_id = cc.id
             WHERE c.ticket_no = ? AND c.deleted_at IS NULL AND c.is_public = 1
             LIMIT 1",
            [$ticketNo]
        );

        if (!$complaint) {
            $error = 'No complaint found with ticket number: ' . escape($ticketNo);
        } else {
            $updates = $db->fetchAll(
                "SELECT cu.*, u.name as updated_by_name
                 FROM complaint_updates cu
                 LEFT JOIN users u ON cu.user_id = u.id
                 WHERE cu.complaint_id = ? AND cu.is_public = 1
                 ORDER BY cu.created_at ASC",
                [$complaint['id']]
            );

            // Mark complaint as read
            $db->update('complaints', ['is_read' => 0], 'id = :id', ['id' => $complaint['id']]);
        }
    }
} elseif (isset($_GET['ticket'])) {
    // Support direct ticket lookup from URL
    $ticketNo = trim($_GET['ticket']);
    $searched = true;
    $db = db();

    $complaint = $db->fetchOne(
        "SELECT c.*, cc.name as category_name
         FROM complaints c
         LEFT JOIN complaint_categories cc ON c.category_id = cc.id
         WHERE c.ticket_no = ? AND c.deleted_at IS NULL AND c.is_public = 1
         LIMIT 1",
        [$ticketNo]
    );

    if ($complaint) {
        $updates = $db->fetchAll(
            "SELECT cu.*, u.name as updated_by_name
             FROM complaint_updates cu
             LEFT JOIN users u ON cu.user_id = u.id
             WHERE cu.complaint_id = ? AND cu.is_public = 1
             ORDER BY cu.created_at ASC",
            [$complaint['id']]
        );
    } else {
        $error = 'No complaint found with ticket number: ' . escape($ticketNo);
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?> - <?= APP_SHORT ?> | Citizen Portal</title>
    <link rel="icon" type="image/png" href="<?= ADMIN_URL ?>assets/images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= CITIZEN_URL ?>assets/css/citizen.css" rel="stylesheet">
</head>
<body>
    <!-- Minimal Navbar for public page -->
    <nav class="navbar navbar-expand-lg citizen-navbar">
        <div class="container">
            <a class="navbar-brand" href="<?= CITIZEN_URL ?>">
                <span class="brand-icon"><i class="fas fa-water"></i></span>
                <span class="brand-text"><?= APP_SHORT ?></span>
                <small class="d-none d-md-inline">Citizen Portal</small>
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="<?= CITIZEN_URL ?>"><i class="fas fa-home"></i> Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= CITIZEN_URL ?>login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                    <li class="nav-item"><a class="nav-link active" href="<?= CITIZEN_URL ?>complaint-track.php"><i class="fas fa-search"></i> Track Complaint</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <?= display_alert() ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-5">
                    <h2 class="fw-bold"><i class="fas fa-search text-primary me-2"></i>Track Your Complaint</h2>
                    <p class="text-muted">Enter your complaint ticket number to check its current status</p>
                </div>

                <!-- Search Form -->
                <div class="card mb-4">
                    <div class="card-body p-4">
                        <form method="POST" action="">
                            <?= csrf_field() ?>
                            <div class="row g-3">
                                <div class="col-md-9">
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text"><i class="fas fa-ticket-alt"></i></span>
                                        <input type="text" name="ticket_no" class="form-control" placeholder="Enter Ticket Number (e.g. TKT-20260529-XXXXXX)" value="<?= escape(post('ticket_no') ?? $_GET['ticket'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary btn-lg w-100"><i class="fas fa-search me-1"></i> Track</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <?php if ($complaint): ?>
                    <div class="track-result">
                        <div class="track-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h4 class="mb-1"><?= escape($complaint['ticket_no']) ?></h4>
                                    <p class="mb-0 opacity-75"><?= escape($complaint['subject']) ?></p>
                                </div>
                                <div class="col-auto text-end">
                                    <div class="mb-1"><?= get_status_badge($complaint['status']) ?></div>
                                    <small class="opacity-75"><?= ucfirst($complaint['priority']) ?> Priority</small>
                                </div>
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="row g-3 mb-4">
                                <div class="col-sm-4">
                                    <small class="text-muted d-block">Category</small>
                                    <strong><?= escape($complaint['category_name'] ?? '-') ?></strong>
                                </div>
                                <div class="col-sm-4">
                                    <small class="text-muted d-block">Submitted On</small>
                                    <strong><?= format_datetime($complaint['created_at']) ?></strong>
                                </div>
                                <div class="col-sm-4">
                                    <small class="text-muted d-block">Last Updated</small>
                                    <strong><?= $complaint['updated_at'] ? format_datetime($complaint['updated_at']) : '-' ?></strong>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h6 class="fw-bold border-bottom pb-2">Description</h6>
                                <p class="mb-0"><?= nl2br(escape($complaint['description'])) ?></p>
                            </div>

                            <?php if (!empty($updates)): ?>
                            <h6 class="fw-bold border-bottom pb-2 mb-3">Status Updates</h6>
                            <div class="timeline">
                                <?php foreach ($updates as $update): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-date">
                                        <i class="far fa-clock me-1"></i> <?= format_datetime($update['created_at']) ?>
                                        <?php if ($update['updated_by_name']): ?>
                                            <span class="ms-2">by <?= escape($update['updated_by_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-text">
                                        <?php if ($update['status']): ?>
                                            <span class="me-2"><?= get_status_badge($update['status']) ?></span>
                                        <?php endif; ?>
                                        <?= nl2br(escape($update['message'])) ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($searched && !$error): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Use the form above to track your complaint by ticket number.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="citizen-footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="footer-brand">
                        <i class="fas fa-water footer-brand-icon"></i>
                        <h5><?= APP_NAME ?></h5>
                    </div>
                    <p class="footer-desc"><?= APP_ORG ?>, Government of Nepal. Providing quality drinking water and sanitation services to the community.</p>
                </div>
                <div class="col-md-3">
                    <h5 class="footer-heading">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="<?= CITIZEN_URL ?>"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="<?= CITIZEN_URL ?>login.php"><i class="fas fa-chevron-right"></i> Citizen Login</a></li>
                        <li><a href="<?= CITIZEN_URL ?>register.php"><i class="fas fa-chevron-right"></i> Register</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5 class="footer-heading">Contact</h5>
                    <ul class="footer-contact">
                        <li><i class="fas fa-map-marker-alt"></i> Kathmandu, Nepal</li>
                        <li><i class="fas fa-phone"></i> +977-1-4XXXXXX</li>
                        <li><i class="fas fa-envelope"></i> info@swms.gov.np</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p class="mb-0">&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved. | Government of Nepal <i class="fas fa-flag ms-1"></i></p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= CITIZEN_URL ?>assets/js/citizen.js"></script>
</body>
</html>
