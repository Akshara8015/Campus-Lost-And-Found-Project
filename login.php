<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$redirect = $_GET['redirect'] ?? 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, full_name, email, password_hash, role FROM users WHERE LOWER(email) = LOWER(:email)");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                loginUser($user);
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (Throwable $e) {
            $error = 'Login error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Log In';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div style="font-size: 38px; margin-bottom: 12px;">🔐</div>
            <h1>Welcome Back</h1>
            <p>Log in to report items, search, or submit claims</p>
        </div>

        <?php if (!empty($error)): ?>
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 12px 16px; border-radius: var(--radius-md); font-size: 14px; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php?redirect=<?= urlencode($redirect) ?>">
            <div class="form-group">
                <label class="form-label" for="loginEmail">Email Address</label>
                <input type="email" name="email" id="loginEmail" class="form-control" placeholder="student@example.test" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="loginPassword">Password</label>
                <input type="password" name="password" id="loginPassword" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px;">Log In</button>
        </form>

        <!-- Quick Demo Credentials Fill -->
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-subtle); text-align: center;">
            <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 10px;">Quick fill seeded test accounts:</p>
            <div style="display: flex; gap: 8px; justify-content: center;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="fillDemo('student@example.test', 'password')">Student Demo</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="fillDemo('admin@example.test', 'password')">Admin Demo</button>
            </div>
        </div>

        <p style="text-align: center; margin-top: 24px; font-size: 14px; color: var(--text-secondary);">
            Don't have an account? <a href="register.php">Create one</a>
        </p>
    </div>
</div>

<script>
function fillDemo(email, pass) {
    document.getElementById('loginEmail').value = email;
    document.getElementById('loginPassword').value = pass;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
