/**
 * Campus Lost & Found Portal - Claims Submission Module
 * Handles opening claim modal, verifying ownership statements, and submitting claims via AJAX
 */

(function () {
    'use strict';

    window.ClaimsModule = {
        init: function () {
            // Open claim / recovery lead modal from button
            $(document).on('click', '.btn-open-claim', function (e) {
                e.stopPropagation();
                const reportId = $(this).data('id');
                const itemName = $(this).data('name');
                const location = $(this).data('location');
                const reportType = $(this).data('type') || 'found';
                const isLost = reportType === 'lost';

                // If inside itemDetailsModal, close it first
                window.closeModal('itemDetailsModal');

                $('#claimReportId').val(reportId);
                $('#claimItemTitle').text(itemName);
                $('#claimItemLocation').text(location);
                $('#claimMessage').val('');

                if (isLost) {
                    $('#claimModalTitle').text('🙋 I Found This Item!');
                    $('#claimModalHint').html('💡 <strong>Recovery Details:</strong> Let the student who lost this know where you found it, where it is currently kept (e.g. Campus Security, Library Desk), or how to contact you to collect it.');
                    $('#claimModalLabel').text('Where You Found It & How to Retrieve *');
                    $('#claimMessage').attr('placeholder', 'Describe where you found it, where you turned it in (e.g. Library front desk, Security Office), or how the owner can contact you to get it back...');
                    $('#claimModalHelper').text('The person who lost this item will review your submission and can confirm recovery to resolve this item.');
                    $('#claimSubmitBtn').text('Notify Owner I Found It');
                } else {
                    $('#claimModalTitle').text('Submit Ownership Claim');
                    $('#claimModalHint').html('💡 <strong>How Verification Works:</strong> Provide unique details that only the true owner would know (e.g. scratches, internal contents, phone wallpaper, serials, keychains). The founder will evaluate your proof and can approve your claim.');
                    $('#claimModalLabel').text('Proof of Ownership / Distinguishing Marks *');
                    $('#claimMessage').attr('placeholder', 'Describe unique details (e.g. blue sticker on bottom right, cracked edge, specific keychain, wallpaper pattern)...');
                    $('#claimModalHelper').text('The student who found this item will review all claims and resolve the item with the verified owner.');
                    $('#claimSubmitBtn').text('Submit Claim');
                }

                window.openModal('claimModal');
                setTimeout(() => $('#claimMessage').focus(), 150);
            });

            // Submit claim form via AJAX
            $('#claimForm').on('submit', function (e) {
                e.preventDefault();

                const message = $('#claimMessage').val().trim();
                if (message.length < 10) {
                    window.showToast('Please provide more detailed information (at least 10 characters).', 'error');
                    $('#claimMessage').focus();
                    return;
                }

                const submitBtn = $('#claimSubmitBtn');
                const originalText = submitBtn.text();
                submitBtn.prop('disabled', true).text('Submitting...');

                const formData = $(this).serialize();

                $.ajax({
                    url: 'api/claims.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function (res) {
                        submitBtn.prop('disabled', false).text(originalText);
                        if (res.success) {
                            window.closeModal('claimModal');
                            window.showToast(res.message || 'Submitted successfully!', 'success');

                            // Refresh reports feed to reflect new claim state without page reload
                            if (window.ItemsFeedModule && typeof window.ItemsFeedModule.fetchReports === 'function') {
                                window.ItemsFeedModule.fetchReports();
                            }
                        } else {
                            window.showToast(res.message || 'Submission failed', 'error');
                        }
                    },
                    error: function (xhr) {
                        submitBtn.prop('disabled', false).text(originalText);
                        const err = xhr.responseJSON ? xhr.responseJSON.message : 'Server error while submitting.';
                        window.showToast(err, 'error');
                    }
                });
            });
        }
    };
})();
