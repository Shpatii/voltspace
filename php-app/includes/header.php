<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
$user = current_user();
$body_class = '';
if (!empty($page_class)) {
    $body_class = ' class="' . h(trim($page_class)) . '"';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(SITE_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo h(BASE_URL); ?>/public/styles.css">
</head>
<body<?php echo $body_class; ?>>
<header class="topbar">
    <div class="container">
        <div class="brand">⚡ <?php echo h(SITE_NAME); ?></div>
        <nav class="nav">
            <?php if ($user): ?>
            <a href="<?php echo h(BASE_URL); ?>/pages/dashboard.php">Dashboard</a>
            <a href="<?php echo h(BASE_URL); ?>/pages/homes.php">Homes</a>
            <a href="<?php echo h(BASE_URL); ?>/pages/rooms.php">Rooms</a>
            <a href="<?php echo h(BASE_URL); ?>/pages/devices.php">Devices</a>
            <a href="<?php echo h(BASE_URL); ?>/pages/insights.php">Insights</a>
            <a href="<?php echo h(BASE_URL); ?>/pages/assistant.php">Assistant</a>
            <a href="<?php echo h(BASE_URL); ?>/pages/floorplan_to_3d.php">Generate 3D Floor Plan</a>
            <span class="user"><?php echo h($user['email']); ?></span>
            <a class="logout" href="<?php echo h(BASE_URL); ?>/pages/login.php?logout=1">Logout</a>
            <?php else: ?>
            <a href="<?php echo h(BASE_URL); ?>/pages/login.php">Login</a>
            <a href="<?php echo h(BASE_URL); ?>/pages/register.php">Register</a>
            <?php endif; ?>
        </nav>
    </div>
    </header>
    <main class="container">
