/**
 * Campus Lost & Found Portal - Dashboard Module
 * Handles founder operations: reviewing incoming claims, approving genuine claimants,
 * resolving between multiple claims, rejecting invalid claims, and status updates.
 */

(function () {
    'use strict';

    window.DashboardModule = {
        init: function () {
            // Approve claim / Confirm recovery lead handler
            $(document).on('click', '.btn-approve-claim', function () {
                const claimId = $(this).data('claim-id');
                const reportId = $(this).data('report-id');
                const reportType = $(this).data('report-type') || 'found';
                const claimantName = $(this).data('claimant') || 'this user';
                const isLost = reportType === 'lost';
                const btn = $(this);

                const promptMsg = isLost
                    ? `Are you sure you want to confirm that ${claimantName} found your lost item?\n\n` +
                      `• Your report will be marked as RESOLVED (Recovered).\n` +
                      `• All other pending responses will be closed.\n` +
                      `• Contact ${claimantName} to retrieve your item.`
                    : `Are you sure you want to approve ${claimantName}'s claim as the genuine owner?\n\n` +
                      `• This item will be marked as RESOLVED.\n` +
                      `• All other pending claims for this item will be automatically DECLINED.\n` +
                      `• The approved claimant will be shown your contact email to arrange collection.`;

                if (!confirm(promptMsg)) {
                    return;
                }

                btn.prop('disabled', true).text('Confirming...');

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
                            window.showToast(res.message, 'success');

                            // Update all claim rows in this report's list
                            const container = $(`#claimsList-${reportId}`);
                            container.find('.claim-item-row').each(function () {
                                const row = $(this);
                                const rowClaimId = row.data('claim-id');
                                const badge = row.find('.claim-status-badge');
                                const actions = row.find('.claim-actions');

                                if (rowClaimId == claimId) {
                                    badge.attr('class', 'badge-status resolved claim-status-badge').text('APPROVED');
                                    const approvedText = isLost 
                                        ? '✔ Confirmed Recovered (Handover in progress)' 
                                        : '✔ Approved Owner (Handover in progress)';
                                    actions.html(`<span style="color: var(--color-resolved); font-weight: 600; font-size: 13px;">${approvedText}</span>`);
                                    row.css('border-color', 'var(--color-resolved)');
                                } else {
                                    badge.attr('class', 'badge-status closed claim-status-badge').text('DECLINED');
                                    const declinedText = isLost 
                                        ? 'Closed (Competing recovery lead confirmed)' 
                                        : 'Declined (Competing claim approved)';
                                    actions.html(`<span style="color: var(--text-muted); font-size: 13px;">${declinedText}</span>`);
                                    row.css('opacity', '0.6');
                                }
                            });

                            // Update report header status badge
                            $(`#reportStatusBadge-${reportId}`)
                                .attr('class', 'badge-status resolved')
                                .text('RESOLVED');

                            // Update select dropdown
                            $(`select.select-report-status[data-report-id="${reportId}"]`).val('resolved');
                        } else {
                            btn.prop('disabled', false).text('✔ Approve Claim');
                            window.showToast(res.message || 'Failed to approve claim', 'error');
                        }
                    },
                    error: function (xhr) {
                        btn.prop('disabled', false).text('✔ Approve Claim');
                        const err = xhr.responseJSON ? xhr.responseJSON.message : 'Server error approving claim.';
                        window.showToast(err, 'error');
                    }
                });
            });

            // Reject single claim handler
            $(document).on('click', '.btn-reject-claim', function () {
                const claimId = $(this).data('claim-id');
                const reportId = $(this).data('report-id');
                const claimantName = $(this).data('claimant') || 'this claim';
                const btn = $(this);

                if (!confirm(`Are you sure you want to decline ${claimantName}'s claim? Other claims will remain pending.`)) {
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
                            window.showToast(res.message, 'info');

                            const row = $(`#claimRow-${claimId}`);
                            row.find('.claim-status-badge')
                                .attr('class', 'badge-status closed claim-status-badge')
                                .text('REJECTED');
                            row.find('.claim-actions')
                                .html('<span style="color: var(--text-muted); font-size: 13px;">Rejected / Declined</span>');
                            row.css('opacity', '0.6');

                            // If report status changed back to open
                            if (res.new_report_status) {
                                $(`#reportStatusBadge-${reportId}`)
                                    .attr('class', `badge-status ${res.new_report_status}`)
                                    .text(res.new_report_status.toUpperCase().replace('_', ' '));
                                $(`select.select-report-status[data-report-id="${reportId}"]`).val(res.new_report_status);
                            }
                        } else {
                            btn.prop('disabled', false).text('✖ Reject');
                            window.showToast(res.message || 'Failed to reject claim', 'error');
                        }
                    },
                    error: function (xhr) {
                        btn.prop('disabled', false).text('✖ Reject');
                        const err = xhr.responseJSON ? xhr.responseJSON.message : 'Server error rejecting claim.';
                        window.showToast(err, 'error');
                    }
                });
            });

            // Direct report status selector handler
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
                            window.showToast(res.message, 'success');
                            $(`#reportStatusBadge-${reportId}`)
                                .attr('class', `badge-status ${newStatus}`)
                                .text(newStatus.toUpperCase().replace('_', ' '));
                        } else {
                            window.showToast(res.message || 'Status update failed', 'error');
                        }
                    },
                    error: function (xhr) {
                        const err = xhr.responseJSON ? xhr.responseJSON.message : 'Error updating status.';
                        window.showToast(err, 'error');
                    }
                });
            });

            // Delete report permanently handler (e.g. if reported by mistake)
            $(document).on('click', '.btn-delete-report', function (e) {
                e.preventDefault();
                const reportId = $(this).data('report-id');
                const itemName = $(this).data('name') || 'this report';
                const btn = $(this);

                const confirmed = confirm(
                    `Are you sure you want to permanently delete "${itemName}"?\n\n` +
                    `• The report and all submitted responses will be deleted immediately.\n` +
                    `• This cannot be undone.`
                );

                if (!confirmed) {
                    return;
                }

                btn.prop('disabled', true).text('Deleting...');

                $.ajax({
                    url: 'api/update_status.php',
                    method: 'POST',
                    data: {
                        action: 'delete_report',
                        report_id: reportId
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            window.showToast(res.message || 'Report deleted successfully.', 'success');
                            const row = $(`#reportCardRow-${reportId}`);
                            row.fadeOut(350, function () {
                                $(this).remove();
                                // Check if any reports remain
                                if ($('.filter-card').length === 0) {
                                    window.location.reload();
                                }
                            });
                        } else {
                            btn.prop('disabled', false).text('🗑 Delete');
                            window.showToast(res.message || 'Failed to delete report', 'error');
                        }
                    },
                    error: function (xhr) {
                        btn.prop('disabled', false).text('🗑 Delete');
                        const err = xhr.responseJSON ? xhr.responseJSON.message : 'Error deleting report.';
                        window.showToast(err, 'error');
                    }
                });
            });
        }
    };
})();
