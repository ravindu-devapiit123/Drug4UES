<?php
// pages/reports.php

$type = $_GET['report'] ?? '';

function rBadge(string $s): string {
    $map = ['Completed'=>'badge-green','Pending'=>'badge-amber','Alert'=>'badge-red',
            'Active'=>'badge-green','Inactive'=>'badge-gray'];
    return "<span class='badge ".($map[$s]??'badge-gray')."'>$s</span>";
}
function stockBadge(array $m): string {
    if (strtotime($m['expiry']) < time()) return "<span class='badge badge-red'>Expired</span>";
    if ($m['qty'] == 0)                   return "<span class='badge badge-red'>Out of Stock</span>";
    if ($m['qty'] < LOW_STOCK_THRESHOLD)  return "<span class='badge badge-orange'>Low Stock</span>";
    return "<span class='badge badge-green'>In Stock</span>";
}
?>

<div class="report-grid">
  <?php $reports = [
    ['id'=>'daily',        'icon'=>'📅','title'=>'Daily Summary',           'desc'=>'Today\'s activity overview'],
    ['id'=>'prescriptions','icon'=>'💊','title'=>'Prescriptions Report',    'desc'=>'All prescription records'],
    ['id'=>'customers',    'icon'=>'👥','title'=>'Customer Report',         'desc'=>'Full customer database'],
    ['id'=>'inventory',    'icon'=>'📦','title'=>'Inventory Report',        'desc'=>'All medicines & stock levels'],
    ['id'=>'lowstock',     'icon'=>'⚠️','title'=>'Low Stock Report',        'desc'=>'Items below threshold'],
    ['id'=>'risk',         'icon'=>'🚨','title'=>'Risk Alert Report',       'desc'=>'All active & resolved alerts'],
  ]; ?>
  <?php foreach ($reports as $r): ?>
  <a href="app.php?page=reports&report=<?= $r['id'] ?>" class="report-card" style="text-decoration:none;color:inherit">
    <div class="icon"><?= $r['icon'] ?></div>
    <h4><?= $r['title'] ?></h4>
    <p><?= $r['desc'] ?></p>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($type): ?>
<div class="report-out" id="reportOut">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <div>
      <h3 id="reportTitle" style="font-size:18px;font-weight:700"></h3>
      <p style="color:var(--text-muted);font-size:13px">Generated: <?= date('d M Y H:i') ?></p>
    </div>
    <button onclick="window.print()" class="btn-action">🖨️ Print</button>
  </div>
  <div id="reportContent">

  <?php if ($type === 'daily'):
    $todayRx  = fetchAll("SELECT p.*, CONCAT(c.first_name,' ',c.last_name) AS patient, m.name AS medicine
                          FROM prescriptions p JOIN customers c ON c.id=p.cust_id JOIN medicines m ON m.id=p.med_id
                          WHERE p.rx_date=CURDATE() ORDER BY p.id DESC");
    $aRisks   = fetchAll("SELECT * FROM risks WHERE resolved=0");
    $lowMeds  = fetchAll("SELECT * FROM medicines WHERE qty < ?", 'i', LOW_STOCK_THRESHOLD);
    ?>
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-icon stat-green">📋</div><div class="stat-info"><div class="label">Today's Prescriptions</div><div class="value" style="color:var(--green)"><?= count($todayRx) ?></div></div></div>
      <div class="stat-card"><div class="stat-icon stat-orange">⚠️</div><div class="stat-info"><div class="label">Active Alerts</div><div class="value" style="color:var(--orange)"><?= count($aRisks) ?></div></div></div>
      <div class="stat-card"><div class="stat-icon stat-red">📦</div><div class="stat-info"><div class="label">Low Stock Items</div><div class="value" style="color:var(--red)"><?= count($lowMeds) ?></div></div></div>
      <div class="stat-card"><div class="stat-icon stat-teal">👥</div><div class="stat-info"><div class="label">Total Customers</div><div class="value" style="color:var(--teal-dark)"><?= $totalCustomers ?></div></div></div>
    </div>
    <h4 style="margin:16px 0 10px">Today's Prescriptions</h4>
    <?php if ($todayRx): ?>
    <table><thead><tr><th>ID</th><th>Patient</th><th>Medicine</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($todayRx as $rx): ?>
    <tr><td><?= $rx['id'] ?></td><td><?= htmlspecialchars($rx['patient']) ?></td><td><?= htmlspecialchars($rx['medicine']) ?></td><td><?= rBadge($rx['status']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><p style="color:var(--text-muted)">No prescriptions today.</p><?php endif; ?>
    <?php $reportTitle = 'Daily Summary — '.date('d M Y'); ?>

  <?php elseif ($type === 'prescriptions'):
    $data = fetchAll("SELECT p.*, CONCAT(c.first_name,' ',c.last_name) AS patient, m.name AS medicine
                      FROM prescriptions p JOIN customers c ON c.id=p.cust_id JOIN medicines m ON m.id=p.med_id
                      ORDER BY p.rx_date DESC, p.id DESC");
    ?>
    <table><thead><tr><th>ID</th><th>Patient</th><th>Medicine</th><th>Qty</th><th>Date</th><th>Prescriber</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($data as $rx): ?>
    <tr><td><?= $rx['id'] ?></td><td><?= htmlspecialchars($rx['patient']) ?></td><td><?= htmlspecialchars($rx['medicine']) ?></td>
    <td><?= $rx['qty'].' '.$rx['unit'] ?></td><td><?= date('d M Y', strtotime($rx['rx_date'])) ?></td>
    <td><?= htmlspecialchars($rx['prescriber']) ?></td><td><?= rBadge($rx['status']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php $reportTitle = 'Prescription Report — '.count($data).' records'; ?>

  <?php elseif ($type === 'customers'):
    $data = fetchAll("SELECT * FROM customers ORDER BY last_name, first_name");
    ?>
    <table><thead><tr><th>ID</th><th>Name</th><th>DOB</th><th>Phone</th><th>NHS</th><th>Allergies</th><th>Conditions</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($data as $c): ?>
    <tr><td><?= $c['id'] ?></td><td><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></td>
    <td><?= date('d M Y', strtotime($c['dob'])) ?></td><td><?= htmlspecialchars($c['phone']) ?></td>
    <td><?= htmlspecialchars($c['nhs_number']) ?></td><td><?= htmlspecialchars($c['allergies']) ?></td>
    <td style="font-size:12px"><?= htmlspecialchars($c['conditions']) ?></td><td><?= rBadge($c['status']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php $reportTitle = 'Customer Report — '.count($data).' customers'; ?>

  <?php elseif ($type === 'inventory'):
    $data = fetchAll("SELECT * FROM medicines ORDER BY name");
    ?>
    <table><thead><tr><th>Medicine</th><th>Category</th><th>Qty</th><th>Unit</th><th>Expiry</th><th>Supplier</th><th>ID Check</th><th>Price</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($data as $m): ?>
    <tr><td><strong><?= htmlspecialchars($m['name']) ?></strong></td><td><?= $m['category'] ?></td>
    <td><?= $m['qty'] ?></td><td><?= $m['unit'] ?></td>
    <td><?= date('d M Y', strtotime($m['expiry'])) ?></td>
    <td><?= htmlspecialchars($m['supplier']) ?></td>
    <td><?= $m['id_check']?'Yes':'No' ?></td>
    <td>£<?= number_format($m['price'],2) ?></td>
    <td><?= stockBadge($m) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php $reportTitle = 'Inventory Report — '.count($data).' items'; ?>

  <?php elseif ($type === 'lowstock'):
    $data = fetchAll("SELECT * FROM medicines WHERE qty < ? ORDER BY qty ASC", 'i', LOW_STOCK_THRESHOLD);
    ?>
    <table><thead><tr><th>Medicine</th><th>Remaining</th><th>Unit</th><th>Supplier</th><th>Expiry</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($data as $m): ?>
    <tr><td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
    <td style="color:var(--red);font-weight:700"><?= $m['qty'] ?></td>
    <td><?= $m['unit'] ?></td><td><?= htmlspecialchars($m['supplier']) ?></td>
    <td><?= date('d M Y', strtotime($m['expiry'])) ?></td>
    <td><?= stockBadge($m) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php $reportTitle = 'Low Stock Report — '.count($data).' items below threshold'; ?>

  <?php elseif ($type === 'risk'):
    $data = fetchAll("SELECT r.*, m.name AS med_name, CONCAT(c.first_name,' ',c.last_name) AS cust_name
                      FROM risks r LEFT JOIN medicines m ON m.id=r.med_id LEFT JOIN customers c ON c.id=r.cust_id
                      ORDER BY r.resolved ASC, FIELD(r.level,'red','amber','blue'), r.risk_date DESC");
    ?>
    <table><thead><tr><th>ID</th><th>Type</th><th>Level</th><th>Description</th><th>Patient</th><th>Medicine</th><th>Date</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($data as $r):
      $lvlC = match($r['level']) {'red'=>'badge-red','amber'=>'badge-amber','blue'=>'badge-blue',default=>'badge-gray'};
    ?>
    <tr><td><?= $r['id'] ?></td><td><?= htmlspecialchars($r['type']) ?></td>
    <td><span class="badge <?= $lvlC ?>"><?= strtoupper($r['level']) ?></span></td>
    <td style="font-size:12px;max-width:260px"><?= htmlspecialchars($r['description']) ?></td>
    <td><?= htmlspecialchars($r['cust_name'] ?? '—') ?></td>
    <td><?= htmlspecialchars($r['med_name'] ?? '—') ?></td>
    <td><?= date('d M Y', strtotime($r['risk_date'])) ?></td>
    <td><?= $r['resolved'] ? "<span class='badge badge-green'>Resolved</span>" : "<span class='badge badge-red'>Active</span>" ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php $reportTitle = 'Risk Alert Report — '.count($data).' alerts'; ?>
  <?php endif; ?>

  </div><!-- /reportContent -->
</div>
<script>document.getElementById('reportTitle').textContent = <?= json_encode($reportTitle ?? '') ?>;</script>
<?php endif; ?>
