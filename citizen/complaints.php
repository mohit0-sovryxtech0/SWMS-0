<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Complaints';
if (!isset($_SESSION['citizen_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect(CITIZEN_URL . 'login.php');
}
require_once __DIR__ . '/includes/header.php';

$consumerId = citizenId();
$db = db();

// Fetch complaint categories
$categories = $db->fetchAll("SELECT id, name, description, sla_hours FROM complaint_categories WHERE deleted_at IS NULL ORDER BY name");

// Handle new complaint submission
if (isPost() && isset($_POST['submit_complaint'])) {
    $categoryId = (int)post('category_id');
    $subject = post('subject');
    $description = post('description');
    $priority = post('priority', 'medium');

    if (!verify_csrf(post('csrf_token'))) {
        alert_error('Invalid security token.');
    } elseif (empty($categoryId)) {
        alert_error('Please select a complaint category.');
    } elseif (empty($subject)) {
        alert_error('Please enter a subject.');
    } elseif (empty($description)) {
        alert_error('Please describe your complaint.');
    } else {
        $ticketNo = generate_ticket_no();
        $attachment = null;

        // Handle file upload
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = upload_file($_FILES['attachment'], UPLOADS_PATH . 'complaints/', explode(',', ALLOWED_EXTENSIONS));
            if ($uploadResult) {
                $attachment = 'complaints/' . $uploadResult;
            }
        }

        $db->insert('complaints', [
            'ticket_no' => $ticketNo,
            'consumer_id' => $consumerId,
            'category_id' => $categoryId,
            'priority' => $priority,
            'subject' => $subject,
            'description' => $description,
            'attachment' => $attachment,
            'status' => 'open',
            'is_public' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        alert_success('Complaint submitted successfully! Your ticket number is: <strong>' . $ticketNo . '</strong>');
        redirect(CITIZEN_URL . 'complaints.php');
    }
}

// Fetch consumer complaints
$complaints = $db->fetchAll(
    "SELECT c.id, c.ticket_no, c.subject, c.priority, c.status, c.created_at, c.updated_at,
            cc.name as category_name
     FROM complaints c
     LEFT JOIN complaint_categories cc ON c.category_id = cc.id
     WHERE c.consumer_id = ? AND c.deleted_at IS NULL
     ORDER BY c.created_at DESC",
    [$consumerId]
);
?>
<div class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col">
                <h2><i class="fas fa-exclamation-triangle me-2"></i> Complaints</h2>
                <p>Submit and track your complaints</p>
            </div>
        </div>
    </div>
</div>
<div class="container pb-5">
    <div class="row g-4">
        <!-- Complaint Form -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><i class="fas fa-plus-circle me-2 text-primary"></i> Submit New Complaint</div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= escape($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control" required placeholder="Brief title of your issue" maxlength="300">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" required placeholder="Describe your complaint in detail"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Attachment (optional)</label>
                            <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                            <small class="text-muted">Allowed: JPG, PNG, PDF, DOC. Max 5MB.</small>
                        </div>
                        <button type="submit" name="submit_complaint" class="btn btn-primary w-100">
                            <i class="fas fa-paper-plane me-1"></i> Submit Complaint
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Complaint History -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><i class="fas fa-list me-2 text-primary"></i> My Complaints</div>
                <div class="card-body p-0">
                    <?php if (empty($complaints)): ?>
                        <p class="text-muted text-center py-4 mb-0"><i class="fas fa-inbox me-2"></i> No complaints submitted yet.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-citizen mb-0">
                            <thead>
                                <tr>
                                    <th>Ticket No</th>
                                    <th>Subject</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($complaints as $c): ?>
                                <tr>
                                    <td class="fw-semibold"><a href="complaint-track.php?ticket=<?= urlencode($c['ticket_no']) ?>" class="text-primary"><?= escape($c['ticket_no']) ?></a></td>
                                    <td><?= escape(truncate($c['subject'], 50)) ?></td>
                                    <td><small><?= escape($c['category_name'] ?? '-') ?></small></td>
                                    <td>
                                        <?php
                                        $p = $c['priority'];
                                        $pClass = ['low' => 'bg-secondary', 'medium' => 'bg-warning text-dark', 'high' => 'bg-danger', 'urgent' => 'bg-danger'];
                                        ?>
                                        <span class="badge <?= $pClass[$p] ?? 'bg-secondary' ?>"><?= ucfirst($p) ?></span>
                                    </td>
                                    <td><?= get_status_badge($c['status']) ?></td>
                                    <td><small><?= format_date($c['created_at'], 'd M Y') ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include_once __DIR__ . '/includes/footer.php'; ?>
