<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/uploader.php';
requireLogin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentUserId = getCurrentUserId();
    $itemName    = trim($_POST['item_name'] ?? '');
    $reportType  = strtolower(trim($_POST['report_type'] ?? ''));
    $category    = trim($_POST['category'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $itemDate    = trim($_POST['item_date'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Server-side validation
    if ($itemName === '' || strlen($itemName) > 160) {
        $error = 'Item name is required (max 160 characters).';
    } elseif (!in_array($reportType, ['lost', 'found'], true)) {
        $error = 'Report type must be either "lost" or "found".';
    } elseif ($category === '' || strlen($category) > 80) {
        $error = 'Category is required (max 80 characters).';
    } elseif ($location === '' || strlen($location) > 180) {
        $error = 'Location is required (max 180 characters).';
    } elseif ($itemDate === '' || !strtotime($itemDate)) {
        $error = 'A valid date is required.';
    } elseif ($description === '') {
        $error = 'Description is required.';
    } else {
        $imagePath = null;
        try {
            $imagePath = handleImageUpload('image');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        if (!$error) {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("
                    INSERT INTO reports (user_id, item_name, report_type, category, location, item_date, description, image_path, status)
                    VALUES (:user_id, :item_name, :report_type, :category, :location, :item_date, :description, :image_path, 'open')
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

                header('Location: index.php?msg=report_created');
                exit;
            } catch (Throwable $e) {
                $error = 'Failed to publish report: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Report Lost or Found Item';
require_once __DIR__ . '/includes/header.php';
?>

<main class="main-content">
    <div style="max-width: 680px; margin: 0 auto;">
        <div style="margin-bottom: 24px;">
            <a href="index.php" style="font-size: 14px; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; color: var(--text-secondary);">
                &larr; Back to Browse
            </a>
            <h1 style="font-size: 28px;">Report an Item</h1>
            <p style="color: var(--text-secondary); font-size: 14px;">Fill in the details below to help connect lost belongings with their owners.</p>
        </div>

        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); border-radius: var(--radius-md); padding: 12px 16px; color: #f87171; font-size: 14px; margin-bottom: 20px;">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="auth-card" style="max-width: 100%;">
            <form id="newReportForm" method="POST" action="report.php" enctype="multipart/form-data">
                <!-- Report Type Selection -->
                <div class="form-group">
                    <label class="form-label">What are you reporting?</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <label style="display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: var(--bg-primary); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); cursor: pointer;">
                            <input type="radio" name="report_type" value="lost" checked>
                            <div>
                                <strong style="display: block; font-size: 14px; color: #f87171;">Lost Item</strong>
                                <small style="color: var(--text-muted); font-size: 12px;">I lost something</small>
                            </div>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: var(--bg-primary); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); cursor: pointer;">
                            <input type="radio" name="report_type" value="found">
                            <div>
                                <strong style="display: block; font-size: 14px; color: #34d399;">Found Item</strong>
                                <small style="color: var(--text-muted); font-size: 12px;">I found something</small>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Item Name -->
                <div class="form-group">
                    <label class="form-label" for="itemName">Item Name *</label>
                    <input type="text" name="item_name" id="itemName" class="form-control" placeholder="e.g. Sony WH-1000XM4 Headphones, Blue HydroFlask" required>
                </div>

                <!-- Category & Date Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="categorySelect">Category *</label>
                        <select name="category" id="categorySelect" class="form-control" required>
                            <option value="">Select category...</option>
                            <option value="Electronics">Electronics</option>
                            <option value="ID Card">ID Cards & Documents</option>
                            <option value="Bottle">Water Bottles</option>
                            <option value="Bags">Bags & Backpacks</option>
                            <option value="Keys">Keys & Wallets</option>
                            <option value="Books">Books & Stationery</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="itemDate">Date Lost / Found *</label>
                        <input type="date" name="item_date" id="itemDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <!-- Location -->
                <div class="form-group">
                    <label class="form-label" for="locationInput">Campus Location *</label>
                    <input type="text" name="location" id="locationInput" class="form-control" placeholder="e.g. Main Library, 2nd Floor, Central Canteen" required>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label class="form-label" for="descriptionInput">Description & Identifying Details *</label>
                    <textarea name="description" id="descriptionInput" class="form-control" rows="4" 
                        placeholder="Provide details such as color, brand, distinct scratches, or any additional context..." required></textarea>
                </div>

                <!-- Photo Upload with Preview -->
                <div class="form-group">
                    <label class="form-label" for="imageFile">Item Photo (Optional, max 5MB)</label>
                    <input type="file" name="image" id="imageFile" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <div id="imagePreviewContainer" style="display: none; margin-top: 12px;">
                        <img id="imagePreview" src="#" alt="Preview" style="max-height: 160px; border-radius: var(--radius-md); border: 1px solid var(--border-subtle); object-fit: cover;">
                    </div>
                </div>

                <button type="submit" id="submitReportBtn" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 15px; margin-top: 12px;">
                    Publish Report
                </button>
            </form>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
