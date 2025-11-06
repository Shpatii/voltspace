<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_login();
$db = get_db();
$user = current_user();

// Add
$msg = null; $err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    if ($name === '') { $err = 'Name is required'; }
    if (!$err) {
        $stmt = $db->prepare('INSERT INTO homes(user_id, name, address, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->bind_param('iss', $user['id'], $name, $address);
        if ($stmt->execute()) { $msg = 'Home added.'; } else { $err = 'Failed to add home'; }
    }
}

// List
$stmt = $db->prepare('SELECT id, name, address, created_at FROM homes WHERE user_id=? ORDER BY created_at DESC');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$homes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>
<h1>Homes</h1>
<?php if ($msg): ?><div class="alert ok"><?php echo h($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert warn"><?php echo h($err); ?></div><?php endif; ?>

<div class="grid two">
    <section class="card">
        <h2>Your Homes</h2>
        <table>
            <tr><th>Name</th><th>Address</th><th>Created</th></tr>
            <?php foreach ($homes as $h): ?>
                <tr>
                    <td><?php echo h($h['name']); ?></td>
                    <td><?php echo h($h['address']); ?></td>
                    <td><?php echo h(fmt_datetime($h['created_at'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </section>
    <section class="card">
        <h2>Add Home</h2>
        <form method="post">
            <label>Name
                <input type="text" name="name" required>
            </label>
            <label>Address
                <input type="text" name="address">
            </label>
            <button type="submit">Add Home</button>
        </form>
    </section>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

