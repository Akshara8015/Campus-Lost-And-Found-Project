<?php
/**
 * Reports API Endpoint
 * Handles fetching, filtering, and creating lost/found item reports
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/uploader.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $search   = trim($_GET['q'] ?? '');
        $type     = trim($_GET['type'] ?? 'all');
        $category = trim($_GET['category'] ?? 'all');
        $status   = trim($_GET['status'] ?? 'all');
        $userId   = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        $currentUserId = getCurrentUserId();

        $query = "
            SELECT 
                r.id,
                r.user_id,
                r.item_name,
                r.report_type,
                r.category,
                r.location,
                r.item_date,
                r.description,
                r.image_path,
                r.status,
                r.created_at,
                u.full_name AS reporter_name,
                u.email AS reporter_email,
                (SELECT COUNT(*) FROM claims c WHERE c.report_id = r.id) AS claims_count,
                " . ($currentUserId ? "(SELECT c.status FROM claims c WHERE c.report_id = r.id AND c.claimant_id = :current_user_id LIMIT 1)" : "NULL") . " AS my_claim_status
            FROM reports r
            JOIN users u ON r.user_id = u.id
            WHERE 1=1
        ";

        $params = [];
        if ($currentUserId) {
            $params['current_user_id'] = $currentUserId;
        }

        if ($type !== 'all' && in_array($type, ['lost', 'found'], true)) {
            $query .= " AND r.report_type = :type";
            $params['type'] = $type;
        }

        if ($category !== 'all' && $category !== '') {
            $query .= " AND LOWER(r.category) = LOWER(:category)";
            $params['category'] = $category;
        }

        if ($status !== 'all' && in_array($status, ['open', 'claim_requested', 'resolved', 'closed'], true)) {
            $query .= " AND r.status = :status";
            $params['status'] = $status;
        }

        if ($userId) {
            $query .= " AND r.user_id = :user_id";
            $params['user_id'] = $userId;
        }

        if ($search !== '') {
            $query .= " AND (
                r.item_name ILIKE :search 
                OR r.description ILIKE :search 
                OR r.location ILIKE :search 
                OR r.category ILIKE :search
            )";
            $params['search'] = '%' . $search . '%';
        }

        $query .= " ORDER BY r.created_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $reports = $stmt->fetchAll();

        // Format dates and ownership flags
        foreach ($reports as &$item) {
            $item['is_owner'] = $currentUserId && ((int)$item['user_id'] === $currentUserId);
            $item['formatted_date'] = date('M d, Y', strtotime($item['item_date']));
            $item['relative_time'] = date('M d, Y', strtotime($item['created_at']));
            $item['claims_count'] = (int)$item['claims_count'];
        }

        jsonSuccess('Reports fetched successfully', [
            'reports' => $reports,
            'count'   => count($reports),
            'current_user_id' => $currentUserId,
        ]);
    } catch (Throwable $e) {
        jsonError('Failed to fetch reports: ' . $e->getMessage(), 500);
    }
}

if ($method === 'POST') {
    requireLogin(true);
    $currentUserId = getCurrentUserId();

    $itemName   = trim($_POST['item_name'] ?? '');
    $reportType = strtolower(trim($_POST['report_type'] ?? ''));
    $category   = trim($_POST['category'] ?? '');
    $location   = trim($_POST['location'] ?? '');
    $itemDate   = trim($_POST['item_date'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validation
    if ($itemName === '' || strlen($itemName) > 160) {
        jsonError('Item name is required (max 160 characters).');
    }
    if (!in_array($reportType, ['lost', 'found'], true)) {
        jsonError('Report type must be either "lost" or "found".');
    }
    if ($category === '' || strlen($category) > 80) {
        jsonError('Category is required (max 80 characters).');
    }
    if ($location === '' || strlen($location) > 180) {
        jsonError('Location is required (max 180 characters).');
    }
    if ($itemDate === '' || !strtotime($itemDate)) {
        jsonError('A valid date is required.');
    }
    if ($description === '') {
        jsonError('Description is required.');
    }

    $imagePath = null;
    try {
        $imagePath = handleImageUpload('image');
    } catch (Throwable $e) {
        jsonError($e->getMessage());
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO reports (user_id, item_name, report_type, category, location, item_date, description, image_path, status)
            VALUES (:user_id, :item_name, :report_type, :category, :location, :item_date, :description, :image_path, 'open')
            RETURNING id, created_at
        ");
        $stmt->execute([
            'user_id'     => $currentUserId,
            'item_name'   => $itemName,
            'report_type' => $reportType,
            'category'    => $category,
            'location'    => $location,
            'item_date'   => $itemDate,
            'description' => $description,
            'image_path'  => $imagePath,
        ]);

        $created = $stmt->fetch();

        jsonSuccess('Report submitted successfully!', [
            'report_id' => $created['id'],
            'status'    => 'open',
        ], 201);
    } catch (Throwable $e) {
        jsonError('Failed to create report: ' . $e->getMessage(), 500);
    }
}

jsonError('Method not allowed', 405);
