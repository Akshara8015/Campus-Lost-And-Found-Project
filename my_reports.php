<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDBConnection();
$currentUserId = getCurrentUserId();

// Direct server-side deletion fallback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_report') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    if ($reportId > 0) {
        $stmt = $pdo->prepare("SELECT id, user_id, image_path FROM reports WHERE id = :id");
        $stmt->execute(['id' => $reportId]);
        $rep = $stmt->fetch();
        if ($rep && ((int)$rep['user_id'] === $currentUserId || isAdmin())) {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM claims WHERE report_id = :id")->execute(['id' => $reportId]);
            $pdo->prepare("DELETE FROM reports WHERE id = :id")->execute(['id' => $reportId]);
            $pdo->commit();
            if (!empty($rep['image_path'])) {
                $imgPath = __DIR__ . '/' . ltrim($rep['image_path'], '/\\');
                if (file_exists($imgPath)) { @unlink($imgPath); }
            }
            header('Location: my_reports.php?msg=report_deleted');
            exit;
        }
    }
}

// Fetch reports created by this user
$stmt = $pdo->prepare("
    SELECT 
        r.*,
        (SELECT COUNT(*) FROM claims c WHERE c.report_id = r.id) AS total_claims,
        (SELECT COUNT(*) FROM claims c WHERE c.report_id = r.id AND c.status = 'pending') AS pending_claims
    FROM reports r
    WHERE r.user_id = :user_id
    ORDER BY r.created_at DESC
");
$stmt->execute(['user_id' => $currentUserId]);
$myReports = $stmt->fetchAll();

// For each report (both found items and lost items), fetch incoming claims / recovery leads
$reportsWithClaims = [];
foreach ($myReports as $rep) {
    $claimStmt = $pdo->prepare("
        SELECT 
            c.id AS claim_id,
            c.message,
            c.status AS claim_status,
            c.created_at AS claim_date,
            c.reviewed_at,
            u.full_name AS claimant_name,
            u.email AS claimant_email
        FROM claims c
        JOIN users u ON c.claimant_id = u.id
        WHERE c.report_id = :report_id
        ORDER BY c.created_at ASC
    ");
    $claimStmt->execute(['report_id' => $rep['id']]);
    $rep['claims'] = $claimStmt->fetchAll();
    $reportsWithClaims[] = $rep;
}

// Fetch claims and recovery responses submitted by this user on other items
$myClaimsStmt = $pdo->prepare("
    SELECT 
        c.id AS claim_id,
        c.message,
        c.status AS claim_status,
        c.created_at AS claim_date,
        c.reviewed_at,
        r.id AS report_id,
        r.report_type,
        r.item_name,
        r.category,
        r.location,
        r.item_date,
        r.status AS report_status,
        u.full_name AS finder_name,
        u.email AS finder_email
    FROM claims c
    JOIN reports r ON c.report_id = r.id
    JOIN users u ON r.user_id = u.id
    WHERE c.claimant_id = :claimant_id
    ORDER BY c.created_at DESC
");
$myClaimsStmt->execute(['claimant_id' => $currentUserId]);
$mySubmittedClaims = $myClaimsStmt->fetchAll();

$pageTitle = 'My Dashboard & Claims';
require_once __DIR__ . '/includes/header.php';
?>

<main class="main-content" id="myDashboardContainer">
    <div style="margin-bottom: 28px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 30px;">My Activity Dashboard</h1>
            <p style="color: var(--text-secondary); font-size: 14px;">Manage your reported items, review incoming claims & leads, and resolve items.</p>
        </div>
        <a href="report.php" class="btn btn-primary">➕ Report New Item</a>
    </div>

    <!-- Testing & Workflow Guide Banner -->
    <div style="background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: var(--radius-md); padding: 14px 18px; margin-bottom: 24px; font-size: 13px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <span style="font-size: 16px; margin-right: 6px;">💡</span>
            <strong>Two-Way Claim & Resolution Workflow:</strong>
            <ul style="margin: 6px 0 0 18px; padding: 0; line-height: 1.6; color: var(--text-secondary);">
                <li><strong>If you reported a Lost item:</strong> Other users can click <em>"🙋 I Found This!"</em> on the browse page to notify you. Review their responses below and click <strong>✔ Confirm Recovered</strong> to resolve your lost item.</li>
                <li><strong>If you reported a Found item:</strong> Other users submit ownership proof. Review their descriptions below and click <strong>✔ Approve Claim</strong> to verify the owner and resolve the item.</li>
            </ul>
        </div>
    </div>

    <!-- Section 1: My Reported Items & Incoming Claims / Leads -->
    <section style="margin-bottom: 48px;">
        <h2 style="font-size: 20px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <span>📦</span> My Reported Items (<?= count($reportsWithClaims) ?>)
        </h2>

        <?php if (empty($reportsWithClaims)): ?>
            <div class="empty-state" style="background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
                <div class="empty-state-icon">📋</div>
                <h3>No reported items yet</h3>
                <p>When you report a lost or found item, it will appear here.</p>
                <a href="report.php" class="btn btn-primary" style="margin-top: 16px;">Report an Item</a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <?php foreach ($reportsWithClaims as $item): ?>
                    <div class="filter-card" id="reportCardRow-<?= $item['id'] ?>" style="margin-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                            <div style="display: flex; gap: 16px; align-items: center;">
                                <?php if (!empty($item['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="" style="width: 64px; height: 64px; border-radius: var(--radius-md); object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 64px; height: 64px; border-radius: var(--radius-md); background: #0f172a; display: flex; align-items: center; justify-content: center; font-size: 28px;">
                                        <?= $item['report_type'] === 'lost' ? '🔎' : '🎁' ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 4px;">
                                        <span class="badge-type <?= $item['report_type'] ?>"><?= strtoupper($item['report_type']) ?></span>
                                        <span class="badge-status <?= $item['status'] ?>" id="reportStatusBadge-<?= $item['id'] ?>">
                                            <?= strtoupper(str_replace('_', ' ', $item['status'])) ?>
                                        </span>
                                    </div>
                                    <h3 style="font-size: 18px; color: var(--text-white);"><?= htmlspecialchars($item['item_name']) ?></h3>
                                    <p style="font-size: 13px; color: var(--text-muted);">
                                        📍 <?= htmlspecialchars($item['location']) ?> &bull; 📅 <?= date('M d, Y', strtotime($item['item_date'])) ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Actions & Status Changer -->
                            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end;">
                                    <label style="font-size: 13px; color: var(--text-secondary);">Status:</label>
                                    <select class="custom-select select-report-status" data-report-id="<?= $item['id'] ?>">
                                        <option value="open" <?= $item['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                                        <option value="claim_requested" <?= $item['status'] === 'claim_requested' ? 'selected' : '' ?>>Claim / Lead Requested</option>
                                        <option value="resolved" <?= $item['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                        <option value="closed" <?= $item['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                    </select>
                                    <form method="POST" action="my_reports.php" style="display: inline; margin: 0;" onsubmit="return confirm('Are you sure you want to delete <?= htmlspecialchars(addslashes($item['item_name'])) ?>?');">
                                        <input type="hidden" name="action" value="delete_report">
                                        <input type="hidden" name="report_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm btn-delete-report" 
                                            data-report-id="<?= $item['id'] ?>" 
                                            data-name="<?= htmlspecialchars($item['item_name']) ?>"
                                            title="Delete this report permanently">
                                            🗑 Delete
                                        </button>
                                    </form>
                                </div>
                                <span style="font-size: 11px; color: var(--text-muted);">Change to Resolved when recovered, or Delete if reported by mistake</span>
                            </div>
                        </div>

                        <!-- Claims & Recovery Leads Review Box -->
                        <?php 
                        $isLostItem = ($item['report_type'] === 'lost');
                        $boxTitle = $isLostItem 
                            ? 'Incoming "I Found This!" Responses (' . count($item['claims']) . ')' 
                            : 'Incoming Ownership Claims (' . count($item['claims']) . ')';
                        $boxEmptyMsg = $isLostItem
                            ? 'ℹ️ No recovery leads received yet. When another user finds your lost item and reports it, their details will appear here for you to verify and resolve.'
                            : 'ℹ️ No claims submitted yet for this found item. When other students browse this item and claim it, their proof will appear here for you to verify.';
                        $messageLabel = $isLostItem
                            ? "Finder's Location & Recovery Details:"
                            : "Claimant's Proof of Ownership:";
                        $approveBtnText = $isLostItem
                            ? '✔ Confirm Recovered (Resolve Lost Item)'
                            : '✔ Approve Claim (Verify Owner)';
                        $rejectBtnText = $isLostItem
                            ? '✖ Not My Item / Reject'
                            : '✖ Reject';
                        ?>
                        <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-subtle);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                                <h4 style="font-size: 14px; color: var(--accent-cyan); text-transform: uppercase; letter-spacing: 0.5px;">
                                    <?= $boxTitle ?>
                                </h4>
                                <?php if ($item['pending_claims'] > 1): ?>
                                    <span style="font-size: 12px; color: #f59e0b; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.3); padding: 4px 10px; border-radius: 6px;">
                                        ⚡ <?= $item['pending_claims'] ?> Pending: Approving one will verify it, mark this report Resolved, and decline competing responses
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div id="claimsList-<?= $item['id'] ?>">
                                <?php if (empty($item['claims'])): ?>
                                    <div style="padding: 12px; background: rgba(15, 23, 42, 0.4); border-radius: var(--radius-md); font-size: 13px; color: var(--text-muted);">
                                        <?= $boxEmptyMsg ?>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($item['claims'] as $c): ?>
                                        <div class="claim-item-row" id="claimRow-<?= $c['claim_id'] ?>" data-claim-id="<?= $c['claim_id'] ?>" style="<?= $c['claim_status'] === 'approved' ? 'border-color: var(--color-resolved);' : '' ?>">
                                            <div class="claim-item-header">
                                                <div>
                                                    <strong style="color: var(--text-white); font-size: 14px;"><?= htmlspecialchars($c['claimant_name']) ?></strong>
                                                    <span style="color: var(--text-muted); font-size: 12px;">(<?= htmlspecialchars($c['claimant_email']) ?>)</span>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span class="badge-status <?= $c['claim_status'] ?> claim-status-badge">
                                                        <?= strtoupper($c['claim_status']) ?>
                                                    </span>
                                                    <span style="font-size: 12px; color: var(--text-muted);">
                                                        <?= date('M d, Y h:i A', strtotime($c['claim_date'])) ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="claim-message-box">
                                                <strong style="display: block; font-size: 11px; text-transform: uppercase; color: var(--accent-cyan); margin-bottom: 4px;"><?= $messageLabel ?></strong>
                                                "<?= nl2br(htmlspecialchars($c['message'])) ?>"
                                            </div>

                                            <div class="claim-actions">
                                                <?php if ($c['claim_status'] === 'pending'): ?>
                                                    <button type="button" class="btn btn-success btn-sm btn-approve-claim" 
                                                        data-claim-id="<?= $c['claim_id'] ?>" 
                                                        data-report-id="<?= $item['id'] ?>"
                                                        data-report-type="<?= $item['report_type'] ?>"
                                                        data-claimant="<?= htmlspecialchars($c['claimant_name']) ?>">
                                                        <?= $approveBtnText ?>
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm btn-reject-claim" 
                                                        data-claim-id="<?= $c['claim_id'] ?>" 
                                                        data-report-id="<?= $item['id'] ?>"
                                                        data-report-type="<?= $item['report_type'] ?>"
                                                        data-claimant="<?= htmlspecialchars($c['claimant_name']) ?>">
                                                        <?= $rejectBtnText ?>
                                                    </button>
                                                <?php elseif ($c['claim_status'] === 'approved'): ?>
                                                    <span style="color: var(--color-resolved); font-weight: 600; font-size: 13px;">
                                                        <?= $isLostItem ? '✔ Confirmed Recovered &bull; Handover in Progress' : '✔ Verified Owner &bull; Handover in Progress' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted); font-size: 13px;">Declined</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Section 2: Claims & Responses Submitted by Current User -->
    <section>
        <h2 style="font-size: 20px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <span>🙋</span> Claims & Responses I Have Submitted (<?= count($mySubmittedClaims) ?>)
        </h2>

        <?php if (empty($mySubmittedClaims)): ?>
            <div class="empty-state" style="background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
                <div class="empty-state-icon">🔎</div>
                <h3>No claims or recovery responses yet</h3>
                <p>Browse items to submit a claim for your lost property or help someone recover their lost items.</p>
                <a href="index.php" class="btn btn-secondary" style="margin-top: 16px;">Browse Items</a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php foreach ($mySubmittedClaims as $mc): ?>
                    <div class="filter-card" style="margin-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                            <div>
                                <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 4px;">
                                    <span class="badge-type <?= $mc['report_type'] ?? 'found' ?>">
                                        <?= ($mc['report_type'] ?? '') === 'lost' ? 'REPORTED FOUND' : 'CLAIMED AS OWNER' ?>
                                    </span>
                                    <span class="badge-status <?= $mc['claim_status'] ?>">
                                        STATUS: <?= strtoupper($mc['claim_status']) ?>
                                    </span>
                                    <span style="font-size: 12px; color: var(--text-muted);">
                                        Submitted on <?= date('M d, Y', strtotime($mc['claim_date'])) ?>
                                    </span>
                                </div>
                                <h3 style="font-size: 18px; color: var(--text-white);"><?= htmlspecialchars($mc['item_name']) ?></h3>
                                <p style="font-size: 13px; color: var(--text-muted);">
                                    <?= ($mc['report_type'] ?? '') === 'lost' ? 'Reported lost by' : 'Found by' ?>: <strong><?= htmlspecialchars($mc['finder_name']) ?></strong> (<?= htmlspecialchars($mc['finder_email']) ?>) &bull; Location: <?= htmlspecialchars($mc['location']) ?>
                                </p>
                            </div>
                        </div>

                        <div class="claim-message-box" style="margin-top: 12px;">
                            <strong><?= ($mc['report_type'] ?? '') === 'lost' ? 'My recovery note / location where found:' : 'My ownership statement:' ?></strong><br>
                            <?= nl2br(htmlspecialchars($mc['message'])) ?>
                        </div>

                        <?php if ($mc['claim_status'] === 'approved'): ?>
                            <div style="margin-top: 12px; padding: 12px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); border-radius: var(--radius-sm); color: #34d399; font-size: 13px;">
                                <?= ($mc['report_type'] ?? '') === 'lost'
                                    ? '🎉 <strong>The owner confirmed your recovery report!</strong> Please contact them at <strong>' . htmlspecialchars($mc['finder_email']) . '</strong> to coordinate return.'
                                    : '🎉 <strong>Your claim was approved!</strong> Please contact the finder at <strong>' . htmlspecialchars($mc['finder_email']) . '</strong> to arrange pickup.' ?>
                            </div>
                        <?php elseif ($mc['claim_status'] === 'rejected'): ?>
                            <div style="margin-top: 12px; padding: 10px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-sm); color: #f87171; font-size: 13px;">
                                <?= ($mc['report_type'] ?? '') === 'lost'
                                    ? 'The owner indicated this lead did not match their item or has already resolved it.'
                                    : 'This claim was not approved by the finder.' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

