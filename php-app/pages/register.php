<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';

if (current_user()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$msg = null; $err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (register_user($name, $email, $password, $err)) {
        $msg = 'Registration successful. You can now login.';
    }
}

$page_class = 'register-page';
include __DIR__ . '/../includes/header.php';
?>

<section class="auth-layout register-layout">
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
    <div class="auth-card">
        <div class="auth-card-header">
            <span class="auth-logo">⚡</span>
            <h1>Create your account</h1>
            <p class="auth-subtitle">Join VoltSpace to start managing your smart home energy usage.</p>
        </div>
        <?php if ($msg): ?><div class="alert ok auth-alert"><?php echo h($msg); ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert warn auth-alert"><?php echo h($err); ?></div><?php endif; ?>
        <form method="post" class="auth-form">
            <label>Full name
                <input type="text" name="name" placeholder="John Doe" required value="<?php echo h($_POST['name'] ?? ''); ?>">
            </label>
            <label>Email address
                <input type="email" name="email" placeholder="you@example.com" required value="<?php echo h($_POST['email'] ?? ''); ?>">
            </label>
            <label>Password
                <input type="password" name="password" placeholder="Create a strong password" required>
            </label>
            <button type="submit" class="primary-btn">Create Account</button>
        </form>
        <div class="auth-footer">
            <p>Already have an account? <a href="<?php echo h(BASE_URL); ?>/pages/login.php">Sign in</a></p>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
