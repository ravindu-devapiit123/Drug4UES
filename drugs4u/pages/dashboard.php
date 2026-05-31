<?php
// pages/dashboard.php
$recentRx = fetchAll("
    SELECT p.*, CONCAT(c.first_name,' ',c.last_name) AS patient,
           m.name AS medicine
    FROM prescriptions p
    JOIN customers c ON c.id=p.cust_id
    JOIN medicines  m ON m.id=p.med_id
    ORDER BY p.rx_date DESC, p.id DESC
    LIMIT 8
");
$activeAlerts = fetchAll("
    SELECT r.*, m.name AS med_name, CONCAT(c.first_name,' ',c.last_name) AS cust_name
    FROM risks r
    LEFT JOIN medicines m ON m.id=r.med_id
    LEFT JOIN customers c ON c.id=r.cust_id
    WHERE r.resolved=0
    ORDER BY FIELD(r.level,'red','amber','blue'), r.risk_date DESC
    LIMIT 6
");
$chartData = fetchAll("
    SELECT rx_date, COUNT(*) cnt
    FROM prescriptions
    WHERE rx_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY rx_date
    ORDER BY rx_date
");
$maxBar = max(array_column($chartData, 'cnt') ?: [1]);

function statusBadge(string $s): string {
    $map = ['Completed'=>'badge-green','Pending'=>'badge-amber','Alert'=>'badge-red',
            'Active'=>'badge-green','Inactive'=>'badge-gray'];
    $cls = $map[$s] ?? 'badge-gray';
    return "<span class='badge $cls'>$s</span>";
}
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon stat-teal">👥</div>
    <div class="stat-info"><div class="label">Total Customers</div><div class="value" style="color:var(--teal-dark)"><?= number_format($totalCustomers) ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-blue">💊</div>
    <div class="stat-info"><div class="label">Medications</div><div class="value" style="color:#185FA5"><?= $totalMedicines ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-orange">⚠️</div>
    <div class="stat-info"><div class="label">Low Stock Alerts</div><div class="value" style="color:var(--orange)"><?= $lowStock ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-red">🚨</div>
    <div class="stat-info"><div class="label">Active Risk Alerts</div><div class="value" style="color:#A32D2D"><?= $activeRisks ?></div></div>
  </div>
</div>

<div class="dash-grid">
  <!-- LEFT: recent prescriptions + chart -->
  <div>
    <div class="card">
      <div class="card-header">
        <h3>📈 Prescriptions – Last 7 Days</h3>
      </div>
      <div class="chart-wrap">
        <?php foreach ($chartData as $cd):
          $h = $maxBar > 0 ? round(($cd['cnt']/$maxBar)*90) : 4;
          $d = date('d M', strtotime($cd['rx_date']));
        ?>
        <div class="chart-bar-item">
          <span class="bar-val"><?= $cd['cnt'] ?></span>
          <div class="bar" style="height:<?= $h ?>px" title="<?= $d ?>: <?= $cd['cnt'] ?> prescriptions"></div>
          <span class="bar-lbl"><?= $d ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (!$chartData): ?>
        <p style="color:var(--text-muted);font-size:13px;padding:20px">No prescriptions in the last 7 days.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3>Recent Prescriptions</h3>
        <a href="app.php?page=prescriptions" style="font-size:12px;color:var(--teal);font-weight:600">View All →</a>
      </div>
      <table>
        <thead><tr><th>ID</th><th>Patient</th><th>Medicine</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentRx as $rx): ?>
        <tr>
          <td><strong><?= htmlspecialchars($rx['id']) ?></strong></td>
          <td><?= htmlspecialchars($rx['patient']) ?></td>
          <td><?= htmlspecialchars($rx['medicine']) ?></td>
          <td><?= date('d M Y', strtotime($rx['rx_date'])) ?></td>
          <td><?= statusBadge($rx['status']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- RIGHT: active alerts -->
  <div>
    <div class="card">
      <div class="card-header">
        <h3>🔴 Active Alerts</h3>
        <span class="badge badge-red"><?= $activeRisks ?> Active</span>
      </div>
      <?php if ($activeAlerts): ?>
      <?php foreach ($activeAlerts as $al): ?>
      <div class="alert-item">
        <div class="alert-dot <?= $al['level'] ?>"></div>
        <div>
          <div class="alert-text"><?= htmlspecialchars($al['type']) ?></div>
          <div class="alert-sub"><?= htmlspecialchars(substr($al['description'],0,80)) ?>…</div>
          <div class="alert-sub"><?= date('d M Y', strtotime($al['risk_date'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php else: ?>
      <div style="padding:20px;font-size:13px;color:var(--text-muted)">✅ No active alerts</div>
      <?php endif; ?>
      <div style="padding:12px 16px;border-top:1px solid var(--border)">
        <a href="app.php?page=risks" class="btn-action" style="width:100%;justify-content:center">View All Alerts</a>
      </div>
    </div>
  </div>
</div>
