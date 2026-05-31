<?php
ob_start();
require_once __DIR__ . '/includes/db.php';
requireLogin();

$page = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard','customers','prescriptions','inventory','risks','reports','users'];
if (!in_array($page, $allowed)) $page = 'dashboard';

// ── Current user data ────────────────────────────────────────
$currentUser = fetchOne("SELECT * FROM users WHERE id=?", 'i', (int)$_SESSION['user_id']);
if ($currentUser) {
    $_SESSION['user_avatar_path'] = $currentUser['avatar_path'];
}

// ── Dashboard stats ──────────────────────────────────────────
$totalCustomers  = fetchOne("SELECT COUNT(*) c FROM customers")['c'];
$totalMedicines  = fetchOne("SELECT COUNT(*) c FROM medicines")['c'];
$lowStock        = fetchOne("SELECT COUNT(*) c FROM medicines WHERE qty < ?", 'i', LOW_STOCK_THRESHOLD)['c'];
$activeRisks     = fetchOne("SELECT COUNT(*) c FROM risks WHERE resolved=0")['c'];
$todayRx         = fetchOne("SELECT COUNT(*) c FROM prescriptions WHERE rx_date=CURDATE()")['c'];

// ── Next prescription ID ──────────────────────────────────────
function nextRxId(): string {
    $row = fetchOne("SELECT id FROM prescriptions ORDER BY id DESC LIMIT 1");
    if (!$row) return 'P-1001';
    $num = (int)substr($row['id'], 2) + 1;
    return 'P-' . str_pad($num, 4, '0', STR_PAD_LEFT);
}
function nextRiskId(): string {
    $row = fetchOne("SELECT id FROM risks ORDER BY id DESC LIMIT 1");
    if (!$row) return 'R-001';
    $num = (int)substr($row['id'], 2) + 1;
    return 'R-' . str_pad($num, 3, '0', STR_PAD_LEFT);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Drugs4U – <?= ucfirst($page) ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{
  --sidebar:#1a2e3b;--sidebar-active:#2d4a5e;--sidebar-text:#a8c4d4;--sidebar-active-text:#fff;
  --teal:#1D9E75;--teal-light:#E1F5EE;--teal-dark:#0F6E56;
  --blue:#378ADD;--blue-light:#E6F1FB;
  --orange:#E07B39;--orange-light:#FAECE7;
  --green:#639922;--green-light:#EAF3DE;
  --red:#E24B4A;--red-light:#FCEBEB;
  --amber:#BA7517;--amber-light:#FAEEDA;
  --gray:#F5F6FA;--border:#E2E8F0;--text:#2D3748;--text-muted:#718096;
  --white:#ffffff;--card-shadow:0 1px 3px rgba(0,0,0,0.08);
}
body{font-family:'Segoe UI',sans-serif;background:var(--gray);color:var(--text);height:100vh;overflow:hidden;display:flex}
/* SIDEBAR */
.sidebar{width:220px;background:var(--sidebar);display:flex;flex-direction:column;flex-shrink:0;height:100vh;overflow-y:auto}
.sidebar-logo{padding:20px 20px 16px;border-bottom:1px solid rgba(255,255,255,0.08)}
.brand{font-size:20px;font-weight:700;color:#fff}.brand span{color:var(--teal)}
.sidebar-logo small{font-size:11px;color:var(--sidebar-text);display:block;margin-top:2px}
.nav-section{padding:16px 0}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 20px;cursor:pointer;color:var(--sidebar-text);font-size:13.5px;font-weight:500;transition:.15s;border-left:3px solid transparent;text-decoration:none}
.nav-item:hover{background:rgba(255,255,255,0.05);color:#fff}
.nav-item.active{background:var(--sidebar-active);color:var(--sidebar-active-text);border-left-color:var(--teal)}
.nav-badge{margin-left:auto;background:#E24B4A;color:#fff;border-radius:10px;padding:1px 7px;font-size:10px;font-weight:700}
.sidebar-user{margin-top:auto;padding:16px 20px;border-top:1px solid rgba(255,255,255,0.08)}
.user-info{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.user-avatar{width:36px;height:36px;border-radius:50%;background:var(--teal);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;object-fit:cover}
.user-name{font-size:13px;color:#fff;font-weight:600}.user-role{font-size:11px;color:var(--sidebar-text)}
.btn-logout{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.07);border:none;border-radius:7px;padding:8px 14px;color:var(--sidebar-text);font-size:13px;cursor:pointer;width:100%;transition:.15s;text-decoration:none}
.btn-logout:hover{background:rgba(255,255,255,0.12);color:#fff}
/* MAIN */
.main{flex:1;display:flex;flex-direction:column;overflow:hidden}
.topbar{background:#fff;border-bottom:1px solid var(--border);padding:0 24px;height:60px;display:flex;align-items:center;gap:16px;flex-shrink:0}
.page-title{font-size:18px;font-weight:700;flex:1}
.search-box{display:flex;align-items:center;gap:8px;background:var(--gray);border:1px solid var(--border);border-radius:8px;padding:8px 14px;width:280px}
.search-box input{border:none;background:transparent;outline:none;font-size:13.5px;width:100%;color:var(--text)}
.btn-action{display:flex;align-items:center;gap:6px;background:var(--teal);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;transition:.15s;white-space:nowrap;text-decoration:none}
.btn-action:hover{background:var(--teal-dark)}
.bell-btn{position:relative;width:38px;height:38px;background:var(--gray);border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;color:var(--text)}
.bell-badge{position:absolute;top:-4px;right:-4px;width:16px;height:16px;background:var(--red);color:#fff;border-radius:50%;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center}
/* CONTENT */
.content{flex:1;overflow-y:auto;padding:24px}
/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:#fff;border-radius:12px;padding:18px 20px;box-shadow:var(--card-shadow);border:1px solid var(--border);display:flex;align-items:center;gap:16px}
.stat-icon{width:50px;height:50px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.stat-info .label{font-size:12px;color:var(--text-muted);font-weight:500;margin-bottom:4px}
.stat-info .value{font-size:26px;font-weight:700;line-height:1}
.stat-teal{background:var(--teal-light)}.stat-teal .value{color:var(--teal-dark)}
.stat-blue{background:var(--blue-light)}.stat-blue .value{color:#185FA5}
.stat-orange{background:var(--orange-light)}.stat-orange .value{color:var(--orange)}
.stat-green{background:var(--green-light)}.stat-green .value{color:var(--green)}
.stat-red{background:var(--red-light)}.stat-red .value{color:#A32D2D}
/* CARDS/TABLES */
.card{background:#fff;border-radius:12px;box-shadow:var(--card-shadow);border:1px solid var(--border);overflow:hidden;margin-bottom:20px}
.card-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)}
.card-header h3{font-size:15px;font-weight:700}
table{width:100%;border-collapse:collapse}
th{font-size:11.5px;color:var(--text-muted);font-weight:600;padding:10px 16px;text-align:left;background:#FAFAFA;border-bottom:1px solid var(--border);text-transform:uppercase;letter-spacing:.5px}
td{font-size:13.5px;padding:11px 16px;border-bottom:1px solid #F1F5F9;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#FAFCFF}
/* BADGES */
.badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:600;white-space:nowrap}
.badge-green{background:var(--green-light);color:var(--teal-dark)}
.badge-orange{background:var(--orange-light);color:#993C1D}
.badge-red{background:var(--red-light);color:#A32D2D}
.badge-amber{background:var(--amber-light);color:#854F0B}
.badge-blue{background:var(--blue-light);color:#185FA5}
.badge-gray{background:#F1F5F9;color:#64748B}
/* BUTTONS */
.btn-sm{padding:5px 12px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid;transition:.15s}
.btn-view{background:var(--teal-light);color:var(--teal-dark);border-color:transparent}
.btn-view:hover{background:#9FE1CB}
.btn-edit{background:#EFF6FF;color:#185FA5;border-color:transparent}
.btn-edit:hover{background:#DBEAFE}
.btn-delete{background:var(--red-light);color:#A32D2D;border-color:transparent}
.btn-delete:hover{background:#F7C1C1}
.btn-resolve{background:var(--green-light);color:var(--green);border-color:transparent}
.btn-resolve:hover{background:#c5e5a0}
.btn-outline{background:#fff;border:1.5px solid var(--border);color:var(--text);padding:7px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:.15s}
.btn-outline:hover{background:var(--gray)}
/* FILTERS */
.filters{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center}
.filter-btn{padding:6px 16px;border-radius:20px;border:1.5px solid var(--border);background:#fff;font-size:13px;cursor:pointer;font-weight:500;transition:.15s}
.filter-btn.active,.filter-btn:checked{background:var(--teal);color:#fff;border-color:var(--teal)}
.filter-btn:hover:not(.active){background:var(--gray)}
/* MODAL */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;display:none;align-items:center;justify-content:center;padding:20px}
.modal-overlay.show{display:flex}
.modal{background:#fff;border-radius:16px;width:100%;max-width:560px;box-shadow:0 20px 60px rgba(0,0,0,0.25);max-height:90vh;overflow-y:auto}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid var(--border)}
.modal-header h3{font-size:16px;font-weight:700}
.modal-close{background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-muted);line-height:1;padding:0 4px}
.modal-body{padding:24px}
.modal-footer{padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.form-field{margin-bottom:14px}
.form-field label{display:block;font-size:12.5px;color:var(--text-muted);font-weight:600;margin-bottom:5px}
.form-field input,.form-field select,.form-field textarea{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13.5px;outline:none;font-family:inherit;background:#fff;color:var(--text);transition:.2s}
.form-field input:focus,.form-field select:focus,.form-field textarea:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(29,158,117,0.1)}
.form-field textarea{resize:vertical;min-height:80px}
/* TOAST */
.toast{position:fixed;bottom:24px;right:24px;background:#1a2e3b;color:#fff;padding:14px 20px;border-radius:10px;font-size:13.5px;font-weight:500;z-index:9999;transform:translateY(80px);opacity:0;transition:.3s;max-width:320px;pointer-events:none}
.toast.show{transform:translateY(0);opacity:1}
.toast.success{background:var(--teal-dark)}
.toast.error{background:#A32D2D}
/* DASH GRID */
.dash-grid{display:grid;grid-template-columns:1fr 320px;gap:20px}
.alert-item{display:flex;align-items:flex-start;gap:12px;padding:12px 16px;border-bottom:1px solid #F1F5F9}
.alert-item:last-child{border-bottom:none}
.alert-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:5px}
.alert-dot.red{background:var(--red)}.alert-dot.amber{background:#EF9F27}.alert-dot.blue{background:var(--blue)}
.alert-text{font-size:13px;font-weight:600;color:var(--text);line-height:1.4}
.alert-sub{font-size:11.5px;color:var(--text-muted);margin-top:2px}
/* RISK ITEMS */
.risk-item{display:flex;align-items:center;gap:14px;padding:14px 16px;border-bottom:1px solid #F1F5F9;transition:.15s}
.risk-item:hover{background:#FAFCFF}
.risk-item:last-child{border-bottom:none}
.risk-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.risk-icon.red{background:var(--red-light)}.risk-icon.amber{background:var(--amber-light)}.risk-icon.blue{background:var(--blue-light)}
.risk-info{flex:1}
.risk-actions{display:flex;gap:6px}
/* REPORTS */
.report-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px}
.report-card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:20px;cursor:pointer;transition:.2s;text-align:center}
.report-card:hover{border-color:var(--teal);box-shadow:0 4px 12px rgba(29,158,117,0.12)}
.report-card .icon{font-size:32px;margin-bottom:12px}
.report-card h4{font-size:14px;font-weight:700;margin-bottom:4px}
.report-card p{font-size:12px;color:var(--text-muted)}
.report-out{background:#fff;border:1px solid var(--border);border-radius:12px;padding:24px;margin-top:20px}
/* DETAIL MODAL */
.detail-section{margin-bottom:20px}
.detail-section h4{font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--border)}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.detail-row .key{color:var(--text-muted);font-size:12px;margin-bottom:2px}
.detail-row .val{font-weight:600;font-size:13.5px}
.rx-header{background:linear-gradient(135deg,var(--teal) 0%,var(--teal-dark) 100%);border-radius:12px;padding:20px;color:#fff;margin-bottom:20px}
.rx-id{font-size:22px;font-weight:700;margin-bottom:4px}
/* CHART */
.chart-wrap{padding:20px;display:flex;align-items:flex-end;gap:6px;height:130px}
.chart-bar-item{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px}
.bar{width:100%;background:var(--teal);border-radius:4px 4px 0 0;opacity:.8;transition:.3s;min-height:4px}
.bar-lbl{font-size:10px;color:var(--text-muted);text-align:center}
.bar-val{font-size:10px;color:var(--text-muted);font-weight:600}
/* SEARCH HIGHLIGHT */
.highlight{background:#FEF9C3;border-radius:2px}
/* WARNING BOX */
.warn-box{background:var(--red-light);border:1px solid #F7C1C1;border-radius:8px;padding:12px;margin-top:8px;font-size:13px;color:#A32D2D}
/* INV SUMMARY */
.inv-summary{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
.stock-bar-wrap{margin-bottom:12px}
.stock-bar-label{display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px}
.stock-bar{height:8px;background:#E2E8F0;border-radius:4px;overflow:hidden}
.stock-bar-fill{height:100%;border-radius:4px;transition:.4s}
@media(max-width:1024px){.stats-grid{grid-template-columns:repeat(2,1fr)}.dash-grid{grid-template-columns:1fr}.report-grid{grid-template-columns:repeat(2,1fr)}}
@media print{.sidebar,.topbar,.btn-action,.btn-sm,.btn-logout{display:none!important}.content{padding:0}}
</style>
</head>
<body>
<div class="toast" id="toast"></div>

<!-- SIDEBAR -->
<div class="sidebar">
  <div class="sidebar-logo">
    <div class="brand">Drugs<span>4U</span></div>
    <small>PMS v2.0</small>
  </div>
  <div class="nav-section">
    <?php
    $navItems = [
      ['page'=>'dashboard',    'icon'=>'⊞',  'label'=>'Dashboard'],
      ['page'=>'customers',    'icon'=>'👥', 'label'=>'Customers'],
      ['page'=>'prescriptions','icon'=>'📋', 'label'=>'Prescriptions'],
      ['page'=>'inventory',    'icon'=>'📦', 'label'=>'Inventory'],
      ['page'=>'risks',        'icon'=>'⚠️', 'label'=>'Risk Alerts', 'badge'=>$activeRisks],
      ['page'=>'reports',      'icon'=>'📊', 'label'=>'Reports'],
    ];
    if ($_SESSION['user_role'] === 'Admin') {
      $navItems[] = ['page'=>'users', 'icon'=>'👤', 'label'=>'Users'];
    }
    foreach ($navItems as $n):
      $active = $page === $n['page'] ? 'active' : '';
    ?>
    <a class="nav-item <?= $active ?>" href="app.php?page=<?= $n['page'] ?>">
      <span><?= $n['icon'] ?></span>
      <?= $n['label'] ?>
      <?php if (!empty($n['badge']) && $n['badge'] > 0): ?>
        <span class="nav-badge"><?= $n['badge'] ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
  <div class="sidebar-user">
    <div class="user-info">
      <?php if (!empty($_SESSION['user_avatar_path'])): ?>
      <img src="uploads/<?= htmlspecialchars($_SESSION['user_avatar_path']) ?>" class="user-avatar" style="width:36px;height:36px;border-radius:50%;object-fit:cover;padding:0">
      <?php else: ?>
      <div class="user-avatar"><?= htmlspecialchars($_SESSION['user_avatar'] ?? 'U') ?></div>
      <?php endif; ?>
      <div>
        <div class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
        <div class="user-role"><?= htmlspecialchars($_SESSION['user_role']) ?></div>
      </div>
    </div>
    <a class="btn-logout" href="auth.php?action=logout">
      ← Log Out
    </a>
    <a class="btn-logout" href="#passwordModal" onclick="openPasswordModal()" style="margin-top:8px">
      🔒 Change Password
    </a>
  </div>
</div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="page-title"><?= ucfirst($page) ?></div>
    <form method="GET" action="app.php" style="display:contents">
      <input type="hidden" name="page" value="<?= $page ?>">
      <div class="search-box">
        🔍
        <input type="text" name="q" id="searchInput" placeholder="Search..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" oninput="this.form.submit()">
      </div>
    </form>
    <a class="bell-btn" href="app.php?page=risks">
      🔔
      <?php if ($activeRisks > 0): ?>
        <span class="bell-badge"><?= $activeRisks ?></span>
      <?php endif; ?>
    </a>
    <?php
    $addLinks = [
      'customers'     => '?page=customers&modal=add',
      'prescriptions' => '?page=prescriptions&modal=add',
      'inventory'     => '?page=inventory&modal=add',
      'risks'         => '?page=risks&modal=add',
    ];
    if (isset($addLinks[$page])): ?>
    <a class="btn-action" href="app.php<?= $addLinks[$page] ?>">+ Add New</a>
    <?php endif; ?>
  </div>

  <div class="content">
    <?php
    $q = trim($_GET['q'] ?? '');
    include __DIR__ . "/pages/{$page}.php";
    ?>
  </div>
</div>

<!-- TOAST SCRIPT -->
<script>
function showToast(msg, type) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast ' + (type || '');
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3500);
}
<?php if (isset($_GET['toast'])): ?>
showToast(<?= json_encode(urldecode($_GET['toast'])) ?>, <?= json_encode($_GET['toast_type'] ?? '') ?>);
<?php endif; ?>

function openPasswordModal() {
  document.getElementById('passwordModal').classList.add('show');
}
function closePasswordModal() {
  document.getElementById('passwordModal').classList.remove('show');
}
</script>

<!-- CHANGE PASSWORD MODAL -->
<div class="modal-overlay" id="passwordModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <h3>Change Password</h3>
      <a href="javascript:void(0)" class="modal-close" onclick="closePasswordModal()">×</a>
    </div>
    <form method="POST" action="auth.php">
      <input type="hidden" name="action" value="change_password">
      <div class="modal-body">
        <div class="form-field"><label>Current Password *</label><input name="current_password" type="password" required placeholder="Enter current password"></div>
        <div class="form-field"><label>New Password *</label><input name="new_password" type="password" required minlength="6" placeholder="Min 6 characters"></div>
      </div>
      <div class="modal-footer">
        <a href="javascript:void(0)" class="btn-outline" onclick="closePasswordModal()">Cancel</a>
        <button type="submit" class="btn-action">🔒 Change Password</button>
      </div>
    </form>
  </div>
</div>
</script>
</body>
</html>
<?php ob_end_flush(); ?>
