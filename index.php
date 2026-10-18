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

<!-- Submit Claim Modal -->
<div class="modal-overlay" id="claimModal">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <h3 class="modal-title">Submit Ownership Claim</h3>
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
                <div class="form-group">
                    <label class="form-label" for="claimMessage">Proof of Ownership / Distinguishing Features</label>
                    <textarea name="message" id="claimMessage" class="form-control" rows="4" 
                        placeholder="Please describe specific unique features (e.g., stickers, scratches, lock screen wallpaper, specific contents) to prove this item is yours..." required></textarea>
                    <small style="color: var(--text-muted); font-size: 12px; display: block; margin-top: 6px;">
                        The person who reported finding this item will review your claim before approving it.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-close-modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Claim</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
