<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$fullName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($fullName === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo = getDBConnection();
            // Check if email already registered
            $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(:email)");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $error = 'An account with this email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (full_name, email, password_hash, role)
                    VALUES (:full_name, :email, :password_hash, 'user')
                    RETURNING id, full_name, email, role
                ");
                $stmt->execute([
                    'full_name'     => $fullName,
                    'email'         => $email,
                    'password_hash' => $hash,
                ]);
                $newUser = $stmt->fetch();

                // Automatically log the user in
                loginUser($newUser);
                $_SESSION['flash_success'] = 'Account created successfully! Welcome to Campus Lost & Found.';
                header('Location: index.php');
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Registration error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Register Account';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div style="font-size: 38px; margin-bottom: 12px;">🎓</div>
            <h1>Create Account</h1>
            <p>Join the Campus Lost & Found portal</p>
        </div>

        <?php if (!empty($error)): ?>
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 12px 16px; border-radius: var(--radius-md); font-size: 14px; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="form-group">
                <label class="form-label" for="regName">Full Name</label>
                <input type="text" name="full_name" id="regName" class="form-control" placeholder="John Doe" value="<?= htmlspecialchars($fullName) ?>" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="regEmail">Campus Email</label>
                <input type="email" name="email" id="regEmail" class="form-control" placeholder="john.doe@campus.edu" value="<?= htmlspecialchars($email) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="regPass">Password (min 6 characters)</label>
                <input type="password" name="password" id="regPass" class="form-control" placeholder="••••••••" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="regConfirm">Confirm Password</label>
                <input type="password" name="confirm_password" id="regConfirm" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px;">Create Account</button>
        </form>

        <p style="text-align: center; margin-top: 24px; font-size: 14px; color: var(--text-secondary);">
            Already have an account? <a href="login.php">Log in</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
