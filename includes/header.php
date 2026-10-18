<?php
/**
 * Global Header Component
 * Campus Lost-and-Found Portal
 */
require_once __DIR__ . '/auth.php';
$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — Campus Lost & Found' : 'Campus Lost-and-Found Portal' ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/jquery.min.js"></script>
</head>
<body>

<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="brand">
            <div class="brand-icon">📦</div>
            <span>Campus <span class="accent">Lost & Found</span></span>
        </a>

        <ul class="nav-links">
            <li><a href="index.php" class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">Browse Items</a></li>
            <?php if (isLoggedIn()): ?>
                <li><a href="report.php" class="nav-link <?= $currentPage === 'report.php' ? 'active' : '' ?>">Report Lost/Found</a></li>
                <li><a href="my_reports.php" class="nav-link <?= $currentPage === 'my_reports.php' ? 'active' : '' ?>">My Dashboard</a></li>
            <?php endif; ?>
        </ul>

        <div class="nav-user">
            <?php if (isLoggedIn()): ?>
                <span class="user-greeting">Hi, <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong></span>
                <a href="logout.php" class="btn btn-secondary btn-sm">Log Out</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-secondary btn-sm">Log In</a>
                <a href="register.php" class="btn btn-primary btn-sm">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="toast-container" id="toastContainer"></div>
