<?php
require __DIR__ . '/config.php'; require_login();
$id = $_SESSION['student_id']; $open = attendance_open();
$f = db()->prepare('SELECT * FROM ' . USERS . ' WHERE `ID` = ?'); $f->execute([$id]); $me = $f->fetch() ?: [];
$q = db()->prepare('SELECT att_date, att_time FROM attendance_log WHERE student_id = ? ORDER BY att_date DESC LIMIT 10');
$q->execute([$id]); $rows = $q->fetchAll();
$t = db()->prepare('SELECT 1 FROM attendance_log WHERE student_id = ? AND att_date = CURDATE()');
$t->execute([$id]); $present = (bool)$t->fetch();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Attendance | Thebes Technological University</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css"></head><body>
<?php topbar(); ?>
<main>
  <h1>Attendance</h1>
  <p class="sub">Scan the attendance barcode with your phone. Each student is counted once per day.</p>
  <div class="card center">
    <?php if ($open): ?>
      <p><span class="badge on">Attendance is open</span>
      <?php if ($present): ?> <span class="badge on">You are marked present today</span><?php endif; ?></p>
      <?php if (!$present): ?>
      <p class="sub" style="margin:14px 0 10px">Scan the barcode shown by your instructor.</p>
      <button class="btn" id="startScan" type="button">Scan with camera</button>
      <div id="reader"></div>
      <p class="sub" style="margin:14px 0 8px">Or type the code printed under the barcode:</p>
      <input class="fld" id="manual" autocomplete="off" placeholder="e.g. TTU1A2B3C4D" style="max-width:260px;text-transform:uppercase">
      <button class="btn" id="sendCode" type="button">Send</button>
      <?php else: ?><p class="sub" style="margin:14px 0 0">You are all set for today.</p><?php endif; ?>
      <div id="smsg" class="msg" role="status"></div>
    <?php else: ?>
      <p><span class="badge off">Attendance is closed</span></p>
      <p class="sub" style="margin:12px 0 0">Your barcode will appear here when attendance is turned on.</p>
    <?php endif; ?>
  </div>
  <?php if ($open): ?><details class="card"><summary>Backup: my student card</summary><div class="idcard">
        <div class="idhead"><img src="ttu-logo.jpg" alt=""><div><b>Thebes Technological University</b><small>Student attendance card</small></div></div>
        <div class="idbody">
          <div class="idname"><?= e($_SESSION['student_name']) ?></div>
          <div class="idfac"><?= e($me['faculty'] ?? '') ?></div>
          <div class="barbox"><svg id="barcode"></svg></div>
          <div class="idnum"><?= e($id) ?></div>
        </div>
        <div class="idfoot"></div>
      </div></details><?php endif; ?>
  <div class="card">
    <h2>My last records</h2>
    <table><tr><th>Date</th><th>Time</th></tr>
    <?php foreach ($rows as $r): ?><tr><td><?= e($r['att_date']) ?></td><td><?= e($r['att_time']) ?></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="2">No records yet.</td></tr><?php endif; ?></table>
  </div>
</main>
<?php if ($open): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js"></script>
<script>
  try { JsBarcode('#barcode', <?= json_encode($id) ?>, { format: 'CODE128', width: 2.4, height: 80, displayValue: false, margin: 0, lineColor: '#111' }); }
  catch (e) { document.getElementById('barcode').outerHTML = '<p style="font-size:1.4rem;margin-top:14px"><?= e($id) ?></p>'; }
</script>
<?php endif; ?>
<?php if ($open): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
  const $ = (id) => document.getElementById(id);
  function say(t, c) { $('smsg').textContent = t; $('smsg').className = 'msg ' + c; }
  async function mark(code) {
    const r = await fetch('mark.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ code }) });
    const d = await r.json().catch(() => ({}));
    if (r.ok) { say(d.repeat ? 'You were already marked today.' : 'You are marked present.', 'ok'); setTimeout(() => location.reload(), 1500); }
    else say(d.error || 'Could not mark attendance.', 'error');
    return r.ok;
  }
  if ($('startScan')) {
    let qr;
    $('startScan').addEventListener('click', async () => {
      try {
        qr = new Html5Qrcode('reader', { formatsToSupport: [Html5QrcodeSupportedFormats.CODE_128, Html5QrcodeSupportedFormats.QR_CODE] });
        await qr.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 300, height: 110 } }, async (text) => {
          await qr.stop(); qr.clear(); await mark(text);
        });
      } catch (e) { say('Camera is not available here (it needs HTTPS). Type the code instead.', 'error'); }
    });
    $('sendCode').addEventListener('click', () => mark($('manual').value));
    $('manual').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); mark($('manual').value); } });
  }
</script>
<?php else: ?>
<script>setTimeout(() => location.reload(), 10000); // page opens itself when attendance turns on</script>
<?php endif; ?>
</body></html>
