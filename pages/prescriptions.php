<?php
// pages/prescriptions.php

// ── Risk auto-detection helper ───────────────────────────────
function autoCreateRisk(string $rxId, int $custId, int $medId, string $type, string $level, string $desc): void {
    $exists = fetchOne("SELECT id FROM risks WHERE rx_id=? AND type=? AND resolved=0", 'ss', $rxId, $type);
    if (!$exists) {
        $rId = 'R-' . str_pad(((int)substr(fetchOne("SELECT id FROM risks ORDER BY id DESC LIMIT 1")['id'] ?? 'R-000', 2)) + 1, 3, '0', STR_PAD_LEFT);
        execute(
            "INSERT INTO risks (id,type,level,cust_id,med_id,rx_id,description,risk_date,resolved) VALUES (?,?,?,?,?,?,?,CURDATE(),0)",
            'sssiiiss',
            $rId, $type, $level, $custId, $medId, $rxId, $desc
        );
    }
}

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'add_rx') {
        $rxId     = nextRxId();
        $custId   = (int)$_POST['cust_id'];
        $medId    = (int)$_POST['med_id'];
        $qty      = (int)$_POST['qty'];
        $unit     = $_POST['unit'];
        $presciber= trim($_POST['prescriber']);
        $rxDate   = $_POST['rx_date'];
        $notes    = trim($_POST['notes']);
        $status   = 'Pending';

        // Check id_check
        $med  = fetchOne("SELECT * FROM medicines WHERE id=?", 'i', $medId);
        $cust = fetchOne("SELECT * FROM customers WHERE id=?", 'i', $custId);

        $warnings = [];
        if ($med['id_check']) {
            $status = 'Alert';
            $warnings[] = "ID check required for {$med['name']}";
        }
        // Allergy check – simple keyword match
        $allergyList = strtolower($cust['allergies'] ?? '');
        $medName     = strtolower($med['name']);
        if ($allergyList !== 'none' && $allergyList) {
            $allergens = array_map('trim', explode(',', $allergyList));
            foreach ($allergens as $al) {
                if ($al && strpos($medName, $al) !== false) {
                    $status = 'Alert';
                    $warnings[] = "⚠️ Patient allergic to {$al} — {$med['name']} prescribed";
                }
            }
            // Penicillin-class check
            if (in_array('penicillin', $allergens) && (stripos($med['name'],'amoxicillin')!==false || stripos($med['name'],'ampicillin')!==false)) {
                $status = 'Alert';
                $warnings[] = "⚠️ Penicillin allergy — {$med['name']} is penicillin-based!";
            }
        }
        // Low stock check
        if ($med['qty'] < LOW_STOCK_THRESHOLD) {
            $warnings[] = "⚠️ Low stock: only {$med['qty']} {$med['unit']} remaining";
        }

        if ($warnings) $notes = implode('; ', $warnings) . ($notes ? '; '.$notes : '');

        execute(
            "INSERT INTO prescriptions (id,cust_id,med_id,qty,unit,rx_date,prescriber,status,notes) VALUES (?,?,?,?,?,?,?,?,?)",
            'siiiissss',
            $rxId, $custId, $medId, $qty, $unit, $rxDate, $presciber, $status, $notes
        );
        // Deduct stock
        execute("UPDATE medicines SET qty=qty-? WHERE id=?", 'ii', $qty, $medId);
        // Update customer last_visit
        execute("UPDATE customers SET last_visit=? WHERE id=?", 'si', $rxDate, $custId);

        // Auto-create risks
        if ($med['id_check']) {
            autoCreateRisk($rxId, $custId, $medId, 'ID Check', 'red', "{$med['name']} requires mandatory ID verification.");
        }
        foreach ($warnings as $w) {
            if (stripos($w,'allerg')!==false) {
                autoCreateRisk($rxId, $custId, $medId, 'Allergy Conflict', 'red', $w);
            }
        }
        if ($med['qty'] < LOW_STOCK_THRESHOLD) {
            $newQty = $med['qty'] - $qty;
            autoCreateRisk($rxId, $custId, $medId, 'Low Stock', 'amber', "{$med['name']} stock now at {$newQty} {$med['unit']}. Reorder needed.");
        }

        header('Location: app.php?page=prescriptions&toast='.urlencode("Prescription $rxId created").'&toast_type=success');
        exit;
    }

    if ($act === 'update_status') {
        execute("UPDATE prescriptions SET status=? WHERE id=?", 'ss', $_POST['status'], $_POST['id']);
        header('Location: app.php?page=prescriptions&toast='.urlencode('Status updated').'&toast_type=success');
        exit;
    }

    if ($act === 'delete_rx') {
        execute("DELETE FROM prescriptions WHERE id=?", 's', $_POST['id']);
        header('Location: app.php?page=prescriptions&toast='.urlencode('Prescription deleted').'&toast_type=success');
        exit;
    }
}

// ── List query ────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$whereStatus = match($filter) {
    'pending'   => "WHERE p.status='Pending'",
    'completed' => "WHERE p.status='Completed'",
    'alert'     => "WHERE p.status='Alert'",
    default     => '',
};
$searchWhere = '';
$binds = [];
if ($q) {
    $like = "%$q%";
    $searchWhere = ($whereStatus ? ' AND ' : ' WHERE ') .
        "(CONCAT(c.first_name,' ',c.last_name) LIKE ? OR m.name LIKE ? OR p.id LIKE ? OR p.prescriber LIKE ?)";
    $binds = [$like,$like,$like,$like];
}
$sql = "SELECT p.*, CONCAT(c.first_name,' ',c.last_name) AS patient, m.name AS medicine, m.unit AS med_unit
        FROM prescriptions p
        JOIN customers c ON c.id=p.cust_id
        JOIN medicines  m ON m.id=p.med_id
        $whereStatus $searchWhere
        ORDER BY p.rx_date DESC, p.id DESC";
$rxList = $binds ? fetchAll($sql, str_repeat('s', count($binds)), ...$binds) : fetchAll($sql);

// view modal
$viewRx = null;
if (isset($_GET['view'])) {
    $viewRx = fetchOne("SELECT p.*, CONCAT(c.first_name,' ',c.last_name) AS patient, c.allergies, c.nhs_number, c.gp_name,
                               m.name AS medicine, m.id_check
                        FROM prescriptions p
                        JOIN customers c ON c.id=p.cust_id
                        JOIN medicines  m ON m.id=p.med_id
                        WHERE p.id=?", 's', $_GET['view']);
}

// Build customer/medicine selects
$allCusts = fetchAll("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM customers WHERE status='Active' ORDER BY last_name");
$allMeds  = fetchAll("SELECT id, name, unit, qty, id_check FROM medicines ORDER BY name");
$showAdd  = isset($_GET['modal']) && $_GET['modal']==='add';
?>

<div class="filters">
  <?php foreach(['all'=>'All','pending'=>'Pending','completed'=>'Completed','alert'=>'Alert ⚠️'] as $k=>$v): ?>
  <a class="filter-btn <?= $filter===$k?'active':'' ?>" href="app.php?page=prescriptions&filter=<?= $k ?>"><?= $v ?></a>
  <?php endforeach; ?>
  <span style="margin-left:auto;font-size:13px;color:var(--text-muted)"><?= count($rxList) ?> result(s)</span>
</div>

<div class="card">
  <table>
    <thead><tr><th>Rx ID</th><th>Patient</th><th>Medicine</th><th>Qty</th><th>Date</th><th>Prescriber</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if ($rxList): ?>
    <?php foreach ($rxList as $rx): ?>
    <tr>
      <td><strong><?= htmlspecialchars($rx['id']) ?></strong></td>
      <td><?= htmlspecialchars($rx['patient']) ?></td>
      <td><?= htmlspecialchars($rx['medicine']) ?></td>
      <td><?= $rx['qty'] ?> <?= htmlspecialchars($rx['med_unit']) ?></td>
      <td><?= date('d M Y', strtotime($rx['rx_date'])) ?></td>
      <td><?= htmlspecialchars($rx['prescriber']) ?></td>
      <td><?php
        $cls = match($rx['status']) {'Completed'=>'badge-green','Pending'=>'badge-amber','Alert'=>'badge-red',default=>'badge-gray'};
        echo "<span class='badge $cls'>{$rx['status']}</span>";
      ?></td>
      <td style="display:flex;gap:4px;flex-wrap:wrap">
        <a href="app.php?page=prescriptions&view=<?= urlencode($rx['id']) ?>" class="btn-sm btn-view">View</a>
        <!-- Quick status change -->
        <form method="POST" style="display:inline">
          <input type="hidden" name="act" value="update_status">
          <input type="hidden" name="id" value="<?= $rx['id'] ?>">
          <select name="status" onchange="this.form.submit()" style="padding:4px 6px;border-radius:6px;border:1px solid var(--border);font-size:12px">
            <option value="">Status…</option>
            <?php foreach(['Pending','Completed','Alert'] as $s): ?>
            <option value="<?= $s ?>" <?= $rx['status']===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this prescription?')">
          <input type="hidden" name="act" value="delete_rx">
          <input type="hidden" name="id" value="<?= $rx['id'] ?>">
          <button type="submit" class="btn-sm btn-delete">Del</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php else: ?>
    <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">No prescriptions found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ADD PRESCRIPTION MODAL -->
<?php if ($showAdd): ?>
<div class="modal-overlay show">
  <div class="modal">
    <div class="modal-header"><h3>Create Prescription</h3><a href="app.php?page=prescriptions" class="modal-close">×</a></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="act" value="add_rx">
        <div class="form-row">
          <div class="form-field"><label>Customer *</label>
            <select name="cust_id" required id="rxCust" onchange="checkAllergyWarn()">
              <option value="">-- Select Customer --</option>
              <?php foreach ($allCusts as $c): ?>
              <option value="<?= $c['id'] ?>" data-allergies="<?= htmlspecialchars($c['allergies'] ?? '') ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field"><label>Medication *</label>
            <select name="med_id" required id="rxMed" onchange="fillUnit(); checkAllergyWarn()">
              <option value="">-- Select Medicine --</option>
              <?php foreach ($allMeds as $m): ?>
              <option value="<?= $m['id'] ?>" data-unit="<?= $m['unit'] ?>" data-qty="<?= $m['qty'] ?>" data-idcheck="<?= $m['id_check'] ?>"><?= htmlspecialchars($m['name']) ?> (<?= $m['qty'] ?> <?= $m['unit'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-field"><label>Quantity *</label><input name="qty" type="number" min="1" required placeholder="1" id="rxQty"></div>
          <div class="form-field"><label>Unit</label><input name="unit" id="rxUnit" placeholder="tablets" readonly></div>
        </div>
        <div class="form-row">
          <div class="form-field"><label>Prescriber *</label><input name="prescriber" required placeholder="Dr. Smith"></div>
          <div class="form-field"><label>Date *</label><input name="rx_date" type="date" required value="<?= date('Y-m-d') ?>"></div>
        </div>
        <div class="form-field"><label>Notes</label><textarea name="notes" placeholder="Special instructions..."></textarea></div>
        <div id="rxWarnBox" style="display:none" class="warn-box"></div>
      </div>
      <div class="modal-footer">
        <a href="app.php?page=prescriptions" class="btn-outline">Cancel</a>
        <button type="submit" class="btn-action">💊 Create Prescription</button>
      </div>
    </form>
  </div>
</div>
<script>
function fillUnit(){
  const sel=document.getElementById('rxMed');
  const opt=sel.options[sel.selectedIndex];
  document.getElementById('rxUnit').value=opt.dataset.unit||'';
  checkAllergyWarn();
}
function checkAllergyWarn(){
  const med=document.getElementById('rxMed');
  const cust=document.getElementById('rxCust');
  const box=document.getElementById('rxWarnBox');
  let warns=[];
  const mOpt=med.options[med.selectedIndex];
  const cOpt=cust.options[cust.selectedIndex];
  if(mOpt&&mOpt.dataset.idcheck==='1') warns.push('⚠️ This medicine requires an ID check.');
  if(mOpt&&parseInt(mOpt.dataset.qty)<10) warns.push('⚠️ Low stock: only '+mOpt.dataset.qty+' remaining.');
  if(cOpt&&cOpt.dataset.allergies&&cOpt.dataset.allergies!=='None'){
    warns.push('⚠️ Patient allergies: '+cOpt.dataset.allergies+' — verify medication.');
  }
  if(warns.length){box.style.display='block';box.innerHTML=warns.join('<br>')}else{box.style.display='none'}
}
</script>
<?php endif; ?>

<!-- VIEW PRESCRIPTION MODAL -->
<?php if ($viewRx): ?>
<div class="modal-overlay show">
  <div class="modal" style="max-width:580px">
    <div class="modal-header"><h3>Prescription Details</h3><a href="app.php?page=prescriptions" class="modal-close">×</a></div>
    <div class="modal-body">
      <div class="rx-header">
        <div class="rx-id"><?= htmlspecialchars($viewRx['id']) ?></div>
        <div><?php
          $cls = match($viewRx['status']) {'Completed'=>'badge-green','Pending'=>'badge-amber','Alert'=>'badge-red',default=>'badge-gray'};
          echo "<span class='badge $cls'>{$viewRx['status']}</span>";
        ?></div>
      </div>
      <div class="detail-section">
        <h4>Patient</h4>
        <div class="detail-grid">
          <div class="detail-row"><div class="key">Name</div><div class="val"><?= htmlspecialchars($viewRx['patient']) ?></div></div>
          <div class="detail-row"><div class="key">NHS Number</div><div class="val"><?= htmlspecialchars($viewRx['nhs_number']) ?></div></div>
          <div class="detail-row"><div class="key">GP</div><div class="val"><?= htmlspecialchars($viewRx['gp_name']) ?></div></div>
          <div class="detail-row"><div class="key">Allergies</div><div class="val">
            <?= ($viewRx['allergies'] && $viewRx['allergies']!=='None') ? "<span class='badge badge-red'>".htmlspecialchars($viewRx['allergies'])."</span>" : 'None' ?>
          </div></div>
        </div>
      </div>
      <div class="detail-section">
        <h4>Prescription</h4>
        <div class="detail-grid">
          <div class="detail-row"><div class="key">Medicine</div><div class="val"><?= htmlspecialchars($viewRx['medicine']) ?></div></div>
          <div class="detail-row"><div class="key">Quantity</div><div class="val"><?= $viewRx['qty'] ?> <?= htmlspecialchars($viewRx['unit']) ?></div></div>
          <div class="detail-row"><div class="key">Date</div><div class="val"><?= date('d M Y', strtotime($viewRx['rx_date'])) ?></div></div>
          <div class="detail-row"><div class="key">Prescriber</div><div class="val"><?= htmlspecialchars($viewRx['prescriber']) ?></div></div>
          <?php if ($viewRx['id_check']): ?><div class="detail-row" style="grid-column:1/-1"><span class="badge badge-red">🔒 ID Check Required</span></div><?php endif; ?>
        </div>
        <?php if ($viewRx['notes']): ?>
        <div class="detail-row" style="margin-top:10px"><div class="key">Notes</div><div class="val" style="color:var(--orange)"><?= htmlspecialchars($viewRx['notes']) ?></div></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
