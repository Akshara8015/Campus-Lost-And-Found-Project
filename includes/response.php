<?php
/**
 * Standard JSON Response Utilities for AJAX Endpoints
 */

function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonSuccess(string $message = '', array $extra = [], int $statusCode = 200): void {
    jsonResponse(array_merge([
        'success' => true,
        'message' => $message,
    ], $extra), $statusCode);
}

function jsonError(string $message = 'An error occurred', int $statusCode = 400, array $extra = []): void {
    jsonResponse(array_merge([
        'success' => false,
        'message' => $message,
    ], $extra), $statusCode);
}
