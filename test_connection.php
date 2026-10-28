<?php
require_once __DIR__ . '/config/db.php';

$php_version = phpversion();
$pdo_pgsql_loaded = extension_loaded('pdo_pgsql');
$pgsql_loaded = extension_loaded('pgsql');
$db_connected = false;
$error_msg = '';
$tables = [];
$users_count = 0;
$reports_count = 0;

if ($pdo_pgsql_loaded) {
    try {
        $pdo = getDBConnection();
        $db_connected = true;

        // Query created tables
        $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Check counts if tables exist
        if (in_array('users', $tables)) {
            $users_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        }
        if (in_array('reports', $tables)) {
            $reports_count = $pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
        }
    } catch (Throwable $e) {
        $error_msg = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PostgreSQL & XAMPP Connection Check</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        body { background: #0f172a; color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 32px; max-width: 640px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4); }
        h1 { font-size: 24px; font-weight: 700; margin-bottom: 8px; color: #38bdf8; display: flex; align-items: center; gap: 10px; }
        p.subtitle { color: #94a3b8; font-size: 14px; margin-bottom: 24px; }
        .check-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; background: #0f172a; border-radius: 10px; margin-bottom: 12px; border: 1px solid #334155; }
        .check-left { display: flex; flex-direction: column; gap: 4px; }
        .check-title { font-weight: 600; font-size: 15px; color: #f1f5f9; }
        .check-detail { font-size: 13px; color: #94a3b8; }
        .badge { padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-success { background: #065f46; color: #34d399; border: 1px solid #059669; }
        .badge-fail { background: #7f1d1d; color: #f87171; border: 1px solid #dc2626; }
        .alert-box { margin-top: 20px; padding: 16px; border-radius: 10px; font-size: 14px; line-height: 1.5; }
        .alert-error { background: #450a0a; border: 1px solid #b91c1c; color: #fca5a5; }
        .alert-success { background: #064e3b; border: 1px solid #059669; color: #6ee7b7; }
        .code-snippet { background: #020617; border-radius: 6px; padding: 8px 12px; font-family: monospace; font-size: 13px; color: #38bdf8; margin-top: 6px; word-break: break-all; }
        .tables-list { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
        .table-chip { background: #334155; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-family: monospace; color: #e2e8f0; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🐘 PostgreSQL Connection Diagnostic</h1>
        <p class="subtitle">Campus Lost-and-Found Portal environment status check</p>

        <div class="check-item">
            <div class="check-left">
                <span class="check-title">Apache Web Server & PHP</span>
                <span class="check-detail">PHP Version: <?= htmlspecialchars($php_version) ?></span>
            </div>
            <span class="badge badge-success">Active</span>
        </div>

        <div class="check-item">
            <div class="check-left">
                <span class="check-title">PHP PostgreSQL Extensions</span>
                <span class="check-detail">pdo_pgsql & pgsql modules</span>
            </div>
            <?php if ($pdo_pgsql_loaded): ?>
                <span class="badge badge-success">Enabled</span>
            <?php else: ?>
                <span class="badge badge-fail">Missing</span>
            <?php endif; ?>
        </div>

        <div class="check-item">
            <div class="check-left">
                <span class="check-title">PostgreSQL Database Connection</span>
                <span class="check-detail">Host: <?= DB_HOST ?>:<?= DB_PORT ?> | DB: <?= DB_NAME ?> | User: <?= DB_USER ?></span>
            </div>
            <?php if ($db_connected): ?>
                <span class="badge badge-success">Connected</span>
            <?php else: ?>
                <span class="badge badge-fail">Disconnected</span>
            <?php endif; ?>
        </div>

        <?php if ($db_connected): ?>
            <div class="alert-box alert-success">
                <strong>Connection Successful!</strong><br>
                Database <code><?= htmlspecialchars(DB_NAME) ?></code> is reachable via PDO.
                <?php if (!empty($tables)): ?>
                    <div style="margin-top: 10px;">
                        Found <strong><?= count($tables) ?></strong> tables:
                        <div class="tables-list">
                            <?php foreach ($tables as $t): ?>
                                <span class="table-chip"><?= htmlspecialchars($t) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div style="margin-top: 10px; font-size: 13px;">
                        Demo Records: <strong><?= $users_count ?></strong> users, <strong><?= $reports_count ?></strong> reports.
                    </div>
                <?php else: ?>
                    <p style="margin-top: 8px;">No tables found yet. Please run <code>schema.sql</code>, <code>indexes.sql</code>, and <code>seed.sql</code>.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert-box alert-error">
                <strong>Connection Error:</strong><br>
                <?= htmlspecialchars($error_msg) ?>
                <p style="margin-top: 10px;">
                    To fix this, open <span class="code-snippet">.env</span> in the root directory and ensure <code>DB_PASS</code> matches the password you chose during PostgreSQL installation.
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
