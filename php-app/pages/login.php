<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';

if (isset($_GET['logout'])) {
    logout();
    header('Location: ' . BASE_URL . '/pages/login.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (login_user($email, $password, $error)) {
        header('Location: ' . BASE_URL . '/pages/dashboard.php');
        exit;
    }
}

$page_class = 'login-page';
include __DIR__ . '/../includes/header.php';
?>

<section class="auth-layout">
    <div class="auth-card">
        <div class="auth-card-header">
            <span class="auth-logo">⚡</span>
            <h1>Welcome back</h1>
            <p class="auth-subtitle">Sign in to manage your smart spaces and keep an eye on live energy usage.</p>
        </div>
        <?php if ($error): ?><div class="alert warn auth-alert"><?php echo h($error); ?></div><?php endif; ?>
        <form method="post" class="auth-form">
            <label>Email address
                <input type="email" name="email" placeholder="you@example.com" required value="<?php echo h($_POST['email'] ?? ''); ?>">
            </label>
            <label>Password
                <input type="password" name="password" placeholder="Enter your password" required>
            </label>
            <div class="auth-meta">
                <label class="inline"><input type="checkbox" name="remember" disabled> Remember me (soon)</label>
                <a href="#" class="muted">Forgot password?</a>
            </div>
            <button type="submit" class="primary-btn">Login</button>
        </form>
        <div class="auth-footer">
            <p>No account? <a href="<?php echo h(BASE_URL); ?>/pages/register.php">Create one</a></p>
        </div>
    </div>
    <div class="auth-illustration">
        <div class="auth-illustration-inner">
            <div class="auth-window">
                <div class="pane"></div>
                <div class="pane"></div>
                <div class="pane"></div>
                <div class="pane"></div>
            </div>
            <div class="auth-character">
                <div class="head"></div>
                <div class="body"></div>
                <div class="laptop"></div>
            </div>
            <div class="auth-plant"></div>
            <div class="auth-lamp"></div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
