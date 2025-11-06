<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/utils.php';

function current_user(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    $db = get_db();
    $stmt = $db->prepare('SELECT id, name, email, password_hash, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    return $user ?: null;
}

function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/pages/login.php');
        exit;
    }
}

function logout(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function register_user(string $name, string $email, string $password, string &$error = null): bool {
    $db = get_db();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'Invalid email'; return false; }
    if (strlen($password) < 6) { $error = 'Password too short'; return false; }
    // Check exists
    $stmt = $db->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) { $error = 'Email already registered'; return false; }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO users(name, email, password_hash, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->bind_param('sss', $name, $email, $hash);
    return $stmt->execute();
}

function login_user(string $email, string $password, string &$error = null): bool {
    $db = get_db();
    $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!($row = $res->fetch_assoc())) { $error = 'Invalid credentials'; return false; }
    $stored = $row['password_hash'] ?? '';
    $ok = false;
    if ($stored && strlen($stored) > 0 && $stored[0] === '$') {
        // Modern hash (bcrypt/argon)
        $ok = password_verify($password, $stored);
        // Optionally rehash
        if ($ok && password_needs_rehash($stored, PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $db->prepare('UPDATE users SET password_hash=? WHERE id=?');
            $upd->bind_param('si', $newHash, $row['id']);
            $upd->execute();
        }
    } else {
        // Legacy SHA-256 support (used by seed.sql)
        $sha = hash('sha256', $password);
        $ok = hash_equals($sha, $stored);
        if ($ok) {
            // Migrate to password_hash
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $db->prepare('UPDATE users SET password_hash=? WHERE id=?');
            $upd->bind_param('si', $newHash, $row['id']);
            $upd->execute();
        }
    }
    if ($ok) {
        $_SESSION['user_id'] = (int)$row['id'];
        return true;
    }
    $error = 'Invalid credentials';
    return false;
}
?>
