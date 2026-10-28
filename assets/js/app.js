/**
 * Campus Lost & Found Portal - Main Application Coordinator
 * Boots up modular scripts depending on active page elements
 */

$(document).ready(function () {
    'use strict';

    // 1. Initialize Item Details Modal interactions
    if (window.ItemDetailsModule && typeof window.ItemDetailsModule.init === 'function') {
        window.ItemDetailsModule.init();
    }

    // 2. Initialize Claim Modal and submission handlers
    if (window.ClaimsModule && typeof window.ClaimsModule.init === 'function') {
        window.ClaimsModule.init();
    }

    // 3. Initialize Home Feed if on browse/index page
    if ($('#reportsContainer').length && window.ItemsFeedModule && typeof window.ItemsFeedModule.init === 'function') {
        window.ItemsFeedModule.init();
    }

    // 4. Initialize Founder Activity Dashboard if on my_reports page
    if ($('#myDashboardContainer').length && window.DashboardModule && typeof window.DashboardModule.init === 'function') {
        window.DashboardModule.init();
    }

    // 5. Initialize Report Publishing Form if on report page
    if ($('#newReportForm').length && window.ReportFormModule && typeof window.ReportFormModule.init === 'function') {
        window.ReportFormModule.init();
    }
});
