<?php
// pages/inventory.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'add_medicine') {
        execute(
            "INSERT INTO medicines (name,category,qty,unit,expiry,supplier,id_check,price) VALUES (?,?,?,?,?,?,?,?)",
            'ssisssid',
            trim($_POST['name']), $_POST['category'], (int)$_POST['qty'],
            $_POST['unit'], $_POST['expiry'], trim($_POST['supplier']),
            (int)$_POST['id_check'], (float)$_POST['price']
        );
        // Auto low-stock risk
        if ((int)$_POST['qty'] < LOW_STOCK_THRESHOLD) {
            $medId = lastInsertId();
            $rId = nextRiskId();
            execute("INSERT INTO risks (id,type,level,med_id,description,risk_date,resolved) VALUES (?,?,?,?,?,CURDATE(),0)",
                'sssiss', $rId,'Low Stock','amber',$medId,
                trim($_POST['name'])." added with low stock: ".(int)$_POST['qty']." ".$_POST['unit']." — reorder needed.");
        }
        header('Location: app.php?page=inventory&toast='.urlencode('Medicine added').'&toast_type=success');
        exit;
    }

    if ($act === 'edit_medicine') {
        execute(
            "UPDATE medicines SET name=?,category=?,qty=?,unit=?,expiry=?,supplier=?,id_check=?,price=? WHERE id=?",
            'ssisssidi',
            trim($_POST['name']), $_POST['category'], (int)$_POST['qty'],
            $_POST['unit'], $_POST['expiry'], trim($_POST['supplier']),
            (int)$_POST['id_check'], (float)$_POST['price'], (int)$_POST['id']
        );
        header('Location: app.php?page=inventory&toast='.urlencode('Medicine updated').'&toast_type=success');
        exit;
    }

    if ($act === 'delete_medicine') {
        execute("DELETE FROM medicines WHERE id=?", 'i', (int)$_POST['id']);
        header('Location: app.php?page=inventory&toast='.urlencode('Medicine deleted').'&toast_type=success');
        exit;
    }

    if ($act === 'restock') {
        $addQty = (int)$_POST['add_qty'];
        execute("UPDATE medicines SET qty=qty+? WHERE id=?", 'ii', $addQty, (int)$_POST['id']);
        // Resolve related low-stock risks
        execute("UPDATE risks SET resolved=1 WHERE med_id=? AND type='Low Stock'", 'i', (int)$_POST['id']);
        header('Location: app.php?page=inventory&toast='.urlencode("Stock updated (+{$addQty})").'&toast_type=success');
        exit;
    }
}

$filter = $_GET['filter'] ?? 'all';
$whereF = match($filter) {
    'low'     => "WHERE m.qty < ".LOW_STOCK_THRESHOLD,
    'idcheck' => "WHERE m.id_check=1",
    'expired' => "WHERE m.expiry < CURDATE()",
    default   => '',
};
$searchWhere = '';
$binds = [];
if ($q) {
    $like = "%$q%";
    $searchWhere = ($whereF ? ' AND ' : ' WHERE ') . "(m.name LIKE ? OR m.category LIKE ? OR m.supplier LIKE ?)";
    $binds = [$like,$like,$like];
}
$sql = "SELECT m.* FROM medicines m $whereF $searchWhere ORDER BY m.name";
$meds = $binds ? fetchAll($sql, str_repeat('s', count($binds)), ...$binds) : fetchAll($sql);

$totalStock  = array_sum(array_column($meds, 'qty'));
$lowCount    = count(array_filter($meds, fn($m) => $m['qty'] < LOW_STOCK_THRESHOLD));
$expiredCount= count(array_filter($meds, fn($m) => strtotime($m['expiry']) < time()));
$idCheckCount= count(array_filter($meds, fn($m) => $m['id_check']));

$editMed = null;
$showAdd = isset($_GET['modal']) && $_GET['modal'] === 'add';
if (isset($_GET['edit'])) $editMed = fetchOne("SELECT * FROM medicines WHERE id=?", 'i', (int)$_GET['edit']);
$restockMed = null;
if (isset($_GET['restock'])) $restockMed = fetchOne("SELECT * FROM medicines WHERE id=?", 'i', (int)$_GET['restock']);

$categories = ['Analgesic','Antibiotic','Antihypertensive','Antidiabetic','Anxiolytic','Antihistamine','Bronchodilator','Antidepressant','Corticosteroid','Thyroid','Antacid','Other'];
$units = ['tablets','capsules','bottle','vials','inhalers','sachets','ml'];
?>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card"><div class="stat-icon stat-blue">📦</div><div class="stat-info"><div class="label">Total Medicines</div><div class="value" style="color:#185FA5"><?= count($meds) ?></div></div></div>
  <div class="stat-card"><div class="stat-icon stat-orange">⚠️</div><div class="stat-info"><div class="label">Low Stock</div><div class="value" style="color:var(--orange)"><?= $lowCount ?></div></div></div>
  <div class="stat-card"><div class="stat-icon stat-red">📅</div><div class="stat-info"><div class="label">Expired</div><div class="value" style="color:#A32D2D"><?= $expiredCount ?></div></div></div>
  <div class="stat-card"><div class="stat-icon stat-teal">🔒</div><div class="stat-info"><div class="label">ID Check Required</div><div class="value" style="color:var(--teal-dark)"><?= $idCheckCount ?></div></div></div>
</div>

<div class="filters">
  <?php foreach(['all'=>'All','low'=>'Low Stock ⚠️','idcheck'=>'ID Check 🔒','expired'=>'Expired 📅'] as $k=>$v): ?>
  <a class="filter-btn <?= $filter===$k?'active':'' ?>" href="app.php?page=inventory&filter=<?= $k ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <table>
    <thead><tr><th>Medicine</th><th>Category</th><th>Stock</th><th>Unit</th><th>Expiry</th><th>Supplier</th><th>ID Check</th><th>Price</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if ($meds): ?>
    <?php foreach ($meds as $m):
      $expired  = strtotime($m['expiry']) < time();
      $lowStock = $m['qty'] < LOW_STOCK_THRESHOLD;
    ?>
    <tr>
      <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
      <td><span class="badge badge-blue"><?= htmlspecialchars($m['category']) ?></span></td>
      <td>
        <?php if ($expired): ?>
          <span class="badge badge-red">EXPIRED</span>
        <?php elseif ($m['qty'] === 0): ?>
          <span class="badge badge-red">Out of Stock</span>
        <?php elseif ($lowStock): ?>
          <span style="color:var(--red);font-weight:700"><?= $m['qty'] ?></span> <span class="badge badge-orange">Low</span>
        <?php else: ?>
          <span style="color:var(--green);font-weight:600"><?= $m['qty'] ?></span>
        <?php endif; ?>
      </td>
      <td><?= htmlspecialchars($m['unit']) ?></td>
      <td style="<?= $expired?'color:var(--red);font-weight:600':'' ?>"><?= date('d M Y', strtotime($m['expiry'])) ?></td>
      <td><?= htmlspecialchars($m['supplier']) ?></td>
      <td><?= $m['id_check'] ? "<span class='badge badge-red'>🔒 Yes</span>" : "<span class='badge badge-gray'>No</span>" ?></td>
      <td>£<?= number_format($m['price'],2) ?></td>
      <td>
        <a href="app.php?page=inventory&restock=<?= $m['id'] ?>" class="btn-sm btn-view">+Stock</a>
        <a href="app.php?page=inventory&edit=<?= $m['id'] ?>" class="btn-sm btn-edit">Edit</a>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this medicine?')">
          <input type="hidden" name="act" value="delete_medicine">
          <input type="hidden" name="id" value="<?= $m['id'] ?>">
          <button type="submit" class="btn-sm btn-delete">Del</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php else: ?>
    <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">No medicines found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ADD / EDIT MODAL -->
<?php if ($showAdd || $editMed): ?>
<div class="modal-overlay show">
  <div class="modal">
    <div class="modal-header"><h3><?= $editMed ? 'Edit Medicine' : 'Add New Medicine' ?></h3><a href="app.php?page=inventory" class="modal-close">×</a></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="act" value="<?= $editMed ? 'edit_medicine' : 'add_medicine' ?>">
        <?php if ($editMed): ?><input type="hidden" name="id" value="<?= $editMed['id'] ?>"><?php endif; ?>
        <div class="form-row">
          <div class="form-field"><label>Medicine Name *</label><input name="name" required placeholder="Paracetamol" value="<?= htmlspecialchars($editMed['name'] ?? '') ?>"></div>
          <div class="form-field"><label>Category *</label>
            <select name="category" required>
              <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat ?>" <?= ($editMed['category'] ?? '')===($cat)?'selected':'' ?>><?= $cat ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-field"><label>Quantity *</label><input name="qty" type="number" min="0" required placeholder="100" value="<?= $editMed['qty'] ?? '' ?>"></div>
          <div class="form-field"><label>Unit</label>
            <select name="unit">
              <?php foreach ($units as $u): ?>
              <option value="<?= $u ?>" <?= ($editMed['unit'] ?? 'tablets')===$u?'selected':'' ?>><?= $u ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-field"><label>Expiry Date *</label><input name="expiry" type="date" required value="<?= $editMed['expiry'] ?? '' ?>"></div>
          <div class="form-field"><label>Supplier</label><input name="supplier" placeholder="PharmaCo Ltd" value="<?= htmlspecialchars($editMed['supplier'] ?? '') ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-field"><label>Requires ID Check</label>
            <select name="id_check">
              <option value="0" <?= !($editMed['id_check'] ?? 0)?'selected':'' ?>>No</option>
              <option value="1" <?= ($editMed['id_check'] ?? 0)?'selected':'' ?>>Yes</option>
            </select>
          </div>
          <div class="form-field"><label>Price (£)</label><input name="price" type="number" step="0.01" min="0" placeholder="0.00" value="<?= $editMed['price'] ?? '' ?>"></div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="app.php?page=inventory" class="btn-outline">Cancel</a>
        <button type="submit" class="btn-action">💾 <?= $editMed ? 'Update' : 'Save Medicine' ?></button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- RESTOCK MODAL -->
<?php if ($restockMed): ?>
<div class="modal-overlay show">
  <div class="modal" style="max-width:420px">
    <div class="modal-header"><h3>🔄 Restock: <?= htmlspecialchars($restockMed['name']) ?></h3><a href="app.php?page=inventory" class="modal-close">×</a></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="act" value="restock">
        <input type="hidden" name="id" value="<?= $restockMed['id'] ?>">
        <p style="margin-bottom:16px;color:var(--text-muted)">Current stock: <strong><?= $restockMed['qty'] ?> <?= $restockMed['unit'] ?></strong></p>
        <div class="form-field"><label>Quantity to Add *</label><input name="add_qty" type="number" min="1" required placeholder="50" autofocus></div>
      </div>
      <div class="modal-footer">
        <a href="app.php?page=inventory" class="btn-outline">Cancel</a>
        <button type="submit" class="btn-action">+ Add Stock</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
