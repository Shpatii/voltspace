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

include __DIR__ . '/../includes/header.php';
?>
<h1>Register</h1>
<?php if ($msg): ?><div class="alert ok"><?php echo h($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert warn"><?php echo h($err); ?></div><?php endif; ?>
<form method="post" class="card">
    <label>Name
        <input type="text" name="name" required value="<?php echo h($_POST['name'] ?? ''); ?>">
    </label>
    <label>Email
        <input type="email" name="email" required value="<?php echo h($_POST['email'] ?? ''); ?>">
    </label>
    <label>Password
        <input type="password" name="password" required>
    </label>
    <button type="submit">Create Account</button>
    <p>Already have an account? <a href="/voltspace/pages/login.php">Login</a></p>
    </form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
