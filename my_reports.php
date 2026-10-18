<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDBConnection();
$currentUserId = getCurrentUserId();

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

// For each found report, fetch the claims
$reportsWithClaims = [];
foreach ($myReports as $rep) {
    if ($rep['report_type'] === 'found') {
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
    } else {
        $rep['claims'] = [];
    }
    $reportsWithClaims[] = $rep;
}

// Fetch claims submitted by this user on other items
$myClaimsStmt = $pdo->prepare("
    SELECT 
        c.id AS claim_id,
        c.message,
        c.status AS claim_status,
        c.created_at AS claim_date,
        c.reviewed_at,
        r.id AS report_id,
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
            <p style="color: var(--text-secondary); font-size: 14px;">Manage your reported items, review incoming claims, and track your submitted claims.</p>
        </div>
        <a href="report.php" class="btn btn-primary">➕ Report New Item</a>
    </div>

    <!-- Section 1: My Reported Items & Incoming Claims -->
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
                    <div class="filter-card" style="margin-bottom: 0;">
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

                            <!-- Status Changer -->
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <label style="font-size: 13px; color: var(--text-secondary);">Item Status:</label>
                                <select class="custom-select select-report-status" data-report-id="<?= $item['id'] ?>">
                                    <option value="open" <?= $item['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                                    <option value="claim_requested" <?= $item['status'] === 'claim_requested' ? 'selected' : '' ?>>Claim Requested</option>
                                    <option value="resolved" <?= $item['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                    <option value="closed" <?= $item['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                </select>
                            </div>
                        </div>

                        <!-- Claims Review Box (For Found Items) -->
                        <?php if ($item['report_type'] === 'found'): ?>
                            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-subtle);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                    <h4 style="font-size: 14px; color: var(--accent-cyan); text-transform: uppercase; letter-spacing: 0.5px;">
                                        Incoming Ownership Claims (<?= count($item['claims']) ?>)
                                    </h4>
                                    <?php if ($item['pending_claims'] > 1): ?>
                                        <span style="font-size: 12px; color: #f59e0b; background: rgba(245, 158, 11, 0.1); padding: 2px 8px; border-radius: 4px;">
                                            ⚡ Multiple claims: Approving one will decline all others
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div id="claimsList-<?= $item['id'] ?>">
                                    <?php if (empty($item['claims'])): ?>
                                        <p style="font-size: 13px; color: var(--text-muted); font-style: italic;">No claims received yet for this found item.</p>
                                    <?php else: ?>
                                        <?php foreach ($item['claims'] as $c): ?>
                                            <div class="claim-item-row" id="claimRow-<?= $c['claim_id'] ?>" data-claim-id="<?= $c['claim_id'] ?>">
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
                                                    "<?= nl2br(htmlspecialchars($c['message'])) ?>"
                                                </div>

                                                <div class="claim-actions">
                                                    <?php if ($c['claim_status'] === 'pending'): ?>
                                                        <button type="button" class="btn btn-success btn-sm btn-approve-claim" 
                                                            data-claim-id="<?= $c['claim_id'] ?>" 
                                                            data-report-id="<?= $item['id'] ?>">
                                                            ✔ Approve Claim
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm btn-reject-claim" 
                                                            data-claim-id="<?= $c['claim_id'] ?>" 
                                                            data-report-id="<?= $item['id'] ?>">
                                                            ✖ Reject
                                                        </button>
                                                    <?php elseif ($c['claim_status'] === 'approved'): ?>
                                                        <span style="color: var(--color-resolved); font-weight: 600; font-size: 13px;">✔ Approved Claimant</span>
                                                    <?php else: ?>
                                                        <span style="color: var(--text-muted); font-size: 13px;">Declined</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Section 2: Claims Made by Current User on Other Found Items -->
    <section>
        <h2 style="font-size: 20px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <span>🙋</span> Claims I Have Submitted (<?= count($mySubmittedClaims) ?>)
        </h2>

        <?php if (empty($mySubmittedClaims)): ?>
            <div class="empty-state" style="background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
                <div class="empty-state-icon">🔎</div>
                <h3>No claims submitted yet</h3>
                <p>Browse found items and submit a claim if you spot one of your belongings.</p>
                <a href="index.php" class="btn btn-secondary" style="margin-top: 16px;">Browse Items</a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php foreach ($mySubmittedClaims as $mc): ?>
                    <div class="filter-card" style="margin-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                            <div>
                                <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 4px;">
                                    <span class="badge-status <?= $mc['claim_status'] ?>">
                                        MY CLAIM: <?= strtoupper($mc['claim_status']) ?>
                                    </span>
                                    <span style="font-size: 12px; color: var(--text-muted);">
                                        Submitted on <?= date('M d, Y', strtotime($mc['claim_date'])) ?>
                                    </span>
                                </div>
                                <h3 style="font-size: 18px; color: var(--text-white);"><?= htmlspecialchars($mc['item_name']) ?></h3>
                                <p style="font-size: 13px; color: var(--text-muted);">
                                    Found by: <strong><?= htmlspecialchars($mc['finder_name']) ?></strong> (<?= htmlspecialchars($mc['finder_email']) ?>) &bull; Location: <?= htmlspecialchars($mc['location']) ?>
                                </p>
                            </div>
                        </div>

                        <div class="claim-message-box" style="margin-top: 12px;">
                            <strong>My ownership statement:</strong><br>
                            <?= nl2br(htmlspecialchars($mc['message'])) ?>
                        </div>

                        <?php if ($mc['claim_status'] === 'approved'): ?>
                            <div style="margin-top: 12px; padding: 12px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); border-radius: var(--radius-sm); color: #34d399; font-size: 13px;">
                                🎉 <strong>Your claim was approved!</strong> Please contact the finder at <strong><?= htmlspecialchars($mc['finder_email']) ?></strong> to arrange pickup.
                            </div>
                        <?php elseif ($mc['claim_status'] === 'rejected'): ?>
                            <div style="margin-top: 12px; padding: 10px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-sm); color: #f87171; font-size: 13px;">
                                This claim was not approved by the finder.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
