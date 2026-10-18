<?php
/**
 * Authentication and Session Management Service
 * Campus Lost-and-Found Portal
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is currently authenticated
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Return current logged-in user array or null
 */
function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Return current user ID or null
 */
function getCurrentUserId(): ?int {
    return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
}

/**
 * Check if the user is an admin
 */
function isAdmin(): bool {
    return isLoggedIn() && (($_SESSION['user']['role'] ?? '') === 'admin');
}

/**
 * Set user session on successful login
 */
function loginUser(array $user): void {
    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'role'      => $user['role'] ?? 'user',
    ];
}

/**
 * Destroy session on logout
 */
function logoutUser(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Require login for page requests (redirect) or AJAX requests (JSON 401)
 */
function requireLogin(bool $isAjax = false): void {
    if (!isLoggedIn()) {
        if ($isAjax) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Please log in to continue.', 'redirect' => 'login.php']);
            exit;
        }
        $redirectUrl = 'login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
        header("Location: $redirectUrl");
        exit;
    }
}

/**
 * CSRF Protection
 */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
