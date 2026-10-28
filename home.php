<?php require __DIR__ . '/config.php'; require_login(); $open = attendance_open(); ?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Home | Thebes Technological University</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css"></head><body>
<?php topbar(); ?>
<main>
  <h1>Welcome, <?= e($_SESSION['student_name']) ?></h1>
  <p class="sub">National ID: <?= e($_SESSION['student_id']) ?></p>
  <div class="grid">
    <a class="card" href="attendance.php">
      <h2>Attendance</h2><p>Show your barcode to be marked present.</p>
      <p style="margin-top:10px"><span class="badge <?= $open ? 'on' : 'off' ?>"><?= $open ? 'Open now' : 'Closed' ?></span></p>
    </a>
    <?php if (is_admin()): ?>
    <a class="card" href="admin.php"><h2>Admin panel</h2><p>Turn attendance on or off and scan student barcodes.</p></a>
    <?php endif; ?>
    <a class="card" href="logout.php"><h2>Log out</h2><p>Sign out of your account.</p></a>
  </div>
</main></body></html>
