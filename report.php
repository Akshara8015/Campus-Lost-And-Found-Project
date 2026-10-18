<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

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

        <div class="auth-card" style="max-width: 100%;">
            <form id="newReportForm" enctype="multipart/form-data">
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

<script>
$(document).ready(function () {
    // Image Preview
    $('#imageFile').on('change', function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                $('#imagePreview').attr('src', e.target.result);
                $('#imagePreviewContainer').slideDown(200);
            };
            reader.readAsDataURL(file);
        } else {
            $('#imagePreviewContainer').slideUp(200);
        }
    });

    // Form submission via AJAX
    $('#newReportForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#submitReportBtn');
        btn.prop('disabled', true).text('Publishing...');

        const formData = new FormData(this);

        $.ajax({
            url: 'api/reports.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).text('Publish Report');
                if (res.success) {
                    showToast('Report published successfully!');
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1000);
                } else {
                    showToast(res.message || 'Failed to submit report', 'error');
                }
            },
            error: function (xhr) {
                btn.prop('disabled', false).text('Publish Report');
                const err = xhr.responseJSON ? xhr.responseJSON.message : 'Error publishing report.';
                showToast(err, 'error');
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
