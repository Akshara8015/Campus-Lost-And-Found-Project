/**
 * Campus Lost & Found Portal - Toast & UI Utilities Module
 * Handles animated alerts, modal toggles, and security sanitization
 */

(function () {
    'use strict';

    /**
     * Show animated toast notifications
     * @param {string} message 
     * @param {string} type ('success' | 'error' | 'info')
     */
    window.showToast = function (message, type = 'success') {
        const icon = type === 'success' ? '✅' : (type === 'error' ? '⚠️' : 'ℹ️');
        const toast = $(`
            <div class="toast ${type}">
                <span style="font-size: 16px;">${icon}</span>
                <span>${window.escapeHtml(message)}</span>
            </div>
        `);

        let container = $('#toastContainer');
        if (!container.length) {
            container = $('<div class="toast-container" id="toastContainer"></div>').appendTo('body');
        }
        container.append(toast);

        setTimeout(() => {
            toast.fadeOut(300, function () { $(this).remove(); });
        }, 4000);
    };

    /**
     * Sanitize text against XSS injection
     * @param {string} text 
     * @returns {string}
     */
    window.escapeHtml = function (text) {
        if (!text) return '';
        return $('<div>').text(text).html();
    };

    /**
     * Modal helper functions
     */
    window.openModal = function (modalId) {
        $(`#${modalId}`).addClass('active');
        $('body').css('overflow', 'hidden');
    };

    window.closeModal = function (modalId) {
        $(`#${modalId}`).removeClass('active');
        $('body').css('overflow', '');
    };

    // Close modals on overlay or close button click
    $(document).on('click', '.modal-close-btn, .btn-close-modal', function () {
        const modal = $(this).closest('.modal-overlay');
        modal.removeClass('active');
        $('body').css('overflow', '');
    });

    $(document).on('click', '.modal-overlay', function (e) {
        if (e.target === this) {
            $(this).removeClass('active');
            $('body').css('overflow', '');
        }
    });

    // Close modal on Escape key
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            $('.modal-overlay.active').removeClass('active');
            $('body').css('overflow', '');
        }
    });

    // Check URL parameters for flash messages (e.g. from standard form redirects)
    $(document).ready(function () {
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg');
        if (msg === 'report_created') {
            window.showToast('Item report published successfully!', 'success');
            const cleanUrl = window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
        }
    });
})();
