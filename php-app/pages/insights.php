<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_login();
$db = get_db();
$user = current_user();

// Acknowledge
if (isset($_GET['ack'])) {
    $id = (int)$_GET['ack'];
    $stmt = $db->prepare('UPDATE insights SET acknowledged=1 WHERE id=? AND user_id=?');
    $stmt->bind_param('ii', $id, $user['id']);
    $stmt->execute();
    header('Location: ' . BASE_URL . '/pages/insights.php');
    exit;
}

$stmt = $db->prepare('SELECT * FROM insights WHERE user_id=? ORDER BY created_at DESC');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$insights = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>
<h1>Insights</h1>
<div class="insights-actions" style="display:flex;align-items:center;justify-content:space-between;margin:10px 0 12px;gap:12px">
  <div>
    <a class="btn" href="<?php echo h(BASE_URL); ?>/api/run_insights.php">Run AI Insights</a>
  </div>
  <form method="post" action="<?php echo h(BASE_URL); ?>/api/clear_insights.php" onsubmit="return confirm('Clear all insights?');" style="margin:0">
    <button type="submit" class="btn" style="padding:6px 10px;font-size:12px;background:#a33;color:#fff;border-color:#c66">Clear All</button>
  </form>
  
</div>
<table>
    <tr><th>Severity</th><th>Title</th><th>Detail</th><th>Created</th><th>Action</th></tr>
    <?php foreach ($insights as $i): ?>
        <tr>
            <td><span class="badge <?php echo h($i['severity']); ?>"><?php echo h(strtoupper($i['severity'])); ?></span></td>
            <td><?php echo h($i['title']); ?></td>
            <td><?php echo h($i['detail']); ?></td>
            <td><?php echo h(fmt_datetime($i['created_at'])); ?></td>
            <td>
                <?php if (!(int)$i['acknowledged']): ?>
                    <a class="btn" href="?ack=<?php echo (int)$i['id']; ?>">Acknowledge</a>
                <?php else: ?>
                    <span class="muted">Acknowledged</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php include __DIR__ . '/../includes/footer.php'; ?>
