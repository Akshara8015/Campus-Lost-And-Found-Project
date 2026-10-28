/**
 * Campus Lost & Found Portal - Item Details Modal Module
 * Displays full item specifications, photo, campus location, and direct claim trigger
 */

(function () {
    'use strict';

    window.ItemDetailsModule = {
        init: function () {
            // Open Item Details Modal when clicking "View Details" or item media
            $(document).on('click', '.btn-view-details, .item-clickable', function (e) {
                // If clicked an action button inside the card, ignore
                if ($(e.target).closest('.btn-open-claim, .btn-secondary, a').length && !$(e.target).hasClass('btn-view-details')) {
                    return;
                }

                const card = $(this).closest('.item-card');
                const itemData = card.data('item');

                if (!itemData) return;

                window.ItemDetailsModule.showModal(itemData);
            });
        },

        showModal: function (item) {
            const isLost = item.report_type === 'lost';
            const modal = $('#itemDetailsModal');
            if (!modal.length) return;

            // Populate fields
            $('#detailItemName').text(item.item_name);
            $('#detailCategory').text(item.category);
            $('#detailDate').text((isLost ? 'Lost on: ' : 'Found on: ') + item.formatted_date);
            $('#detailLocation').text(item.location);
            $('#detailDescription').text(item.description);
            if (item.reporter_email) {
                $('#detailReporter').html(`${window.escapeHtml(item.reporter_name)} &bull; <a href="mailto:${window.escapeHtml(item.reporter_email)}" style="color: var(--accent-cyan); text-decoration: underline;">✉️ ${window.escapeHtml(item.reporter_email)}</a>`);
            } else {
                $('#detailReporter').text(item.reporter_name);
            }

            // Badge styling
            const typeBadge = $('#detailTypeBadge');
            typeBadge.attr('class', `badge-type ${item.report_type}`).text(isLost ? 'LOST ITEM' : 'FOUND ITEM');

            const statusBadge = $('#detailStatusBadge');
            statusBadge.attr('class', `badge-status ${item.status}`).text(item.status.toUpperCase().replace('_', ' '));

            // Image display
            const imgWrap = $('#detailImageWrap');
            if (item.image_path) {
                imgWrap.html(`<img src="${window.escapeHtml(item.image_path)}" alt="${window.escapeHtml(item.item_name)}" style="max-height: 280px; width: 100%; object-fit: contain; border-radius: var(--radius-md); background: #020617;">`);
            } else {
                imgWrap.html(`<div style="height: 180px; display: flex; align-items: center; justify-content: center; font-size: 56px; background: #0f172a; border-radius: var(--radius-md);">${isLost ? '🔎' : '🎁'}</div>`);
            }

            // Action button inside details modal
            const actionContainer = $('#detailActionContainer');
            actionContainer.empty();

            if (item.status === 'resolved') {
                actionContainer.html('<span class="badge-status resolved" style="padding: 8px 16px; font-size: 14px;">✔ This item has been resolved and returned to its owner.</span>');
            } else if (item.status === 'closed') {
                actionContainer.html('<span class="badge-status closed" style="padding: 8px 16px; font-size: 14px;">This report is closed.</span>');
            } else if (isLost) {
                // Lost item details actions
                if (item.is_owner) {
                    actionContainer.html(`
                        <div style="background: rgba(167, 139, 250, 0.1); border: 1px solid rgba(167, 139, 250, 0.3); padding: 12px 16px; border-radius: var(--radius-md); width: 100%;">
                            <strong style="color: #a78bfa; display: block; font-size: 14px; margin-bottom: 4px;">🏷 You reported this item lost</strong>
                            <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 8px;">
                                Other students can notify you if they find it. You have received <strong>${item.claims_count}</strong> recovery lead(s).
                            </p>
                            <a href="my_reports.php" class="btn btn-primary btn-sm">📋 Go to Dashboard to Review & Resolve Leads</a>
                        </div>
                    `);
                } else if (item.my_claim_status) {
                    actionContainer.html(`
                        <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); padding: 12px 16px; border-radius: var(--radius-md); width: 100%;">
                            <strong style="color: #34d399; display: block; font-size: 14px; margin-bottom: 4px;">🙋 Your Lead Status: ${item.my_claim_status.toUpperCase()}</strong>
                            <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 8px;">You notified the owner where you found this item.</p>
                            <a href="my_reports.php" class="btn btn-secondary btn-sm">View Status in My Dashboard</a>
                        </div>
                    `);
                } else if (window.CurrentUserId) {
                    actionContainer.html(`
                        <button type="button" class="btn btn-success btn-open-claim" 
                            data-id="${item.id}" 
                            data-name="${window.escapeHtml(item.item_name)}" 
                            data-location="${window.escapeHtml(item.location)}"
                            data-type="lost"
                            style="padding: 10px 24px; font-size: 14px;">
                            🙋 I Found This Item! (Notify Owner)
                        </button>
                    `);
                } else {
                    actionContainer.html(`
                        <a href="login.php?redirect=index.php" class="btn btn-success" style="padding: 10px 24px; font-size: 14px;">
                            🔑 Log in to Report You Found This
                        </a>
                    `);
                }
            } else {
                // Found item details actions
                if (item.is_owner) {
                    actionContainer.html(`
                        <div style="background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.3); padding: 12px 16px; border-radius: var(--radius-md); width: 100%;">
                            <strong style="color: var(--accent-cyan); display: block; font-size: 14px; margin-bottom: 4px;">🏷 You reported finding this item</strong>
                            <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 8px;">
                                Other students can submit claims for you to review. You have received <strong>${item.claims_count}</strong> claim(s).
                            </p>
                            <a href="my_reports.php" class="btn btn-primary btn-sm">📋 Go to Dashboard to Review & Resolve Claims</a>
                        </div>
                    `);
                } else if (item.my_claim_status) {
                    actionContainer.html(`
                        <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); padding: 12px 16px; border-radius: var(--radius-md); width: 100%;">
                            <strong style="color: #f59e0b; display: block; font-size: 14px; margin-bottom: 4px;">🙋 Your Claim Status: ${item.my_claim_status.toUpperCase()}</strong>
                            <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 8px;">You have already submitted an ownership verification for this item.</p>
                            <a href="my_reports.php" class="btn btn-secondary btn-sm">View Status in My Dashboard</a>
                        </div>
                    `);
                } else if (window.CurrentUserId) {
                    actionContainer.html(`
                        <button type="button" class="btn btn-primary btn-open-claim" 
                            data-id="${item.id}" 
                            data-name="${window.escapeHtml(item.item_name)}" 
                            data-location="${window.escapeHtml(item.location)}"
                            data-type="found"
                            style="padding: 10px 24px; font-size: 14px;">
                            🙋 Claim This Item as Owner
                        </button>
                    `);
                } else {
                    actionContainer.html(`
                        <a href="login.php?redirect=index.php" class="btn btn-primary" style="padding: 10px 24px; font-size: 14px;">
                            🔑 Log in to Claim This Item
                        </a>
                    `);
                }
            }

            window.openModal('itemDetailsModal');
        }
    };
})();
