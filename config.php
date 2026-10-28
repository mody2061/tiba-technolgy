<?php
// Same MySQL settings as register.php and login.php
const DB_HOST = '127.0.0.1';
const DB_NAME = 'mysql';
const DB_USER = 'root';
const DB_PASS = '';
const USERS   = '`project data`';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

function db(): PDO {
    static $pdo;
    return $pdo ??= new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
// Admin status is checked in the database on every request, so removing an admin takes effect immediately.
function is_admin(): bool {
    static $cache;
    if (empty($_SESSION['student_id'])) return false;
    if ($cache === null) {
        try {
            $q = db()->prepare('SELECT `role` FROM ' . USERS . ' WHERE `ID` = ?');
            $q->execute([$_SESSION['student_id']]);
            $cache = ($q->fetchColumn() === 'admin');
        } catch (PDOException $e) { $cache = false; }
        $_SESSION['role'] = $cache ? 'admin' : 'student';
    }
    return $cache;
}
function require_login(bool $admin = false): void {
    header('Cache-Control: no-store, no-cache, must-revalidate'); // back button must not show private pages
    if (empty($_SESSION['student_id'])) { header('Location: login.html'); exit; }
    if ($admin && !is_admin()) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Access denied</title><link rel="stylesheet" href="style.css"></head><body><main class="center"><h1>Access denied</h1><p class="sub">This page is for admin accounts only.</p><a class="btn" href="home.php" style="text-decoration:none;display:inline-block">Back to home</a></main></body></html>';
        exit;
    }
}
function attendance_open(): bool {
    $r = db()->query("SELECT `value` FROM site_settings WHERE `name`='attendance_open'")->fetch();
    return $r && $r['value'] === '1';
}
function setting(string $n): string {
    $q = db()->prepare('SELECT `value` FROM site_settings WHERE `name` = ?'); $q->execute([$n]);
    return (string)($q->fetchColumn() ?: '');
}
function set_setting(string $n, string $v): void {
    db()->prepare('REPLACE INTO site_settings (`name`, `value`) VALUES (?, ?)')->execute([$n, $v]);
}
// The primary barcode text. Changes every 60 seconds (the previous one stays valid until the next change).
function current_code(bool $force = false): string {
    $code = setting('attendance_code');
    if ($force || $code === '' || time() - (int)setting('attendance_code_at') > 60) {
        set_setting('attendance_prev', $code);
        $code = 'TTU' . strtoupper(bin2hex(random_bytes(4)));
        set_setting('attendance_code', $code);
        set_setting('attendance_code_at', (string)time());
    }
    return $code;
}
function topbar(): void { ?>
<header class="top">
  <div class="brand"><img src="ttu-logo.jpg" alt="TTU"><span>Thebes Technological University</span></div>
  <nav>
    <a href="home.php">Home</a>
    <a href="attendance.php">Attendance</a>
    <?php if (is_admin()): ?><a href="admin.php">Admin</a><?php endif; ?>
    <a href="logout.php">Log out</a>
  </nav>
</header>
<?php }
