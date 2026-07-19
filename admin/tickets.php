<?php
/**
 * admin/tickets.php — Support tickets management.
 *
 * List view: paginated table of all support_tickets joined with users.
 * Filter by status (open|closed).
 * Detail view (?id=<ticket_id>): show ticket info, original message,
 * existing response, and a respond form. Submitting the form writes the
 * response column, marks the ticket as 'closed', and notifies the user.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_ADMIN);
$pageTitle = 'Support Tickets';

// ─── POST handler: respond to a ticket ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean($_POST['action'] ?? '');

    if ($action === 'respond') {
        $ticketId = clean($_POST['ticket_id'] ?? '');
        $response = trim($_POST['response'] ?? '');

        if ($ticketId === '' || $response === '') {
            flash('danger', 'Ticket ID and response are required.');
            redirect(APP_URL . '/admin/tickets.php');
        }

        $ticket = db_select_one(
            'SELECT t.id, t.user_id, t.subject, t.status
               FROM support_tickets t
              WHERE t.id = ? LIMIT 1',
            [$ticketId]
        );
        if (!$ticket) {
            flash('danger', 'Ticket not found.');
            redirect(APP_URL . '/admin/tickets.php');
        }

        db_execute(
            'UPDATE support_tickets SET response = ?, status = ? WHERE id = ?',
            [$response, 'closed', $ticketId]
        );

        notify(
            $ticket['user_id'],
            'Support ticket responded',
            'Your ticket "' . $ticket['subject'] . '" has been answered by our support team.',
            'ticket'
        );

        flash('success', 'Response sent and ticket closed.');
        redirect(APP_URL . '/admin/tickets.php?id=' . urlencode($ticketId));
    }

    if ($action === 'reopen') {
        $ticketId = clean($_POST['ticket_id'] ?? '');
        db_execute('UPDATE support_tickets SET status = ? WHERE id = ?', ['open', $ticketId]);
        flash('success', 'Ticket reopened.');
        redirect(APP_URL . '/admin/tickets.php?id=' . urlencode($ticketId));
    }

    flash('danger', 'Invalid request.');
    redirect(APP_URL . '/admin/tickets.php');
}

// ─── Detail view (?id=…) ─────────────────────────────────────────────
$detailId = $_GET['id'] ?? null;
if ($detailId) {
    $ticket = db_select_one(
        'SELECT t.*, u.name AS user_name, u.email, u.phone, u.avatar
           FROM support_tickets t
           JOIN users u ON u.id = t.user_id
          WHERE t.id = ? LIMIT 1',
        [$detailId]
    );

    if (!$ticket) {
        flash('danger', 'Ticket not found.');
        redirect(APP_URL . '/admin/tickets.php');
    }
}

include __DIR__ . '/../includes/header.php';
if ($detailId) {
    ?>
    <!-- Page header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 mb-1 fw-bold text-purple">
                <i class="fas fa-life-ring me-2"></i>Ticket #<?= e(substr($ticket['id'], -6)) ?>
            </h1>
            <p class="text-muted mb-0">
                <a href="<?= APP_URL ?>/admin/tickets.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i>Back to all tickets
                </a>
            </p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-md-0">
            <?= status_badge($ticket['status']) ?>
            <?= ticket_priority_badge($ticket['priority']) ?>
        </div>
    </div>

    <div class="row g-3">
        <!-- Left column: ticket + user info -->
        <div class="col-lg-7">
            <!-- Subject -->
            <div class="card mb-3">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Subject</div>
                    <h5 class="mb-0 fw-600"><?= e($ticket['subject']) ?></h5>
                </div>
            </div>

            <!-- User's original message -->
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-comment-dots me-2 text-purple"></i>Customer's Message</div>
                <div class="card-body">
                    <blockquote class="blockquote border-start border-3 border-purple ps-3 mb-0">
                        <p class="mb-2"><?= nl2br(e($ticket['message'])) ?></p>
                        <footer class="blockquote-footer">
                            <?= e($ticket['user_name']) ?> · <?= e(fmt_datetime($ticket['created_at'])) ?>
                        </footer>
                    </blockquote>
                </div>
            </div>

            <!-- Existing response -->
            <?php if (!empty($ticket['response'])): ?>
            <div class="card mb-3 border-success">
                <div class="card-header bg-success text-white"><i class="fas fa-reply me-2"></i>Previous Response</div>
                <div class="card-body">
                    <p class="mb-0"><?= nl2br(e($ticket['response'])) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Respond form -->
            <div class="card">
                <div class="card-header"><i class="fas fa-reply-all me-2 text-purple"></i>Respond to Ticket</div>
                <div class="card-body">
                    <form method="post" action="<?= APP_URL ?>/admin/tickets.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="respond">
                        <input type="hidden" name="ticket_id" value="<?= e($ticket['id']) ?>">
                        <div class="mb-3">
                            <label class="form-label">Your response <span class="text-danger">*</span></label>
                            <textarea name="response" class="form-control" rows="6" required
                                      placeholder="Type the response that will be sent to the customer…"><?= e($ticket['response'] ?? '') ?></textarea>
                            <small class="text-muted">Submitting this form saves the response and closes the ticket. The customer will be notified.</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-grad">
                                <i class="fas fa-paper-plane me-1"></i>Send &amp; Close
                            </button>
                            <a href="<?= APP_URL ?>/admin/tickets.php" class="btn btn-link text-muted">Cancel</a>
                        </div>
                    </form>
                    <?php if ($ticket['status'] === 'closed'): ?>
                    <form method="post" action="<?= APP_URL ?>/admin/tickets.php" class="d-inline mt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reopen">
                        <input type="hidden" name="ticket_id" value="<?= e($ticket['id']) ?>">
                        <button type="submit" class="btn btn-outline-warning">
                            <i class="fas fa-door-open me-1"></i>Reopen Ticket
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right column: customer + ticket meta -->
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-user me-2 text-purple"></i>Customer</div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="ph-avatar" style="width:48px;height:48px;font-size:1rem;background:var(--ph-purple);color:#fff;">
                            <?= e(initials($ticket['user_name'])) ?>
                        </span>
                        <div>
                            <div class="fw-600"><?= e($ticket['user_name']) ?></div>
                            <small class="text-muted">Customer</small>
                        </div>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted"><i class="fas fa-envelope me-2"></i>Email</span>
                            <span class="fw-600"><?= e($ticket['email']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted"><i class="fas fa-phone me-2"></i>Phone</span>
                            <span class="fw-600"><?= e($ticket['phone'] ?: '—') ?></span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-info-circle me-2 text-purple"></i>Ticket Info</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Status</span>
                            <?= status_badge($ticket['status']) ?>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Priority</span>
                            <?= ticket_priority_badge($ticket['priority']) ?>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Created</span>
                            <span class="fw-600"><?= e(fmt_datetime($ticket['created_at'])) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Updated</span>
                            <span class="fw-600"><?= e(fmt_datetime($ticket['updated_at'])) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Ticket ID</span>
                            <span class="fw-600 text-monospace small"><?= e($ticket['id']) ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// ─── List view ───────────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? '';
if (!in_array($statusFilter, ['open', 'closed', ''], true)) {
    $statusFilter = '';
}

$perPage = 15;
$where   = $statusFilter !== '' ? 'WHERE t.status = ?' : '';
$params  = $statusFilter !== '' ? [$statusFilter] : [];

$countSql = 'SELECT COUNT(*) FROM support_tickets t ' . $where;
$dataSql  = 'SELECT t.id, t.subject, t.status, t.priority, t.created_at,
                    u.name AS user_name, u.email
               FROM support_tickets t
               JOIN users u ON u.id = t.user_id '
            . $where
            . ' ORDER BY
                FIELD(t.status, "open", "closed"),
                FIELD(t.priority, "high", "medium", "low"),
                t.created_at DESC';

$pg = paginate($countSql, $dataSql, $params, $perPage);

/**
 * Render a priority badge for a support ticket.
 */
function ticket_priority_badge(string $priority): string
{
    $map = [
        'high'   => 'bg-danger',
        'medium' => 'bg-info text-dark',
        'low'    => 'bg-secondary',
    ];
    $cls = $map[strtolower($priority)] ?? 'bg-secondary';
    return '<span class="badge ' . $cls . '">' . e(ucfirst($priority)) . '</span>';
}
?>
<!-- Page header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 fade-in">
    <div>
        <h1 class="h3 mb-1 fw-bold text-purple">
            <i class="fas fa-life-ring me-2"></i>Support Tickets
        </h1>
        <p class="text-muted mb-0">Review and respond to customer support requests.</p>
    </div>
    <form method="get" class="d-flex align-items-center gap-2 mt-2 mt-md-0">
        <select name="status" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
            <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>All tickets</option>
            <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
            <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
        </select>
        <?php if ($statusFilter !== ''): ?>
            <a href="<?= APP_URL ?>/admin/tickets.php" class="btn btn-link btn-sm text-muted">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-inbox me-2 text-purple"></i>Tickets (<?= $pg['total'] ?>)</span>
        <span class="badge bg-purple-soft text-purple">Page <?= $pg['page'] ?> of <?= $pg['pages'] ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (!$pg['rows']): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p class="mb-0">No support tickets found.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
                <thead><tr>
                    <th>Subject</th><th>Customer</th>
                    <th>Status</th><th>Priority</th>
                    <th>Created</th><th class="text-end">Action</th>
                </tr></thead>
                <tbody>
                <?php foreach ($pg['rows'] as $t): ?>
                    <tr>
                        <td>
                            <a href="<?= APP_URL ?>/admin/tickets.php?id=<?= e($t['id']) ?>" class="fw-600 text-purple text-decoration-none">
                                <?= e($t['subject']) ?>
                            </a>
                            <div class="small text-muted">#<?= e(substr($t['id'], -8)) ?></div>
                        </td>
                        <td>
                            <div class="fw-600"><?= e($t['user_name']) ?></div>
                            <small class="text-muted"><?= e($t['email']) ?></small>
                        </td>
                        <td>
                            <?php if ($t['status'] === 'open'): ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-circle-dot me-1"></i>Open</span>
                            <?php else: ?>
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Closed</span>
                            <?php endif; ?>
                        </td>
                        <td><?= ticket_priority_badge($t['priority']) ?></td>
                        <td class="small text-muted"><?= e(time_ago($t['created_at'])) ?></td>
                        <td class="text-end">
                            <a href="<?= APP_URL ?>/admin/tickets.php?id=<?= e($t['id']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i>View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($pg['pages'] > 1): ?>
    <div class="card-footer bg-white">
        <?= pagination_links($pg, APP_URL . '/admin/tickets.php') ?>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php';
