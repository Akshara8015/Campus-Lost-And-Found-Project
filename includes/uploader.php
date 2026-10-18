<?php
/**
 * Secure Image Upload Helper
 * Campus Lost-and-Found Portal
 */

function handleImageUpload(string $fileInputName = 'image', int $maxSizeBytes = 5242880): ?string {
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fileInputName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("File upload failed with error code: " . $file['error']);
    }

    if ($file['size'] > $maxSizeBytes) {
        throw new RuntimeException("Image size exceeds the maximum limit of 5MB.");
    }

    // Verify MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!array_key_exists($mimeType, $allowedMimes)) {
        throw new RuntimeException("Invalid image format. Allowed formats: JPG, PNG, WEBP.");
    }

    $extension = $allowedMimes[$mimeType];
    $filename = sprintf('item_%d_%s.%s', time(), bin2hex(random_bytes(6)), $extension);
    
    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException("Failed to save the uploaded image.");
    }

    // Return relative URL path for web access and database storage
    return 'uploads/' . $filename;
}
