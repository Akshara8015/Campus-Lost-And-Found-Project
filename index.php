<?php
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<main class="main-content">
    <!-- Hero Banner -->
    <section class="hero-section">
        <h1 class="hero-title">Campus <span>Lost & Found</span> Portal</h1>
        <p class="hero-subtitle">
            Report misplaced belongings, browse items found on campus, and securely submit ownership claims.
        </p>
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
            <a href="report.php" class="btn btn-primary">➕ Report Lost or Found Item</a>
            <?php if (isLoggedIn()): ?>
                <a href="my_reports.php" class="btn btn-secondary">📋 My Dashboard & Claims</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-secondary">🔑 Sign in to Submit Claims</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Interactive Search and Multi-Criteria Filter Bar -->
    <section class="filter-card">
        <div class="search-input-wrap">
            <span class="search-icon">🔍</span>
            <input type="text" id="searchInput" class="search-input" placeholder="Search by item name, location (e.g. Library, Canteen), or description...">
        </div>

        <div class="filters-row">
            <div class="type-pills">
                <button type="button" class="pill-btn active" data-type="all">All Items</button>
                <button type="button" class="pill-btn" data-type="lost">Lost Items</button>
                <button type="button" class="pill-btn" data-type="found">Found Items</button>
            </div>

            <div class="filter-group">
                <select id="categoryFilter" class="custom-select">
                    <option value="all">All Categories</option>
                    <option value="Electronics">Electronics</option>
                    <option value="ID Card">ID Cards & Documents</option>
                    <option value="Bottle">Water Bottles</option>
                    <option value="Bags">Bags & Backpacks</option>
                    <option value="Keys">Keys & Wallets</option>
                    <option value="Books">Books & Stationery</option>
                    <option value="Others">Others</option>
                </select>

                <select id="statusFilter" class="custom-select">
                    <option value="all">All Statuses</option>
                    <option value="open">Open</option>
                    <option value="claim_requested">Claim Requested</option>
                    <option value="resolved">Resolved</option>
                </select>
            </div>
        </div>
    </section>

    <!-- Dynamic Reports Grid (Populated asynchronously via jQuery AJAX) -->
    <section class="items-grid" id="reportsContainer">
        <!-- Rendered by assets/js/app.js without full page reload -->
        <div class="empty-state">
            <div class="empty-state-icon">⏳</div>
            <p>Loading items...</p>
        </div>
    </section>
</main>

<!-- Item Details Inspection Modal -->
<div class="modal-overlay" id="itemDetailsModal">
    <div class="modal-card" style="max-width: 640px;">
        <div class="modal-header">
            <div>
                <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 4px;">
                    <span id="detailTypeBadge" class="badge-type"></span>
                    <span id="detailStatusBadge" class="badge-status"></span>
                </div>
                <h3 class="modal-title" id="detailItemName">Item Details</h3>
            </div>
            <button type="button" class="modal-close-btn btn-close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <div id="detailImageWrap" style="margin-bottom: 20px;"></div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; background: var(--bg-primary); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-subtle); font-size: 13px;">
                <div>
                    <span style="color: var(--text-muted); display: block;">Category</span>
                    <strong id="detailCategory" style="color: var(--text-white);"></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block;">Campus Location</span>
                    <strong id="detailLocation" style="color: var(--text-white);"></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block;">Date</span>
                    <strong id="detailDate" style="color: var(--text-white);"></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block;">Reported By</span>
                    <strong id="detailReporter" style="color: var(--text-white);"></strong>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label class="form-label" style="font-size: 13px; color: var(--text-secondary);">Description & Details</label>
                <div id="detailDescription" style="background: rgba(30, 41, 59, 0.5); padding: 14px; border-radius: var(--radius-md); color: var(--text-primary); font-size: 14px; line-height: 1.6; white-space: pre-wrap;"></div>
            </div>

            <!-- Dynamic Action Container (Claim trigger, status, or owner review link) -->
            <div id="detailActionContainer" style="display: flex; justify-content: center;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-close-modal">Close</button>
        </div>
    </div>
</div>

<!-- Submit Claim / Found Response Modal -->
<div class="modal-overlay" id="claimModal">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="claimModalTitle">Submit Ownership Claim</h3>
                <p style="font-size: 13px; color: var(--text-secondary); margin-top: 2px;">
                    Item: <strong id="claimItemTitle" style="color: var(--accent-cyan);"></strong> 
                    (<span id="claimItemLocation"></span>)
                </p>
            </div>
            <button type="button" class="modal-close-btn btn-close-modal">&times;</button>
        </div>
        <form id="claimForm">
            <input type="hidden" name="report_id" id="claimReportId">
            <div class="modal-body">
                <div id="claimModalHint" style="background: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.2); padding: 12px 14px; border-radius: var(--radius-md); margin-bottom: 16px; font-size: 13px; color: var(--text-primary);">
                    💡 <strong>How Verification Works:</strong> Provide unique details that only the true owner would know (e.g. scratches, internal contents, phone wallpaper, serials, keychains). The founder will evaluate your proof and can approve your claim.
                </div>
                <div class="form-group">
                    <label class="form-label" id="claimModalLabel" for="claimMessage">Proof of Ownership / Distinguishing Marks *</label>
                    <textarea name="message" id="claimMessage" class="form-control" rows="4" 
                        placeholder="Describe unique details (e.g. blue sticker on bottom right, cracked edge, specific keychain, wallpaper pattern)..." required></textarea>
                    <small id="claimModalHelper" style="color: var(--text-muted); font-size: 12px; display: block; margin-top: 6px;">
                        The student who found this item will review all claims and resolve the item with the verified owner.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-close-modal">Cancel</button>
                <button type="submit" id="claimSubmitBtn" class="btn btn-primary">Submit Claim</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
