<?php
// pages/users.php – Admin only user management

// Admin check
if ($_SESSION['user_role'] !== 'Admin') {
    echo '<div style="padding:60px;text-align:center"><h2 style="color:var(--red)">Access Denied</h2><p style="color:var(--text-muted)">Only administrators can manage users.</p></div>';
    return;
}

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'add_user') {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $avatarPath = '';

        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $avatarPath = uploadAvatar($_FILES['avatar']);
        }

        execute(
            "INSERT INTO users (name,role,email,password,avatar_path) VALUES (?,?,?,?,?)",
            'sssss',
            trim($_POST['name']), $_POST['role'], $_POST['email'], $hash, $avatarPath
        );
        header('Location: app.php?page=users&toast='.urlencode('User created successfully').'&toast_type=success');
        exit;
    }

    if ($act === 'edit_user') {
        $fields = "name=?, role=?, email=?";
        $vals   = [trim($_POST['name']), $_POST['role'], $_POST['email']];
        $types  = 'sss';

        if (!empty($_POST['password'])) {
            $fields .= ", password=?";
            $vals[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $types .= 's';
        }

        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $old = fetchOne("SELECT avatar_path FROM users WHERE id=?", 'i', (int)$_POST['id']);
            if ($old && $old['avatar_path'] && file_exists(__DIR__ . '/../uploads/' . $old['avatar_path'])) {
                unlink(__DIR__ . '/../uploads/' . $old['avatar_path']);
            }
            $avatarPath = uploadAvatar($_FILES['avatar']);
            $fields .= ", avatar_path=?";
            $vals[] = $avatarPath;
            $types .= 's';
        }

        $vals[] = (int)$_POST['id'];
        $types .= 'i';
        execute("UPDATE users SET $fields WHERE id=?", $types, ...$vals);
        header('Location: app.php?page=users&toast='.urlencode('User updated').'&toast_type=success');
        exit;
    }

    if ($act === 'delete_user') {
        $uid = (int)$_POST['id'];
        if ($uid === (int)$_SESSION['user_id']) {
            header('Location: app.php?page=users&toast='.urlencode('Cannot delete your own account').'&toast_type=error');
            exit;
        }
        $old = fetchOne("SELECT avatar_path FROM users WHERE id=?", 'i', $uid);
        if ($old && $old['avatar_path'] && file_exists(__DIR__ . '/../uploads/' . $old['avatar_path'])) {
            unlink(__DIR__ . '/../uploads/' . $old['avatar_path']);
        }
        execute("DELETE FROM users WHERE id=?", 'i', $uid);
        header('Location: app.php?page=users&toast='.urlencode('User deleted').'&toast_type=success');
        exit;
    }
}

function uploadAvatar($file): string {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) return '';
    $name = 'user_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dir = __DIR__ . '/../uploads/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return $name;
    }
    return '';
}

// ── List ─────────────────────────────────────────────────────
$searchWhere = '';
$binds = [];
if ($q) {
    $like = "%$q%";
    $searchWhere = "WHERE u.name LIKE ? OR u.email LIKE ? OR u.role LIKE ?";
    $binds = [$like, $like, $like];
}
$sql = "SELECT u.* FROM users u $searchWhere ORDER BY u.created_at DESC";
$users = $binds ? fetchAll($sql, str_repeat('s', count($binds)), ...$binds) : fetchAll($sql);

$editUser = null;
$showAdd  = isset($_GET['modal']) && $_GET['modal'] === 'add';
if (isset($_GET['edit'])) $editUser = fetchOne("SELECT * FROM users WHERE id=?", 'i', (int)$_GET['edit']);

// Fetch current logged-in user fresh for avatar display
$current = fetchOne("SELECT * FROM users WHERE id=?", 'i', (int)$_SESSION['user_id']);
if ($current) {
    $_SESSION['user_avatar']     = $current['avatar'];
    $_SESSION['user_avatar_path']= $current['avatar_path'];
}
?>

<div class="filters">
  <a class="filter-btn active" href="app.php?page=users">All Users</a>
  <?php foreach(['Admin','Pharmacist','Staff'] as $r):
    $cnt = fetchOne("SELECT COUNT(*) c FROM users WHERE role=?", 's', $r)['c'];
  ?>
  <a class="filter-btn" href="app.php?page=users&role=<?= $r ?>"><?= $r ?> (<?= $cnt ?>)</a>
  <?php endforeach; ?>
  <span style="margin-left:auto;font-size:13px;color:var(--text-muted)"><?= count($users) ?> user(s)</span>
</div>

<div class="card">
  <table>
    <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if ($users): ?>
    <?php foreach ($users as $u): ?>
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <?php if (!empty($u['avatar_path'])): ?>
          <img src="uploads/<?= htmlspecialchars($u['avatar_path']) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0">
          <?php else: ?>
          <div class="user-avatar" style="width:36px;height:36px;font-size:13px"><?= htmlspecialchars($u['avatar'] ?? substr($u['name'],0,2)) ?></div>
          <?php endif; ?>
          <div>
            <strong><?= htmlspecialchars($u['name']) ?></strong>
            <?php if ($u['id'] == $_SESSION['user_id']): ?>
            <span class="badge badge-blue" style="font-size:10px;margin-left:6px">You</span>
            <?php endif; ?>
          </div>
        </div>
      </td>
      <td><?= htmlspecialchars($u['email']) ?></td>
      <td>
        <?php
        $rBadge = match($u['role']) {
            'Admin'      => 'badge-red',
            'Pharmacist' => 'badge-green',
            'Staff'      => 'badge-gray',
            default      => 'badge-gray'
        };
        echo "<span class='badge $rBadge'>{$u['role']}</span>";
        ?>
      </td>
      <td style="font-size:12px"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
      <td>
        <a href="app.php?page=users&edit=<?= $u['id'] ?>" class="btn-sm btn-edit">Edit</a>
        <?php if ($u['id'] != $_SESSION['user_id']): ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')">
          <input type="hidden" name="act" value="delete_user">
          <input type="hidden" name="id" value="<?= $u['id'] ?>">
          <button type="submit" class="btn-sm btn-delete">Del</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php else: ?>
    <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted)">No users found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ADD / EDIT USER MODAL -->
<?php if ($showAdd || $editUser): ?>
<div class="modal-overlay show">
  <div class="modal">
    <div class="modal-header">
      <h3><?= $editUser ? 'Edit User' : 'Add New User' ?></h3>
      <a href="app.php?page=users" class="modal-close">×</a>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="act" value="<?= $editUser ? 'edit_user' : 'add_user' ?>">
        <?php if ($editUser): ?><input type="hidden" name="id" value="<?= $editUser['id'] ?>"><?php endif; ?>

        <!-- Avatar Upload -->
        <div style="display:flex;align-items:center;gap:20px;margin-bottom:20px">
          <?php if ($editUser && !empty($editUser['avatar_path'])): ?>
          <img id="avatarPreview" src="uploads/<?= htmlspecialchars($editUser['avatar_path']) ?>" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--border)">
          <?php else: ?>
          <div id="avatarPreview" style="width:72px;height:72px;border-radius:50%;background:var(--teal);display:flex;align-items:center;justify-content:center;font-size:28px;color:#fff;font-weight:700;border:3px solid var(--border)">
            <?= $editUser ? htmlspecialchars($editUser['avatar']) : ($showAdd ? 'U' : '') ?>
          </div>
          <?php endif; ?>
          <div>
            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Profile Picture</label>
            <input type="file" name="avatar" accept="image/*" onchange="previewAvatar(this)" style="font-size:13px">
            <div style="font-size:11px;color:var(--text-muted);margin-top:4px">JPG, PNG, GIF, WebP (max 2MB)</div>
          </div>
        </div>

        <div class="form-field"><label>Full Name *</label><input name="name" required placeholder="John Smith" value="<?= htmlspecialchars($editUser['name'] ?? '') ?>"></div>
        <div class="form-row">
          <div class="form-field"><label>Email *</label><input name="email" type="email" required placeholder="user@drugs4u.co.uk" value="<?= htmlspecialchars($editUser['email'] ?? '') ?>"></div>
          <div class="form-field"><label>Role *</label>
            <select name="role" required>
              <?php foreach(['Admin','Pharmacist','Staff'] as $r): ?>
              <option value="<?= $r ?>" <?= ($editUser['role'] ?? 'Staff')===$r?'selected':'' ?>><?= $r ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-field">
          <label><?= $editUser ? 'New Password (leave blank to keep current)' : 'Password *' ?></label>
          <input name="password" type="password" <?= $showAdd ? 'required' : '' ?> placeholder="<?= $editUser ? 'Enter new password' : 'min 6 characters' ?>">
        </div>
      </div>
      <div class="modal-footer">
        <a href="app.php?page=users" class="btn-outline">Cancel</a>
        <button type="submit" class="btn-action">💾 <?= $editUser ? 'Update User' : 'Create User' ?></button>
      </div>
    </form>
  </div>
</div>
<script>
function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const prev = document.getElementById('avatarPreview');
      if (prev.tagName === 'IMG') { prev.src = e.target.result; }
      else {
        prev.outerHTML = '<img id="avatarPreview" src="'+e.target.result+'" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--border)">';
      }
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
<?php endif; ?>
