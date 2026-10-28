<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
function out(int $c, array $d): void { http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

if (empty($_SESSION['student_id']) || !is_admin()) out(403, ['error' => 'Admins only.']);
$in  = json_decode(file_get_contents('php://input'), true) ?: [];
$act = $in['action'] ?? '';

try {
    $pdo = db();
    if ($act === 'toggle') {
        $new = attendance_open() ? '0' : '1';
        $pdo->prepare("REPLACE INTO site_settings (`name`, `value`) VALUES ('attendance_open', ?)")->execute([$new]);
        if ($new === '1') current_code(true);
        out(200, ['open' => $new === '1']);
    }
    if ($act === 'scan') {
        if (!attendance_open()) out(409, ['error' => 'Attendance is OFF. Turn it on first.']);
        $id = trim($in['id'] ?? '');
        if (!preg_match('/^\d{14}$/', $id)) out(422, ['error' => 'Invalid ID: ' . $id]);
        $u = $pdo->prepare('SELECT `name` FROM ' . USERS . ' WHERE `ID` = ?');
        $u->execute([$id]); $user = $u->fetch();
        if (!$user) out(404, ['error' => 'Student not found: ' . $id]);
        $ins = $pdo->prepare('INSERT IGNORE INTO attendance_log (student_id, att_date, att_time) VALUES (?, CURDATE(), CURTIME())');
        $ins->execute([$id]);
        out(200, ['name' => $user['name'], 'repeat' => $ins->rowCount() === 0]);
    }
    if ($act === 'new_code') { out(200, ['code' => attendance_open() ? current_code(true) : '']); }
    if ($act === 'today') {
        $rows = $pdo->query('SELECT l.att_time, l.student_id, u.`name` FROM attendance_log l
                             JOIN ' . USERS . ' u ON u.`ID` = l.student_id
                             WHERE l.att_date = CURDATE() ORDER BY l.att_time DESC')->fetchAll();
        $op = attendance_open();
        out(200, ['open' => $op, 'code' => $op ? current_code() : '', 'rows' => $rows]);
    }
    if ($act === 'admins') {
        $rows = $pdo->query('SELECT `name`, `ID`, `email` FROM ' . USERS . " WHERE `role` = 'admin' ORDER BY `name`")->fetchAll();
        out(200, ['rows' => $rows, 'me' => $_SESSION['student_id']]);
    }
    if ($act === 'add_admin') {
        $id = trim($in['id'] ?? '');
        if (!preg_match('/^\d{14}$/', $id)) out(422, ['error' => 'National ID must be exactly 14 digits.']);
        $c = $pdo->prepare('SELECT `name` FROM ' . USERS . ' WHERE `ID` = ?');
        $c->execute([$id]); $found = $c->fetch();
        if ($found) { // existing student: just promote
            $pdo->prepare('UPDATE ' . USERS . " SET `role` = 'admin' WHERE `ID` = ?")->execute([$id]);
            out(200, ['message' => $found['name'] . ' is now an admin.']);
        }
        $name = trim($in['name'] ?? ''); $email = trim($in['email'] ?? ''); $pw = (string)($in['password'] ?? '');
        if (mb_strlen($name) < 3) out(422, ['error' => 'New account: enter the full name.']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(422, ['error' => 'New account: enter a valid email.']);
        if (strlen($pw) < 8 || !preg_match('/\d/', $pw) || !preg_match('/[A-Za-z]/', $pw))
            out(422, ['error' => 'New account: password needs 8+ characters with letters and numbers.']);
        // LEARNING ONLY: plain text, same as register.php
        $pdo->prepare('INSERT INTO ' . USERS . " (`name`, `ID`, `Password`, `email`, `role`) VALUES (?, ?, ?, ?, 'admin')")
            ->execute([$name, $id, $pw, $email]);
        out(201, ['message' => 'Admin account created for ' . $name . '.']);
    }
    if ($act === 'remove_admin') {
        $id = trim($in['id'] ?? '');
        if ($id === $_SESSION['student_id']) out(409, ['error' => 'You cannot remove your own admin access.']);
        $pdo->prepare('UPDATE ' . USERS . " SET `role` = 'student' WHERE `ID` = ?")->execute([$id]);
        out(200, ['message' => 'Admin access removed.']);
    }
    out(400, ['error' => 'Unknown action.']);
} catch (PDOException $e) {
    out(500, ['error' => 'Database error: ' . $e->getMessage()]);
}
