<?php require __DIR__ . '/config.php'; require_login(true); ?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin | Thebes Technological University</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css"></head><body>
<?php topbar(); ?>
<main>
  <h1>Admin panel</h1>
  <p class="sub">Control attendance and scan student barcodes.</p>

  <div class="card row">
    <div><h2>Attendance</h2><span id="state" class="badge off">Loading...</span></div>
    <label class="switch" aria-label="Turn attendance on or off">
      <input type="checkbox" id="tgl"><span class="slider"></span>
    </label>
  </div>

  <div class="card center">
    <h2>Attendance barcode</h2>
    <p class="sub" style="margin:4px 0 14px">Show this on the screen or projector. Students scan it with their phones. It changes every minute.</p>
    <div id="codeBox"><span class="badge off">Turn attendance ON to show the barcode</span></div>
    <p style="margin-top:14px"><button class="btn" id="newCode" type="button">New code now</button></p>
  </div>

  <div class="card">
    <h2>Scan barcode</h2>
    <p class="sub" style="margin:0 0 10px">Click the box and scan with a barcode scanner (or type the ID and press Enter).</p>
    <input class="scan" id="scan" inputmode="numeric" autocomplete="off" placeholder="Scan student barcode here">
    <div id="msg" class="msg" role="status"></div>
  </div>

  <div class="card">
    <h2>Present today: <span id="count">0</span></h2>
    <table><thead><tr><th>Time</th><th>National ID</th><th>Name</th></tr></thead><tbody id="rows"></tbody></table>
  </div>
  <div class="card">
    <h2>Admin accounts</h2>
    <div class="tbl"><table><thead><tr><th>Name</th><th>National ID</th><th></th></tr></thead><tbody id="admins"></tbody></table></div>
    <h2 style="margin-top:22px">Add an admin</h2>
    <p class="sub" style="margin:0 0 10px">Enter the National ID. If that student already has an account, they become an admin. Otherwise fill in the other fields to create a new admin account.</p>
    <div class="form">
      <input class="fld" id="aid" inputmode="numeric" maxlength="14" autocomplete="off" placeholder="National ID (14 digits)">
      <input class="fld" id="aname" autocomplete="off" placeholder="Full name (new account)">
      <input class="fld" id="aemail" type="email" autocomplete="off" placeholder="Email (new account)">
      <input class="fld" id="apw" type="password" autocomplete="new-password" placeholder="Password (new account)">
    </div>
    <button class="btn" id="addAdmin" type="button">Add admin</button>
    <div id="amsg" class="msg" role="status"></div>
  </div>
</main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js"></script>
<script>
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  async function api(body) {
    const r = await fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    return { ok: r.ok, d: await r.json().catch(() => ({})) };
  }
  function say(t, c) { $('msg').textContent = t; $('msg').className = 'msg ' + c; }
  function showState(open) {
    $('tgl').checked = open;
    $('state').textContent = open ? 'ON - students can be scanned' : 'OFF - scanning is disabled';
    $('state').className = 'badge ' + (open ? 'on' : 'off');
  }
  let shownCode = null;
  function renderCode(open, code) {
    if (!open || !code) { shownCode = null; $('codeBox').innerHTML = '<span class="badge off">Turn attendance ON to show the barcode</span>'; return; }
    if (code === shownCode) return;
    shownCode = code;
    $('codeBox').innerHTML = '<div class="barwrap"><svg id="codeSvg"></svg></div><div class="idnum" style="margin-top:8px"></div>';
    $('codeBox').querySelector('.idnum').textContent = code;
    try { JsBarcode('#codeSvg', code, { format: 'CODE128', width: 3, height: 110, displayValue: false, margin: 0, lineColor: '#111' }); } catch (e) {}
  }
  $('newCode').addEventListener('click', async () => { const { ok, d } = await api({ action: 'new_code' }); if (ok) renderCode(true, d.code); });
  async function load() {
    const { ok, d } = await api({ action: 'today' });
    if (!ok) return;
    showState(d.open); renderCode(d.open, d.code);
    $('count').textContent = d.rows.length;
    $('rows').innerHTML = d.rows.map(r => `<tr><td>${esc(r.att_time)}</td><td>${esc(r.student_id)}</td><td>${esc(r.name)}</td></tr>`).join('')
      || '<tr><td colspan="3">No one yet today.</td></tr>';
  }
  $('tgl').addEventListener('change', async () => {
    const { ok, d } = await api({ action: 'toggle' });
    if (ok) { showState(d.open); load(); } else { $('tgl').checked = !$('tgl').checked; say(d.error || 'Could not change', 'error'); }
  });
  $('scan').addEventListener('keydown', async (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const id = e.target.value.replace(/\D/g, ''); e.target.value = '';
    if (!id) return;
    const { ok, d } = await api({ action: 'scan', id });
    if (ok) say(d.repeat ? d.name + ' was already marked today.' : d.name + ' marked present.', d.repeat ? 'error' : 'ok');
    else say(d.error || 'Scan failed', 'error');
    load(); $('scan').focus();
  });
  async function loadAdmins() {
    const { ok, d } = await api({ action: 'admins' });
    if (!ok) return;
    $('admins').innerHTML = d.rows.map(r => `<tr><td>${esc(r.name)}</td><td>${esc(r.ID)}</td><td>${
      r.ID === d.me ? '<small>(you)</small>' : `<button class="btn small" data-rm="${esc(r.ID)}">Remove</button>`}</td></tr>`).join('');
  }
  function asay(t, c) { $('amsg').textContent = t; $('amsg').className = 'msg ' + c; }
  $('aid').addEventListener('input', (e) => { e.target.value = e.target.value.replace(/\D/g, '').slice(0, 14); });
  $('addAdmin').addEventListener('click', async () => {
    const { ok, d } = await api({ action: 'add_admin', id: $('aid').value, name: $('aname').value, email: $('aemail').value, password: $('apw').value });
    asay(ok ? d.message : (d.error || 'Failed'), ok ? 'ok' : 'error');
    if (ok) { ['aid', 'aname', 'aemail', 'apw'].forEach(i => $(i).value = ''); loadAdmins(); }
  });
  $('admins').addEventListener('click', async (e) => {
    const id = e.target.dataset.rm; if (!id || !confirm('Remove admin access for ' + id + '?')) return;
    const { ok, d } = await api({ action: 'remove_admin', id });
    asay(ok ? d.message : (d.error || 'Failed'), ok ? 'ok' : 'error'); loadAdmins();
  });
  loadAdmins();
  load(); setInterval(load, 5000); $('scan').focus();
</script>
</body></html>
