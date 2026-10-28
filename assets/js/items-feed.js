/**
 * Campus Lost & Found Portal - Items Feed Module
 * Multi-criteria search, live filters, card rendering, and async feed synchronization
 */

(function () {
    'use strict';

    const state = {
        q: '',
        type: 'all',
        category: 'all',
        status: 'all',
        debounceTimer: null
    };

    window.ItemsFeedModule = {
        init: function () {
            // Initial load
            window.ItemsFeedModule.fetchReports();

            // Search bar with 250ms debouncing
            $('#searchInput').on('input', function () {
                clearTimeout(state.debounceTimer);
                state.q = $(this).val().trim();
                state.debounceTimer = setTimeout(() => {
                    window.ItemsFeedModule.fetchReports();
                }, 250);
            });

            // Report type pills (All, Lost, Found)
            $('.pill-btn').on('click', function () {
                $('.pill-btn').removeClass('active');
                $(this).addClass('active');
                state.type = $(this).data('type');
                window.ItemsFeedModule.fetchReports();
            });

            // Category filter
            $('#categoryFilter').on('change', function () {
                state.category = $(this).val();
                window.ItemsFeedModule.fetchReports();
            });

            // Status filter
            $('#statusFilter').on('change', function () {
                state.status = $(this).val();
                window.ItemsFeedModule.fetchReports();
            });
        },

        fetchReports: function () {
            const container = $('#reportsContainer');
            if (!container.length) return;

            container.css('opacity', '0.5');

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
                    container.css('opacity', '1');
                    if (res.success) {
                        window.CurrentUserId = res.current_user_id;
                        window.ItemsFeedModule.renderReports(res.reports, res.current_user_id);
                    } else {
                        container.html('<div class="empty-state">Failed to load reports.</div>');
                    }
                },
                error: function () {
                    container.css('opacity', '1');
                    container.html('<div class="empty-state"><div class="empty-state-icon">⚠️</div><h3>Server Connection Error</h3><p>Could not connect to the API. Ensure your web server and PostgreSQL are running.</p></div>');
                }
            });
        },

        renderReports: function (reports, currentUserId) {
            const container = $('#reportsContainer');
            container.empty();

            if (!reports || reports.length === 0) {
                container.html(`
                    <div class="empty-state">
                        <div class="empty-state-icon">🔍</div>
                        <h3 style="margin-bottom: 8px;">No items found</h3>
                        <p>Try adjusting your search query or switching filters.</p>
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

                // Media thumbnail
                let mediaHtml = '';
                if (item.image_path) {
                    mediaHtml = `<img src="${window.escapeHtml(item.image_path)}" alt="${window.escapeHtml(item.item_name)}" class="item-image" loading="lazy">`;
                } else {
                    mediaHtml = `<div class="item-placeholder-icon">${isLost ? '🔎' : '🎁'}</div>`;
                }

                // Interactive Action logic
                let actionHtml = '';
                if (item.status === 'resolved') {
                    actionHtml = `<span class="badge-status resolved">✔ Resolved</span>`;
                } else if (item.status === 'closed') {
                    actionHtml = `<span class="badge-status closed">Closed</span>`;
                } else if (isLost) {
                    // Lost item actions
                    if (item.is_owner) {
                        // Current user reported it lost
                        actionHtml = `
                            <a href="my_reports.php" class="btn btn-secondary btn-sm" title="Review responses from users who found it">
                                📋 Review Responses (${item.claims_count})
                            </a>
                        `;
                    } else if (item.my_claim_status) {
                        // Current user already submitted recovery notice
                        const leadStatus = item.my_claim_status.toUpperCase();
                        actionHtml = `
                            <span class="badge-status ${item.my_claim_status}">Found Lead: ${leadStatus}</span>
                        `;
                    } else if (currentUserId) {
                        // Another logged in user found it
                        actionHtml = `
                            <button type="button" class="btn btn-success btn-sm btn-open-claim" 
                                data-id="${item.id}" 
                                data-name="${window.escapeHtml(item.item_name)}" 
                                data-location="${window.escapeHtml(item.location)}"
                                data-type="lost">
                                🙋 I Found This!
                            </button>
                        `;
                    } else {
                        // Guest visitor
                        actionHtml = `<a href="login.php?redirect=index.php" class="btn btn-secondary btn-sm">🔑 Log in to Report Found</a>`;
                    }
                } else {
                    // Found item actions
                    if (item.is_owner) {
                        // Current user is the finder
                        actionHtml = `
                            <a href="my_reports.php" class="btn btn-secondary btn-sm" title="Review incoming claims on this item">
                                📋 Manage Claims (${item.claims_count})
                            </a>
                        `;
                    } else if (item.my_claim_status) {
                        // Current user already claimed
                        const claimStatus = item.my_claim_status.toUpperCase();
                        actionHtml = `
                            <span class="badge-status ${item.my_claim_status}">Claim: ${claimStatus}</span>
                        `;
                    } else if (currentUserId) {
                        // Another logged in user can claim
                        actionHtml = `
                            <button type="button" class="btn btn-primary btn-sm btn-open-claim" 
                                data-id="${item.id}" 
                                data-name="${window.escapeHtml(item.item_name)}" 
                                data-location="${window.escapeHtml(item.location)}"
                                data-type="found">
                                🙋 Claim Item
                            </button>
                        `;
                    } else {
                        // Guest visitor
                        actionHtml = `<a href="login.php?redirect=index.php" class="btn btn-secondary btn-sm">🔑 Log in to Claim</a>`;
                    }
                }

                // Owner indicator banner
                let ownerHintHtml = '';
                if (item.is_owner) {
                    if (isLost) {
                        ownerHintHtml = `
                            <div style="font-size: 11px; color: #a78bfa; background: rgba(167, 139, 250, 0.08); padding: 4px 10px; border-radius: 4px; margin-top: 6px;">
                                🏷 You reported this lost &bull; ${item.claims_count} response(s) received
                            </div>
                        `;
                    } else {
                        ownerHintHtml = `
                            <div style="font-size: 11px; color: var(--accent-cyan); background: rgba(56, 189, 248, 0.08); padding: 4px 10px; border-radius: 4px; margin-top: 6px;">
                                🛡 You reported finding this &bull; ${item.claims_count} claim(s) received
                            </div>
                        `;
                    }
                }

                const card = $(`
                    <div class="item-card" id="reportCard-${item.id}">
                        <div class="item-media item-clickable" style="cursor: pointer;">
                            ${mediaHtml}
                            <div class="badge-tag-wrap">
                                <span class="badge-type ${typeClass}">${typeLabel}</span>
                                <span class="badge-status ${item.status}">${statusLabel}</span>
                            </div>
                        </div>
                        <div class="item-body">
                            <div class="item-meta-top">
                                <span class="item-category">${window.escapeHtml(item.category)}</span>
                                <span class="item-date">${window.escapeHtml(item.formatted_date)}</span>
                            </div>
                            <h3 class="item-title item-clickable" style="cursor: pointer;">${window.escapeHtml(item.item_name)}</h3>
                            <p class="item-desc">${window.escapeHtml(item.description)}</p>
                            <div class="item-info-row">
                                <span class="icon">📍</span>
                                <span>${window.escapeHtml(item.location)}</span>
                            </div>
                            <div class="item-info-row" style="margin-top: 6px; font-size: 13px;">
                                <span class="icon">👤</span>
                                <span>
                                    ${isLost ? 'Owner:' : 'Finder:'} <strong>${window.escapeHtml(item.reporter_name)}</strong>
                                    ${item.reporter_email ? ` &bull; <a href="mailto:${window.escapeHtml(item.reporter_email)}" style="color: var(--accent-cyan); text-decoration: underline;" title="Send email to ${window.escapeHtml(item.reporter_name)}">✉️ ${window.escapeHtml(item.reporter_email)}</a>` : ''}
                                </span>
                            </div>
                            ${ownerHintHtml}
                        </div>
                        <div class="item-footer">
                            <button type="button" class="btn btn-secondary btn-sm btn-view-details" style="padding: 6px 12px; font-size: 12px;">
                                👁 Details
                            </button>
                            <div>${actionHtml}</div>
                        </div>
                    </div>
                `);

                // Attach full data object to card DOM
                card.data('item', item);
                container.append(card);
            });
        }
    };
})();
