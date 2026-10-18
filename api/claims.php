<?php
/**
 * Claims API Endpoint
 * Handles claim submission, retrieval of claims for an item, and user claims
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// All claim operations require an authenticated user
requireLogin(true);
$currentUserId = getCurrentUserId();

if ($method === 'GET') {
    try {
        $reportId = isset($_GET['report_id']) ? (int)$_GET['report_id'] : null;
        $view = trim($_GET['view'] ?? '');

        // View claims made BY current user
        if ($view === 'my_claims') {
            $stmt = $pdo->prepare("
                SELECT 
                    c.id AS claim_id,
                    c.message,
                    c.status AS claim_status,
                    c.created_at AS claim_date,
                    c.reviewed_at,
                    r.id AS report_id,
                    r.item_name,
                    r.category,
                    r.location,
                    r.item_date,
                    r.image_path,
                    r.status AS report_status,
                    u.full_name AS finder_name,
                    u.email AS finder_email
                FROM claims c
                JOIN reports r ON c.report_id = r.id
                JOIN users u ON r.user_id = u.id
                WHERE c.claimant_id = :claimant_id
                ORDER BY c.created_at DESC
            ");
            $stmt->execute(['claimant_id' => $currentUserId]);
            $claims = $stmt->fetchAll();

            foreach ($claims as &$c) {
                $c['claim_date_formatted'] = date('M d, Y h:i A', strtotime($c['claim_date']));
            }

            jsonSuccess('My claims retrieved', ['claims' => $claims]);
        }

        // View claims on a specific report (only if current user owns the report or is admin)
        if ($reportId) {
            $stmt = $pdo->prepare("SELECT user_id, item_name, status FROM reports WHERE id = :report_id");
            $stmt->execute(['report_id' => $reportId]);
            $report = $stmt->fetch();

            if (!$report) {
                jsonError('Report not found', 404);
            }

            if ((int)$report['user_id'] !== $currentUserId && !isAdmin()) {
                jsonError('Unauthorized to view claims for this report', 403);
            }

            $stmt = $pdo->prepare("
                SELECT 
                    c.id,
                    c.message,
                    c.status,
                    c.created_at,
                    c.reviewed_at,
                    u.id AS claimant_id,
                    u.full_name AS claimant_name,
                    u.email AS claimant_email
                FROM claims c
                JOIN users u ON c.claimant_id = u.id
                WHERE c.report_id = :report_id
                ORDER BY c.created_at ASC
            ");
            $stmt->execute(['report_id' => $reportId]);
            $claims = $stmt->fetchAll();

            foreach ($claims as &$c) {
                $c['formatted_date'] = date('M d, Y h:i A', strtotime($c['created_at']));
            }

            jsonSuccess('Claims retrieved', [
                'report' => $report,
                'claims' => $claims,
            ]);
        }

        jsonError('Invalid request parameters');
    } catch (Throwable $e) {
        jsonError('Failed to fetch claims: ' . $e->getMessage(), 500);
    }
}

if ($method === 'POST') {
    $reportId = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
    $message  = trim($_POST['message'] ?? '');

    if ($reportId <= 0) {
        jsonError('Invalid report ID.');
    }
    if ($message === '') {
        jsonError('Please provide details or proof of ownership to claim this item.');
    }

    try {
        // Fetch report and verify permissions
        $stmt = $pdo->prepare("SELECT id, user_id, report_type, status FROM reports WHERE id = :id");
        $stmt->execute(['id' => $reportId]);
        $report = $stmt->fetch();

        if (!$report) {
            jsonError('Report not found.', 404);
        }
        if ($report['report_type'] !== 'found') {
            jsonError('Claims can only be submitted on Found items.');
        }
        if ((int)$report['user_id'] === $currentUserId) {
            jsonError('You cannot claim an item you reported yourself.');
        }
        if (in_array($report['status'], ['resolved', 'closed'], true)) {
            jsonError('This item report has already been resolved or closed.');
        }

        // Check if user already submitted a claim
        $stmt = $pdo->prepare("SELECT id, status FROM claims WHERE report_id = :report_id AND claimant_id = :claimant_id");
        $stmt->execute([
            'report_id'   => $reportId,
            'claimant_id' => $currentUserId,
        ]);
        $existing = $stmt->fetch();
        if ($existing) {
            jsonError('You have already submitted a claim for this item (Status: ' . htmlspecialchars($existing['status']) . ').');
        }

        // Insert claim & update report status to claim_requested
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO claims (report_id, claimant_id, message, status)
            VALUES (:report_id, :claimant_id, :message, 'pending')
            RETURNING id, created_at
        ");
        $stmt->execute([
            'report_id'   => $reportId,
            'claimant_id' => $currentUserId,
            'message'     => $message,
        ]);
        $newClaim = $stmt->fetch();

        // Update report status to claim_requested if currently open
        if ($report['status'] === 'open') {
            $stmt = $pdo->prepare("UPDATE reports SET status = 'claim_requested' WHERE id = :id");
            $stmt->execute(['id' => $reportId]);
        }

        $pdo->commit();

        jsonSuccess('Claim submitted successfully! The founder has been notified to review your claim.', [
            'claim_id'      => $newClaim['id'],
            'report_status' => 'claim_requested',
        ], 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonError('Failed to submit claim: ' . $e->getMessage(), 500);
    }
}

jsonError('Method not allowed', 405);
