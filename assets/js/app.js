/**
 * Campus Lost & Found Portal - Interactive Client Engine
 * Uses jQuery for AJAX, DOM updates, multi-filter search, and status updates
 */

$(document).ready(function () {
    // Current state
    const state = {
        q: '',
        type: 'all',
        category: 'all',
        status: 'all',
        debounceTimer: null
    };

    // Initialize home page item loader if container exists
    if ($('#reportsContainer').length) {
        initHomeFeed();
    }

    // Initialize My Dashboard if on dashboard page
    if ($('#myDashboardContainer').length) {
        initDashboard();
    }

    /**
     * Show animated toast notifications
     */
    window.showToast = function (message, type = 'success') {
        const icon = type === 'success' ? '✅' : '⚠️';
        const toast = $(`
            <div class="toast ${type}">
                <span>${icon}</span>
                <span>${escapeHtml(message)}</span>
            </div>
        `);
        $('#toastContainer').append(toast);

        setTimeout(() => {
            toast.fadeOut(300, function () { $(this).remove(); });
        }, 4000);
    };

    /**
     * Helper to escape HTML and prevent XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        return $('<div>').text(text).html();
    }

    /**
     * Home Feed Controller
     */
    function initHomeFeed() {
        // Load initial reports
        fetchReports();

        // Search bar with 250ms debounce
        $('#searchInput').on('input', function () {
            clearTimeout(state.debounceTimer);
            state.q = $(this).val().trim();
            state.debounceTimer = setTimeout(() => {
                fetchReports();
            }, 250);
        });

        // Report type pills (All, Lost, Found)
        $('.pill-btn').on('click', function () {
            $('.pill-btn').removeClass('active');
            $(this).addClass('active');
            state.type = $(this).data('type');
            fetchReports();
        });

        // Category filter
        $('#categoryFilter').on('change', function () {
            state.category = $(this).val();
            fetchReports();
        });

        // Status filter
        $('#statusFilter').on('change', function () {
            state.status = $(this).val();
            fetchReports();
        });

        // Open claim modal
        $(document).on('click', '.btn-open-claim', function () {
            const reportId = $(this).data('id');
            const itemName = $(this).data('name');
            const location = $(this).data('location');

            $('#claimReportId').val(reportId);
            $('#claimItemTitle').text(itemName);
            $('#claimItemLocation').text(location);
            $('#claimMessage').val('');
            $('#claimModal').addClass('active');
        });

        // Close claim modal
        $('.btn-close-modal, #claimModal').on('click', function (e) {
            if (e.target === this) {
                $('#claimModal').removeClass('active');
            }
        });

        // Submit claim form via AJAX (no reload)
        $('#claimForm').on('submit', function (e) {
            e.preventDefault();
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).text('Submitting...');

            const formData = $(this).serialize();

            $.ajax({
                url: 'api/claims.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function (res) {
                    submitBtn.prop('disabled', false).text('Submit Claim');
                    if (res.success) {
                        $('#claimModal').removeClass('active');
                        showToast(res.message || 'Claim submitted successfully!');
                        // Refresh reports feed without page reload
                        fetchReports();
                    } else {
                        showToast(res.message || 'Failed to submit claim', 'error');
                    }
                },
                error: function (xhr) {
                    submitBtn.prop('disabled', false).text('Submit Claim');
                    const err = xhr.responseJSON ? xhr.responseJSON.message : 'Error submitting claim.';
                    showToast(err, 'error');
                }
            });
        });
    }

    /**
     * AJAX Fetch & Render Reports
     */
    function fetchReports() {
        $('#reportsContainer').css('opacity', '0.5');

        $.ajax({
            url: 'api/reports.php',
            method: 'GET',
            data: {
                q: state.q,
                type: state.type,
                category: state.category,
                status: state.status
            },
            dataType: 'json',
            success: function (res) {
                $('#reportsContainer').css('opacity', '1');
                if (res.success) {
                    renderReports(res.reports, res.current_user_id);
                } else {
                    $('#reportsContainer').html('<div class="empty-state">Failed to load reports.</div>');
                }
            },
            error: function () {
                $('#reportsContainer').css('opacity', '1');
                $('#reportsContainer').html('<div class="empty-state">Error connecting to server.</div>');
            }
        });
    }

    /**
     * Render Reports Grid
     */
    function renderReports(reports, currentUserId) {
        const container = $('#reportsContainer');
        container.empty();

        if (!reports || reports.length === 0) {
            container.html(`
                <div class="empty-state">
                    <div class="empty-state-icon">🔍</div>
                    <h3 style="margin-bottom: 8px;">No items found</h3>
                    <p>Try adjusting your search query or filters.</p>
                </div>
            `);
            return;
        }

        reports.forEach(item => {
            const isLost = item.report_type === 'lost';
            const typeLabel = isLost ? 'Lost Item' : 'Found Item';
            const typeClass = isLost ? 'lost' : 'found';

            let statusLabel = item.status.replace('_', ' ');
            statusLabel = statusLabel.charAt(0).toUpperCase() + statusLabel.slice(1);

            // Thumbnail or clean icon
            let mediaHtml = '';
            if (item.image_path) {
                mediaHtml = `<img src="${escapeHtml(item.image_path)}" alt="${escapeHtml(item.item_name)}" class="item-image" loading="lazy">`;
            } else {
                mediaHtml = `<div class="item-placeholder-icon">${isLost ? '🔎' : '🎁'}</div>`;
            }

            // Action button logic
            let actionHtml = '';
            if (item.status === 'resolved') {
                actionHtml = `<span class="badge-status resolved">Resolved</span>`;
            } else if (item.status === 'closed') {
                actionHtml = `<span class="badge-status closed">Closed</span>`;
            } else if (isLost) {
                actionHtml = `<span style="font-size: 12px; color: var(--text-muted);">Reported by ${escapeHtml(item.reporter_name)}</span>`;
            } else {
                // Found item
                if (item.is_owner) {
                    actionHtml = `<a href="my_reports.php" class="btn btn-secondary btn-sm">Manage Claims (${item.claims_count})</a>`;
                } else if (item.my_claim_status) {
                    const statusText = item.my_claim_status.toUpperCase();
                    actionHtml = `<span class="badge-status ${item.my_claim_status}">Claim: ${statusText}</span>`;
                } else if (currentUserId) {
                    actionHtml = `
                        <button type="button" class="btn btn-primary btn-sm btn-open-claim" 
                            data-id="${item.id}" 
                            data-name="${escapeHtml(item.item_name)}" 
                            data-location="${escapeHtml(item.location)}">
                            Claim Item
                        </button>
                    `;
                } else {
                    actionHtml = `<a href="login.php?redirect=index.php" class="btn btn-secondary btn-sm">Log in to Claim</a>`;
                }
            }

            const card = $(`
                <div class="item-card" id="reportCard-${item.id}">
                    <div class="item-media">
                        ${mediaHtml}
                        <div class="badge-tag-wrap">
                            <span class="badge-type ${typeClass}">${typeLabel}</span>
                            <span class="badge-status ${item.status}">${statusLabel}</span>
                        </div>
                    </div>
                    <div class="item-body">
                        <div class="item-meta-top">
                            <span class="item-category">${escapeHtml(item.category)}</span>
                            <span class="item-date">${escapeHtml(item.formatted_date)}</span>
                        </div>
                        <h3 class="item-title">${escapeHtml(item.item_name)}</h3>
                        <p class="item-desc">${escapeHtml(item.description)}</p>
                        <div class="item-info-row">
                            <span class="icon">📍</span>
                            <span>${escapeHtml(item.location)}</span>
                        </div>
                    </div>
                    <div class="item-footer">
                        <span style="font-size: 12px; color: var(--text-muted);">
                            ${isLost ? 'Lost on ' : 'Found on '} ${escapeHtml(item.formatted_date)}
                        </span>
                        <div>${actionHtml}</div>
                    </div>
                </div>
            `);

            container.append(card);
        });
    }

    /**
     * Dashboard Controller (My Reports & Claims Management)
     */
    function initDashboard() {
        // Handle approve claim via AJAX
        $(document).on('click', '.btn-approve-claim', function () {
            const claimId = $(this).data('claim-id');
            const reportId = $(this).data('report-id');
            const btn = $(this);

            if (!confirm('Are you sure you want to approve this claim? This will resolve the item and automatically decline all other pending claims.')) {
                return;
            }

            btn.prop('disabled', true).text('Approving...');

            $.ajax({
                url: 'api/update_status.php',
                method: 'POST',
                data: {
                    action: 'approve_claim',
                    claim_id: claimId
                },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        showToast(res.message);
                        // Dynamically update the claim row in-place without page reload
                        const container = $(`#claimsList-${reportId}`);
                        container.find('.claim-status-badge').each(function () {
                            const row = $(this).closest('.claim-item-row');
                            const rowClaimId = row.data('claim-id');
                            if (rowClaimId == claimId) {
                                $(this).attr('class', 'badge-status resolved claim-status-badge').text('APPROVED');
                                row.find('.claim-actions').html('<span style="color: var(--color-resolved); font-weight: 600; font-size: 13px;">✔ Approved Claimant</span>');
                            } else {
                                $(this).attr('class', 'badge-status closed claim-status-badge').text('DECLINED');
                                row.find('.claim-actions').html('<span style="color: var(--text-muted); font-size: 13px;">Declined (other claim approved)</span>');
                            }
                        });

                        // Update report header badge
                        $(`#reportStatusBadge-${reportId}`).attr('class', 'badge-status resolved').text('RESOLVED');
                    } else {
                        btn.prop('disabled', false).text('Approve');
                        showToast(res.message || 'Failed to approve claim', 'error');
                    }
                },
                error: function (xhr) {
                    btn.prop('disabled', false).text('Approve');
                    const err = xhr.responseJSON ? xhr.responseJSON.message : 'Error approving claim.';
                    showToast(err, 'error');
                }
            });
        });

        // Handle reject single claim via AJAX
        $(document).on('click', '.btn-reject-claim', function () {
            const claimId = $(this).data('claim-id');
            const reportId = $(this).data('report-id');
            const btn = $(this);

            if (!confirm('Are you sure you want to reject this claim?')) {
                return;
            }

            btn.prop('disabled', true).text('Rejecting...');

            $.ajax({
                url: 'api/update_status.php',
                method: 'POST',
                data: {
                    action: 'reject_claim',
                    claim_id: claimId
                },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        showToast(res.message);
                        const row = $(`#claimRow-${claimId}`);
                        row.find('.claim-status-badge').attr('class', 'badge-status closed claim-status-badge').text('REJECTED');
                        row.find('.claim-actions').html('<span style="color: var(--text-muted); font-size: 13px;">Rejected</span>');

                        if (res.new_report_status) {
                            $(`#reportStatusBadge-${reportId}`).attr('class', `badge-status ${res.new_report_status}`).text(res.new_report_status.toUpperCase());
                        }
                    } else {
                        btn.prop('disabled', false).text('Reject');
                        showToast(res.message || 'Failed to reject claim', 'error');
                    }
                },
                error: function (xhr) {
                    btn.prop('disabled', false).text('Reject');
                    const err = xhr.responseJSON ? xhr.responseJSON.message : 'Error rejecting claim.';
                    showToast(err, 'error');
                }
            });
        });

        // Handle direct report status update (e.g. resolve lost item)
        $(document).on('change', '.select-report-status', function () {
            const reportId = $(this).data('report-id');
            const newStatus = $(this).val();

            $.ajax({
                url: 'api/update_status.php',
                method: 'POST',
                data: {
                    action: 'update_report_status',
                    report_id: reportId,
                    status: newStatus
                },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        showToast(res.message);
                        $(`#reportStatusBadge-${reportId}`).attr('class', `badge-status ${newStatus}`).text(newStatus.toUpperCase().replace('_', ' '));
                    } else {
                        showToast(res.message || 'Status update failed', 'error');
                    }
                },
                error: function (xhr) {
                    const err = xhr.responseJSON ? xhr.responseJSON.message : 'Error updating status.';
                    showToast(err, 'error');
                }
            });
        });
    }
});
