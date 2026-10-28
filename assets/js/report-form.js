/**
 * Campus Lost & Found Portal - Report Form Module
 * Handles client-side image preview and form enhancements for report.php
 */

(function () {
    'use strict';

    window.ReportFormModule = {
        init: function () {
            const fileInput = $('#imageFile');
            const previewContainer = $('#imagePreviewContainer');
            const previewImg = $('#imagePreview');

            if (!fileInput.length) return;

            // Live image preview
            fileInput.on('change', function () {
                const file = this.files && this.files[0];
                if (file) {
                    // Check file size (max 5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        window.showToast('Image size exceeds 5MB limit.', 'error');
                        $(this).val('');
                        previewContainer.hide();
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function (e) {
                        previewImg.attr('src', e.target.result);
                        previewContainer.fadeIn(200);
                    };
                    reader.readAsDataURL(file);
                } else {
                    previewContainer.hide();
                }
            });
        }
    };
})();
