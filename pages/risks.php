<?php
// pages/risks.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'resolve_risk') {
        execute("UPDATE risks SET resolved=1 WHERE id=?", 's', $_POST['id']);
        // If it's an allergy/id-check risk, update prescription status
        $r = fetchOne("SELECT * FROM risks WHERE id=?", 's', $_POST['id']);
        if ($r && $r['rx_id']) {
            execute("UPDATE prescriptions SET status='Completed' WHERE id=?", 's', $r['rx_id']);
        }
        header('Location: app.php?page=risks&toast='.urlencode('Alert resolved').'&toast_type=success');
        exit;
    }

    if ($act === 'unresolve_risk') {
        execute("UPDATE risks SET resolved=0 WHERE id=?", 's', $_POST['id']);
        header('Location: app.php?page=risks&toast='.urlencode('Alert re-opened').'&toast_type=success');
        exit;
    }

    if ($act === 'delete_risk') {
        execute("DELETE FROM risks WHERE id=?", 's', $_POST['id']);
        header('Location: app.php?page=risks&toast='.urlencode('Risk deleted').'&toast_type=success');
        exit;
    }

    if ($act === 'add_risk') {
        $rId = nextRiskId();
        execute(
            "INSERT INTO risks (id,type,level,cust_id,med_id,rx_id,description,risk_date,resolved) VALUES (?,?,?,?,?,?,?,?,0)",
            'sssiiiss',
            $rId, trim($_POST['type']), $_POST['level'],
            ($_POST['cust_id'] ?: null), ($_POST['med_id'] ?: null),
            (trim($_POST['rx_id']) ?: null),
            trim($_POST['description']), $_POST['risk_date']
        );
        header('Location: app.php?page=risks&toast='.urlencode("Risk $rId created").'&toast_type=success');
        exit;
    }
}

$filter = $_GET['filter'] ?? 'active';
$whereF = match($filter) {
    'active'   => "WHERE r.resolved=0",
    'resolved' => "WHERE r.resolved=1",
    'red'      => "WHERE r.level='red' AND r.resolved=0",
    'amber'    => "WHERE r.level='amber' AND r.resolved=0",
    default    => '',
};

$sql = "SELECT r.*, m.name AS med_name, CONCAT(c.first_name,' ',c.last_name) AS cust_name
        FROM risks r
        LEFT JOIN medicines m ON m.id=r.med_id
        LEFT JOIN customers c ON c.id=r.cust_id
        $whereF
        ORDER BY r.resolved ASC, FIELD(r.level,'red','amber','blue'), r.risk_date DESC";
$risks = fetchAll($sql);

$allCusts = fetchAll("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM customers ORDER BY last_name");
$allMeds  = fetchAll("SELECT id, name FROM medicines ORDER BY name");
$showAdd  = isset($_GET['modal']) && $_GET['modal'] === 'add';

$riskIcons = ['red'=>'🔴','amber'=>'🟡','blue'=>'🔵'];
$riskTypes = ['ID Check','Allergy Conflict','Low Stock','Duplicate Request','Other'];
?>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
  <?php
    $red   = fetchOne("SELECT COUNT(*) c FROM risks WHERE level='red'   AND resolved=0")['c'];
    $amber = fetchOne("SELECT COUNT(*) c FROM risks WHERE level='amber' AND resolved=0")['c'];
    $res   = fetchOne("SELECT COUNT(*) c FROM risks WHERE resolved=1")['c'];
  ?>
  <div class="stat-card"><div class="stat-icon stat-red">🔴</div><div class="stat-info"><div class="label">Red Alerts</div><div class="value" style="color:#A32D2D"><?= $red ?></div></div></div>
  <div class="stat-card"><div class="stat-icon stat-orange">🟡</div><div class="stat-info"><div class="label">Amber Alerts</div><div class="value" style="color:var(--amber)"><?= $amber ?></div></div></div>
  <div class="stat-card"><div class="stat-icon stat-green">✅</div><div class="stat-info"><div class="label">Resolved</div><div class="value" style="color:var(--green)"><?= $res ?></div></div></div>
  <div class="stat-card"><div class="stat-icon stat-blue">📋</div><div class="stat-info"><div class="label">Total Alerts</div><div class="value" style="color:#185FA5"><?= $activeRisks + $res ?></div></div></div>
</div>

<div class="filters">
  <?php foreach(['active'=>'Active','red'=>'🔴 Red','amber'=>'🟡 Amber','resolved'=>'✅ Resolved','all'=>'All'] as $k=>$v): ?>
  <a class="filter-btn <?= $filter===$k?'active':'' ?>" href="app.php?page=risks&filter=<?= $k ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if ($risks): ?>
  <?php foreach ($risks as $r): ?>
  <div class="risk-item">
    <div class="risk-icon <?= $r['level'] ?>"><?= $riskIcons[$r['level']] ?? '⚠️' ?></div>
    <div class="risk-info">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
        <strong style="font-size:14px"><?= htmlspecialchars($r['id']) ?> — <?= htmlspecialchars($r['type']) ?></strong>
        <?php
          $lvlCls = match($r['level']) {'red'=>'badge-red','amber'=>'badge-amber','blue'=>'badge-blue',default=>'badge-gray'};
          echo "<span class='badge $lvlCls'>".strtoupper($r['level'])."</span>";
          if ($r['resolved']) echo "<span class='badge badge-green'>Resolved</span>";
        ?>
      </div>
      <div style="font-size:13px;color:var(--text);margin-bottom:4px"><?= htmlspecialchars($r['description']) ?></div>
      <div style="font-size:12px;color:var(--text-muted)">
        <?php if ($r['cust_name']): ?>Patient: <strong><?= htmlspecialchars($r['cust_name']) ?></strong> · <?php endif; ?>
        <?php if ($r['med_name']): ?>Med: <strong><?= htmlspecialchars($r['med_name']) ?></strong> · <?php endif; ?>
        <?php if ($r['rx_id']): ?>Rx: <strong><?= htmlspecialchars($r['rx_id']) ?></strong> · <?php endif; ?>
        <?= date('d M Y', strtotime($r['risk_date'])) ?>
      </div>
    </div>
    <div class="risk-actions">
      <?php if (!$r['resolved']): ?>
      <form method="POST" style="display:inline">
        <input type="hidden" name="act" value="resolve_risk">
        <input type="hidden" name="id" value="<?= $r['id'] ?>">
        <button type="submit" class="btn-sm btn-resolve">✓ Resolve</button>
      </form>
      <?php else: ?>
      <form method="POST" style="display:inline">
        <input type="hidden" name="act" value="unresolve_risk">
        <input type="hidden" name="id" value="<?= $r['id'] ?>">
        <button type="submit" class="btn-sm btn-edit">Re-open</button>
      </form>
      <?php endif; ?>
      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this alert?')">
        <input type="hidden" name="act" value="delete_risk">
        <input type="hidden" name="id" value="<?= $r['id'] ?>">
        <button type="submit" class="btn-sm btn-delete">Del</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php else: ?>
  <div style="padding:40px;text-align:center;color:var(--text-muted)">✅ No alerts found for this filter.</div>
  <?php endif; ?>
</div>

<!-- ADD RISK MODAL -->
<?php if ($showAdd): ?>
<div class="modal-overlay show">
  <div class="modal">
    <div class="modal-header"><h3>Add Risk Alert</h3><a href="app.php?page=risks" class="modal-close">×</a></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="act" value="add_risk">
        <div class="form-row">
          <div class="form-field"><label>Type *</label>
            <select name="type" required>
              <?php foreach ($riskTypes as $rt): ?><option><?= $rt ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-field"><label>Level *</label>
            <select name="level" required>
              <option value="red">🔴 Red</option>
              <option value="amber" selected>🟡 Amber</option>
              <option value="blue">🔵 Blue</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-field"><label>Customer</label>
            <select name="cust_id">
              <option value="">-- None --</option>
              <?php foreach ($allCusts as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-field"><label>Medicine</label>
            <select name="med_id">
              <option value="">-- None --</option>
              <?php foreach ($allMeds as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-field"><label>Prescription ID</label><input name="rx_id" placeholder="P-1001"></div>
          <div class="form-field"><label>Date *</label><input name="risk_date" type="date" required value="<?= date('Y-m-d') ?>"></div>
        </div>
        <div class="form-field"><label>Description *</label><textarea name="description" required placeholder="Describe the risk..."></textarea></div>
      </div>
      <div class="modal-footer">
        <a href="app.php?page=risks" class="btn-outline">Cancel</a>
        <button type="submit" class="btn-action">⚠️ Create Alert</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
