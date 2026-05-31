<?php
// pages/customers.php
// ── Handle form submissions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'add_customer') {
        execute(
            "INSERT INTO customers (first_name,last_name,dob,phone,email,address,nhs_number,gp_name,allergies,conditions,last_visit,status)
             VALUES (?,?,?,?,?,?,?,?,?,?,CURDATE(),'Active')",
            'ssssssssss',
            trim($_POST['first_name']), trim($_POST['last_name']),
            $_POST['dob'], $_POST['phone'], $_POST['email'],
            $_POST['address'], $_POST['nhs_number'], $_POST['gp_name'],
            $_POST['allergies'] ?: 'None', $_POST['conditions']
        );
        header('Location: app.php?page=customers&toast='.urlencode('Customer added successfully').'&toast_type=success');
        exit;
    }

    if ($act === 'edit_customer') {
        execute(
            "UPDATE customers SET first_name=?,last_name=?,dob=?,phone=?,email=?,address=?,nhs_number=?,gp_name=?,allergies=?,conditions=?,status=? WHERE id=?",
            'sssssssssssi',
            trim($_POST['first_name']), trim($_POST['last_name']),
            $_POST['dob'], $_POST['phone'], $_POST['email'],
            $_POST['address'], $_POST['nhs_number'], $_POST['gp_name'],
            $_POST['allergies'] ?: 'None', $_POST['conditions'], $_POST['status'],
            (int)$_POST['id']
        );
        header('Location: app.php?page=customers&toast='.urlencode('Customer updated').'&toast_type=success');
        exit;
    }

    if ($act === 'delete_customer') {
        execute("DELETE FROM customers WHERE id=?", 'i', (int)$_POST['id']);
        header('Location: app.php?page=customers&toast='.urlencode('Customer deleted').'&toast_type=success');
        exit;
    }
}

// ── List query ────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$whereStatus = match($filter) {
    'active'   => "WHERE c.status='Active'",
    'alert'    => "WHERE c.status='Alert'",
    'inactive' => "WHERE c.status='Inactive'",
    default    => '',
};
$searchWhere = '';
$searchBind  = [];
if ($q) {
    $like = "%$q%";
    $searchWhere = $whereStatus ? " AND " : " WHERE ";
    $searchWhere .= "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.nhs_number LIKE ?)";
    $searchBind = [$like,$like,$like,$like,$like];
}
$sql = "SELECT c.*, (SELECT COUNT(*) FROM prescriptions WHERE cust_id=c.id) AS rx_count
        FROM customers c $whereStatus $searchWhere
        ORDER BY c.last_name, c.first_name";

if ($searchBind) {
    $customers = fetchAll($sql, str_repeat('s', count($searchBind)), ...$searchBind);
} else {
    $customers = fetchAll($sql);
}

// view/edit customer
$viewCust = null;
$editCust = null;
if (isset($_GET['view'])) $viewCust = fetchOne("SELECT * FROM customers WHERE id=?", 'i', (int)$_GET['view']);
if (isset($_GET['edit'])) $editCust = fetchOne("SELECT * FROM customers WHERE id=?", 'i', (int)$_GET['edit']);
$showAdd = isset($_GET['modal']) && $_GET['modal']==='add';
?>

<div class="filters">
  <?php foreach(['all'=>'All','active'=>'Active','alert'=>'Alert','inactive'=>'Inactive'] as $k=>$v): ?>
  <a class="filter-btn <?= $filter===$k?'active':'' ?>" href="app.php?page=customers&filter=<?= $k ?>"><?= $v ?></a>
  <?php endforeach; ?>
  <span style="margin-left:auto;font-size:13px;color:var(--text-muted)"><?= count($customers) ?> result(s)</span>
</div>

<div class="card">
  <table>
    <thead>
      <tr><th>Name</th><th>DOB</th><th>Phone</th><th>NHS No.</th><th>Allergies</th><th>Rxs</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php if ($customers): ?>
    <?php foreach ($customers as $c): ?>
    <tr>
      <td>
        <strong><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></strong><br>
        <span style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($c['email']) ?></span>
      </td>
      <td><?= date('d M Y', strtotime($c['dob'])) ?></td>
      <td><?= htmlspecialchars($c['phone']) ?></td>
      <td style="font-size:12px"><?= htmlspecialchars($c['nhs_number']) ?></td>
      <td><?php if ($c['allergies'] && $c['allergies']!=='None'): ?><span class="badge badge-red"><?= htmlspecialchars($c['allergies']) ?></span><?php else: ?>None<?php endif; ?></td>
      <td><?= $c['rx_count'] ?></td>
      <td>
        <?php
        $sBadge = match($c['status']) {
            'Active'   => 'badge-green',
            'Alert'    => 'badge-red',
            'Inactive' => 'badge-gray',
            default    => 'badge-gray'
        };
        echo "<span class='badge $sBadge'>{$c['status']}</span>";
        ?>
      </td>
      <td>
        <a href="app.php?page=customers&view=<?= $c['id'] ?>" class="btn-sm btn-view">View</a>
        <a href="app.php?page=customers&edit=<?= $c['id'] ?>" class="btn-sm btn-edit">Edit</a>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this customer?')">
          <input type="hidden" name="act" value="delete_customer">
          <input type="hidden" name="id" value="<?= $c['id'] ?>">
          <button type="submit" class="btn-sm btn-delete">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php else: ?>
    <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">No customers found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ADD CUSTOMER MODAL -->
<?php if ($showAdd || $editCust): ?>
<div class="modal-overlay show" id="custModal">
  <div class="modal">
    <div class="modal-header">
      <h3><?= $editCust ? 'Edit Customer' : 'Add New Customer' ?></h3>
      <a href="app.php?page=customers" class="modal-close">×</a>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="act" value="<?= $editCust ? 'edit_customer' : 'add_customer' ?>">
        <?php if ($editCust): ?><input type="hidden" name="id" value="<?= $editCust['id'] ?>"><?php endif; ?>
        <div class="form-row">
          <div class="form-field"><label>First Name *</label><input name="first_name" required placeholder="Ahmed" value="<?= htmlspecialchars($editCust['first_name'] ?? '') ?>"></div>
          <div class="form-field"><label>Last Name *</label><input name="last_name" required placeholder="Perera" value="<?= htmlspecialchars($editCust['last_name'] ?? '') ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-field"><label>Date of Birth *</label><input name="dob" type="date" required value="<?= $editCust['dob'] ?? '' ?>"></div>
          <div class="form-field"><label>Phone *</label><input name="phone" required placeholder="0765-123-456" value="<?= htmlspecialchars($editCust['phone'] ?? '') ?>"></div>
        </div>
        <div class="form-field"><label>Email</label><input name="email" type="email" placeholder="ahmed@email.com" value="<?= htmlspecialchars($editCust['email'] ?? '') ?>"></div>
        <div class="form-field"><label>Address</label><input name="address" placeholder="123 High Street, Stafford" value="<?= htmlspecialchars($editCust['address'] ?? '') ?>"></div>
        <div class="form-row">
          <div class="form-field"><label>NHS Number</label><input name="nhs_number" placeholder="NHS-12345678" value="<?= htmlspecialchars($editCust['nhs_number'] ?? '') ?>"></div>
          <div class="form-field"><label>GP Name</label><input name="gp_name" placeholder="Dr. Smith" value="<?= htmlspecialchars($editCust['gp_name'] ?? '') ?>"></div>
        </div>
        <div class="form-field"><label>Known Allergies</label><input name="allergies" placeholder="Penicillin, Aspirin (or None)" value="<?= htmlspecialchars($editCust['allergies'] ?? '') ?>"></div>
        <div class="form-field"><label>Medical Conditions</label><textarea name="conditions" placeholder="Hypertension, Diabetes..."><?= htmlspecialchars($editCust['conditions'] ?? '') ?></textarea></div>
        <?php if ($editCust): ?>
        <div class="form-field"><label>Status</label>
          <select name="status">
            <?php foreach(['Active','Inactive','Alert'] as $s): ?>
            <option value="<?= $s ?>" <?= ($editCust['status']===$s)?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <a href="app.php?page=customers" class="btn-outline">Cancel</a>
        <button type="submit" class="btn-action">💾 <?= $editCust ? 'Update' : 'Save Customer' ?></button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- VIEW CUSTOMER MODAL -->
<?php if ($viewCust):
    $cRxs = fetchAll("SELECT p.*, m.name AS medicine FROM prescriptions p JOIN medicines m ON m.id=p.med_id WHERE p.cust_id=? ORDER BY p.rx_date DESC LIMIT 5", 'i', $viewCust['id']);
?>
<div class="modal-overlay show">
  <div class="modal" style="max-width:620px">
    <div class="modal-header">
      <h3>👤 <?= htmlspecialchars($viewCust['first_name'].' '.$viewCust['last_name']) ?></h3>
      <a href="app.php?page=customers" class="modal-close">×</a>
    </div>
    <div class="modal-body">
      <div class="detail-section">
        <h4>Personal Information</h4>
        <div class="detail-grid">
          <div class="detail-row"><div class="key">Date of Birth</div><div class="val"><?= date('d M Y', strtotime($viewCust['dob'])) ?></div></div>
          <div class="detail-row"><div class="key">Phone</div><div class="val"><?= htmlspecialchars($viewCust['phone']) ?></div></div>
          <div class="detail-row"><div class="key">Email</div><div class="val"><?= htmlspecialchars($viewCust['email']) ?></div></div>
          <div class="detail-row"><div class="key">NHS Number</div><div class="val"><?= htmlspecialchars($viewCust['nhs_number']) ?></div></div>
          <div class="detail-row"><div class="key">GP</div><div class="val"><?= htmlspecialchars($viewCust['gp_name']) ?></div></div>
          <div class="detail-row"><div class="key">Status</div><div class="val"><?= $viewCust['status'] ?></div></div>
        </div>
        <div class="detail-row" style="margin-top:10px"><div class="key">Address</div><div class="val"><?= htmlspecialchars($viewCust['address']) ?></div></div>
      </div>
      <div class="detail-section">
        <h4>Medical Profile</h4>
        <div class="detail-row" style="margin-bottom:8px"><div class="key">Allergies</div>
          <div class="val"><?= ($viewCust['allergies'] && $viewCust['allergies']!=='None') ? "<span class='badge badge-red'>".htmlspecialchars($viewCust['allergies'])."</span>" : 'None' ?></div>
        </div>
        <div class="detail-row"><div class="key">Conditions</div><div class="val"><?= htmlspecialchars($viewCust['conditions']) ?></div></div>
      </div>
      <?php if ($cRxs): ?>
      <div class="detail-section">
        <h4>Recent Prescriptions</h4>
        <table><thead><tr><th>ID</th><th>Medicine</th><th>Date</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($cRxs as $cr): ?>
        <tr>
          <td><?= $cr['id'] ?></td>
          <td><?= htmlspecialchars($cr['medicine']) ?></td>
          <td><?= date('d M Y', strtotime($cr['rx_date'])) ?></td>
          <td><?php
            $cls = match($cr['status']) {'Completed'=>'badge-green','Pending'=>'badge-amber','Alert'=>'badge-red',default=>'badge-gray'};
            echo "<span class='badge $cls'>{$cr['status']}</span>";
          ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>
