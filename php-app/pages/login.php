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

include __DIR__ . '/../includes/header.php';
?>
<h1>Login</h1>
<?php if ($error): ?><div class="alert warn"><?php echo h($error); ?></div><?php endif; ?>
<form method="post" class="card">
    <label>Email
        <input type="email" name="email" required value="<?php echo h($_POST['email'] ?? ''); ?>">
    </label>
    <label>Password
        <input type="password" name="password" required>
    </label>
    <button type="submit">Login</button>
    <p>No account? <a href="/voltspace/pages/register.php">Register</a></p>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
