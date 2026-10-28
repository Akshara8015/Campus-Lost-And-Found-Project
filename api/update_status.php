<?php
/**
 * Status Management API Endpoint
 * Handles claim approval (with auto-decline of competing claims) and status updates
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

requireLogin(true);
$currentUserId = getCurrentUserId();

$action = trim($_POST['action'] ?? '');

try {
    if ($action === 'approve_claim') {
        $claimId = isset($_POST['claim_id']) ? (int)$_POST['claim_id'] : 0;
        if ($claimId <= 0) {
            jsonError('Invalid claim ID.');
        }

        // Fetch claim and related report
        $stmt = $pdo->prepare("
            SELECT 
                c.id AS claim_id,
                c.status AS claim_status,
                r.id AS report_id,
                r.user_id AS report_owner_id,
                r.item_name,
                r.status AS report_status
            FROM claims c
            JOIN reports r ON c.report_id = r.id
            WHERE c.id = :claim_id
        ");
        $stmt->execute(['claim_id' => $claimId]);
        $data = $stmt->fetch();

        if (!$data) {
            jsonError('Claim not found.', 404);
        }

        // Verify permission: only report owner or admin can review claims
        if ((int)$data['report_owner_id'] !== $currentUserId && !isAdmin()) {
            jsonError('You do not have permission to review claims for this report.', 403);
        }

        $reportId = (int)$data['report_id'];

        // Transaction: Approve this claim, automatically decline all other pending claims, mark report resolved
        $pdo->beginTransaction();

        // 1. Approve selected claim
        $stmtApprove = $pdo->prepare("
            UPDATE claims 
            SET status = 'approved',
                reviewed_by = :reviewer_id,
                reviewed_at = CURRENT_TIMESTAMP
            WHERE id = :claim_id
        ");
        $stmtApprove->execute([
            'reviewer_id' => $currentUserId,
            'claim_id'    => $claimId,
        ]);

        // 2. Automatically decline / reject all other pending claims on this item
        $stmtRejectOthers = $pdo->prepare("
            UPDATE claims 
            SET status = 'rejected',
                reviewed_by = :reviewer_id,
                reviewed_at = CURRENT_TIMESTAMP
            WHERE report_id = :report_id 
              AND id != :claim_id 
              AND status = 'pending'
        ");
        $stmtRejectOthers->execute([
            'reviewer_id' => $currentUserId,
            'report_id'   => $reportId,
            'claim_id'    => $claimId,
        ]);
        $declinedCount = $stmtRejectOthers->rowCount();

        // 3. Mark the report as resolved
        $stmtReport = $pdo->prepare("
            UPDATE reports 
            SET status = 'resolved'
            WHERE id = :report_id
        ");
        $stmtReport->execute(['report_id' => $reportId]);

        $pdo->commit();

        jsonSuccess('Claim approved! The item is now marked as Resolved, and all other pending claims have been declined.', [
            'report_id'          => $reportId,
            'approved_claim_id'  => $claimId,
            'declined_others'    => $declinedCount,
            'new_report_status'  => 'resolved',
        ]);
    }

    if ($action === 'reject_claim') {
        $claimId = isset($_POST['claim_id']) ? (int)$_POST['claim_id'] : 0;
        if ($claimId <= 0) {
            jsonError('Invalid claim ID.');
        }

        $stmt = $pdo->prepare("
            SELECT 
                c.id AS claim_id,
                c.status AS claim_status,
                r.id AS report_id,
                r.user_id AS report_owner_id,
                r.status AS report_status
            FROM claims c
            JOIN reports r ON c.report_id = r.id
            WHERE c.id = :claim_id
        ");
        $stmt->execute(['claim_id' => $claimId]);
        $data = $stmt->fetch();

        if (!$data) {
            jsonError('Claim not found.', 404);
        }

        if ((int)$data['report_owner_id'] !== $currentUserId && !isAdmin()) {
            jsonError('You do not have permission to review claims for this report.', 403);
        }

        $reportId = (int)$data['report_id'];

        $pdo->beginTransaction();

        // Reject this claim
        $stmt = $pdo->prepare("
            UPDATE claims 
            SET status = 'rejected',
                reviewed_by = :reviewer_id,
                reviewed_at = CURRENT_TIMESTAMP
            WHERE id = :claim_id
        ");
        $stmt->execute([
            'reviewer_id' => $currentUserId,
            'claim_id'    => $claimId,
        ]);

        // Check if there are any remaining pending claims on this report
        $stmtPending = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE report_id = :report_id AND status = 'pending'");
        $stmtPending->execute(['report_id' => $reportId]);
        $remainingPending = (int)$stmtPending->fetchColumn();

        $newReportStatus = $data['report_status'];
        // If no pending claims remain and report was in 'claim_requested', revert to 'open'
        if ($remainingPending === 0 && $data['report_status'] === 'claim_requested') {
            $stmtRevert = $pdo->prepare("UPDATE reports SET status = 'open' WHERE id = :report_id");
            $stmtRevert->execute(['report_id' => $reportId]);
            $newReportStatus = 'open';
        }

        $pdo->commit();

        jsonSuccess('Claim rejected.', [
            'report_id'          => $reportId,
            'rejected_claim_id'  => $claimId,
            'remaining_pending'  => $remainingPending,
            'new_report_status'  => $newReportStatus,
        ]);
    }

    if ($action === 'update_report_status') {
        $reportId = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
        $newStatus = trim($_POST['status'] ?? '');

        if (!in_array($newStatus, ['open', 'claim_requested', 'resolved', 'closed'], true)) {
            jsonError('Invalid status value.');
        }

        $stmt = $pdo->prepare("SELECT id, user_id FROM reports WHERE id = :id");
        $stmt->execute(['id' => $reportId]);
        $report = $stmt->fetch();

        if (!$report) {
            jsonError('Report not found.', 404);
        }

        if ((int)$report['user_id'] !== $currentUserId && !isAdmin()) {
            jsonError('You do not have permission to modify this report.', 403);
        }

        $stmt = $pdo->prepare("UPDATE reports SET status = :status WHERE id = :id");
        $stmt->execute([
            'status' => $newStatus,
            'id'     => $reportId,
        ]);

        jsonSuccess('Report status updated to ' . ucfirst(str_replace('_', ' ', $newStatus)), [
            'report_id'   => $reportId,
            'new_status'  => $newStatus,
        ]);
    }

    if ($action === 'delete_report') {
        $reportId = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;

        if ($reportId <= 0) {
            jsonError('Invalid report ID.');
        }

        $stmt = $pdo->prepare("SELECT id, user_id, item_name, image_path FROM reports WHERE id = :id");
        $stmt->execute(['id' => $reportId]);
        $report = $stmt->fetch();

        if (!$report) {
            jsonError('Report not found.', 404);
        }

        if ((int)$report['user_id'] !== $currentUserId && !isAdmin()) {
            jsonError('You do not have permission to delete this report.', 403);
        }

        $pdo->beginTransaction();

        // 1. Delete associated claims first
        $stmtClaims = $pdo->prepare("DELETE FROM claims WHERE report_id = :report_id");
        $stmtClaims->execute(['report_id' => $reportId]);

        // 2. Delete report
        $stmtDel = $pdo->prepare("DELETE FROM reports WHERE id = :report_id");
        $stmtDel->execute(['report_id' => $reportId]);

        $pdo->commit();

        // Remove uploaded image file if exists
        if (!empty($report['image_path'])) {
            $imageFullPath = __DIR__ . '/../' . ltrim($report['image_path'], '/\\');
            if (file_exists($imageFullPath)) {
                @unlink($imageFullPath);
            }
        }

        jsonSuccess('Report deleted successfully.', [
            'deleted_report_id' => $reportId,
            'item_name'         => $report['item_name']
        ]);
    }

    jsonError('Invalid action specified.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Status operation failed: ' . $e->getMessage(), 500);
}
