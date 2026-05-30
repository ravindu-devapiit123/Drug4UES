<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db.php';

// Handle customer actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_customer'])) {
        $first = $_POST['first'];
        $last = $_POST['last'];
        $dob = $_POST['dob'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $address = $_POST['address'];
        $nhs = $_POST['nhs'];
        $gp = $_POST['gp'];
        $allergies = $_POST['allergies'];
        $conditions = $_POST['conditions'];
        $last_visit = $_POST['last_visit'];

        mysqli_query($conn, "INSERT INTO customers (first, last, dob, phone, email, address, nhs, gp, allergies, conditions, last_visit) VALUES ('$first', '$last', '$dob', '$phone', '$email', '$address', '$nhs', '$gp', '$allergies', '$conditions', '$last_visit')");
        header("Location: customers.php");
        exit();
    }

    if (isset($_POST['update_customer'])) {
        $id = $_POST['customer_id'];
        $first = $_POST['first'];
        $last = $_POST['last'];
        $dob = $_POST['dob'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $address = $_POST['address'];
        $nhs = $_POST['nhs'];
        $gp = $_POST['gp'];
        $allergies = $_POST['allergies'];
        $conditions = $_POST['conditions'];
        $last_visit = $_POST['last_visit'];
        $status = $_POST['status'];

        mysqli_query($conn, "UPDATE customers SET first='$first', last='$last', dob='$dob', phone='$phone', email='$email', address='$address', nhs='$nhs', gp='$gp', allergies='$allergies', conditions='$conditions', last_visit='$last_visit', status='$status' WHERE id=$id");
        header("Location: customers.php");
        exit();
    }

    if (isset($_POST['delete_customer'])) {
        $id = $_POST['customer_id'];
        mysqli_query($conn, "DELETE FROM customers WHERE id=$id");
        header("Location: customers.php");
        exit();
    }
}

// Get customers with filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT * FROM customers WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $query .= " AND (first LIKE ? OR last LIKE ? OR email LIKE ? OR phone LIKE ? OR nhs LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= 'sssss';
}

if ($status_filter) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$query .= " ORDER BY last ASC, first ASC";

if ($params) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - PMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <h1><i class="fas fa-users"></i> Customer Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['username']; ?></span>
                    <a href="logout.php" class="btn btn-secondary"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            <nav>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="customers.php" class="active"><i class="fas fa-users"></i> Customers</a>
                <a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a>
                <a href="prescriptions.php"><i class="fas fa-prescription-bottle-medical"></i> Prescriptions</a>
                <a href="alerts.php"><i class="fas fa-exclamation-triangle"></i> Alerts</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="risks.php"><i class="fas fa-exclamation-triangle"></i> Risks</a>
            </nav>
        </header>

        <main>
            <div class="actions-bar">
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Add Customer
                </button>
                <div class="search-filter">
                    <input type="text" id="searchInput" placeholder="Search customers..." onkeyup="filterCustomers()" value="<?php echo htmlspecialchars($search); ?>">
                    <select id="statusFilter" onchange="filterCustomers()">
                        <option value="">All Status</option>
                        <option value="Active" <?php echo $status_filter == 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo $status_filter == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="Alert" <?php echo $status_filter == 'Alert' ? 'selected' : ''; ?>>Alert</option>
                    </select>
                </div>
            </div>

            <div class="customer-stats">
                <?php
                $stats = mysqli_query($conn, "
                    SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN status = 'Alert' THEN 1 ELSE 0 END) as alerts,
                        AVG(TIMESTAMPDIFF(YEAR, dob, CURDATE())) as avg_age
                    FROM customers
                ");
                $stat = mysqli_fetch_assoc($stats);
                ?>
                <div class="stat-card">
                    <h3><?php echo $stat['total']; ?></h3>
                    <p>Total Customers</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stat['active']; ?></h3>
                    <p>Active Patients</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stat['alerts']; ?></h3>
                    <p>Alert Cases</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo round($stat['avg_age']); ?></h3>
                    <p>Average Age</p>
                </div>
            </div>

            <div class="table-container">
                <table id="customersTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>DOB</th>
                            <th>NHS Number</th>
                            <th>GP</th>
                            <th>Last Visit</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="status-<?php echo strtolower($row['status']); ?>">
                            <td><?php echo $row['first'] . ' ' . $row['last']; ?></td>
                            <td><?php echo $row['phone']; ?></td>
                            <td><?php echo $row['email'] ?: '-'; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['dob'])); ?></td>
                            <td><?php echo $row['nhs'] ?: '-'; ?></td>
                            <td><?php echo $row['gp'] ?: '-'; ?></td>
                            <td><?php echo $row['last_visit'] ? date('d/m/Y', strtotime($row['last_visit'])) : '-'; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="viewCustomer(<?php echo $row['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="editCustomer(<?php echo $row['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteCustomer(<?php echo $row['id']; ?>, '<?php echo addslashes($row['first'] . ' ' . $row['last']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Add Customer Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h2><i class="fas fa-plus"></i> Add New Customer</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first">First Name:</label>
                        <input type="text" name="first" required>
                    </div>
                    <div class="form-group">
                        <label for="last">Last Name:</label>
                        <input type="text" name="last" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="dob">Date of Birth:</label>
                        <input type="date" name="dob" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone:</label>
                        <input type="text" name="phone" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" name="email">
                    </div>
                    <div class="form-group">
                        <label for="nhs">NHS Number:</label>
                        <input type="text" name="nhs">
                    </div>
                </div>
                <div class="form-group">
                    <label for="address">Address:</label>
                    <textarea name="address" rows="2"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="gp">GP:</label>
                        <select name="gp">
                            <option value="">Select GP</option>
                            <option value="Dr. Sarah Williams">Dr. Sarah Williams</option>
                            <option value="Dr. James Patel">Dr. James Patel</option>
                            <option value="Dr. Mark Thompson">Dr. Mark Thompson</option>
                            <option value="Dr. Emily Chen">Dr. Emily Chen</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="last_visit">Last Visit:</label>
                        <input type="date" name="last_visit">
                    </div>
                </div>
                <div class="form-group">
                    <label for="allergies">Allergies:</label>
                    <textarea name="allergies" rows="2" placeholder="List any known allergies"></textarea>
                </div>
                <div class="form-group">
                    <label for="conditions">Medical Conditions:</label>
                    <textarea name="conditions" rows="2" placeholder="List any medical conditions"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="add_customer" class="btn btn-primary">Add Customer</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Customer</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="customer_id" id="edit_customer_id">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_first">First Name:</label>
                        <input type="text" name="first" id="edit_first" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_last">Last Name:</label>
                        <input type="text" name="last" id="edit_last" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_dob">Date of Birth:</label>
                        <input type="date" name="dob" id="edit_dob" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_phone">Phone:</label>
                        <input type="text" name="phone" id="edit_phone" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_email">Email:</label>
                        <input type="email" name="email" id="edit_email">
                    </div>
                    <div class="form-group">
                        <label for="edit_nhs">NHS Number:</label>
                        <input type="text" name="nhs" id="edit_nhs">
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_address">Address:</label>
                    <textarea name="address" id="edit_address" rows="2"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_gp">GP:</label>
                        <select name="gp" id="edit_gp">
                            <option value="">Select GP</option>
                            <option value="Dr. Sarah Williams">Dr. Sarah Williams</option>
                            <option value="Dr. James Patel">Dr. James Patel</option>
                            <option value="Dr. Mark Thompson">Dr. Mark Thompson</option>
                            <option value="Dr. Emily Chen">Dr. Emily Chen</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_last_visit">Last Visit:</label>
                        <input type="date" name="last_visit" id="edit_last_visit">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_status">Status:</label>
                        <select name="status" id="edit_status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Alert">Alert</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_allergies">Allergies:</label>
                    <textarea name="allergies" id="edit_allergies" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_conditions">Medical Conditions:</label>
                    <textarea name="conditions" id="edit_conditions" rows="2"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="update_customer" class="btn btn-primary">Update Customer</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Customer Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-trash"></i> Delete Customer</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <p>Are you sure you want to delete <strong id="delete_customer_name"></strong>?</p>
            <p class="warning">This action cannot be undone and will also delete all associated prescriptions.</p>
            <form method="POST">
                <input type="hidden" name="customer_id" id="delete_customer_id">
                <div class="form-actions">
                    <button type="submit" name="delete_customer" class="btn btn-danger">Delete Customer</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Customer Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h2><i class="fas fa-eye"></i> Customer Details</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="customerDetails"></div>
        </div>
    </div>

    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function editCustomer(id) {
            // Fetch customer data and populate edit modal
            fetch('get_customer_details.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_customer_id').value = data.id;
                    document.getElementById('edit_first').value = data.first;
                    document.getElementById('edit_last').value = data.last;
                    document.getElementById('edit_dob').value = data.dob;
                    document.getElementById('edit_phone').value = data.phone;
                    document.getElementById('edit_email').value = data.email;
                    document.getElementById('edit_nhs').value = data.nhs;
                    document.getElementById('edit_address').value = data.address;
                    document.getElementById('edit_gp').value = data.gp;
                    document.getElementById('edit_last_visit').value = data.last_visit;
                    document.getElementById('edit_status').value = data.status;
                    document.getElementById('edit_allergies').value = data.allergies;
                    document.getElementById('edit_conditions').value = data.conditions;
                    document.getElementById('editModal').style.display = 'block';
                });
        }

        function deleteCustomer(id, name) {
            document.getElementById('delete_customer_id').value = id;
            document.getElementById('delete_customer_name').textContent = name;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function viewCustomer(id) {
            // AJAX call to get customer details
            fetch('get_customer_details.php?id=' + id)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('customerDetails').innerHTML = data;
                    document.getElementById('viewModal').style.display = 'block';
                });
        }

        function closeModal() {
            document.querySelectorAll('.modal').forEach(modal => modal.style.display = 'none');
        }

        function filterCustomers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const table = document.getElementById('customersTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                const status = row.cells[7].textContent.trim();

                const matchesSearch = text.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;

                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customers - Drugs 4U PMS</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="dashboard-shell">
    <aside class="sidebar">
      <div class="sidebar-brand">
        <div class="sidebar-logo">Drugs<span>4U</span></div>
        <div class="sidebar-title">Pharmacy PMS</div>
      </div>
      <nav class="sidebar-nav">
        <a class="nav-link" href="dashboard.php">Dashboard</a>
        <a class="nav-link active" href="customers.php">Customers</a>
        <a class="nav-link" href="inventory.php">Inventory</a>
        <a class="nav-link" href="alerts.php">Alerts</a>
      </nav>
      <div class="sidebar-footer">
        <div class="sidebar-user">Logged in as</div>
        <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
        <a class="logout-link" href="logout.php">Log Out</a>
      </div>
    </aside>

    <main class="dashboard-main customers-page">
      <div class="content-header">
        <div>
          <p class="eyebrow">Customers</p>
          <h1>All customers</h1>
          <p class="subtext">Search and manage customers with real data from your customer table.</p>
        </div>
        <a class="primary-btn" href="#add-customer">Add New Customer</a>
      </div>

      <section class="content-panel">
        <div class="table-toolbar">
          <form method="get" class="search-form">
            <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, email, phone">
            <button type="submit">Search</button>
          </form>
        </div>

        <div class="table-card">
          <div class="panel-header">
            <h2>Customer Directory</h2>
            <a class="panel-link" href="#add-customer">New Customer</a>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Age</th>
                  <th>Last Visit</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (mysqli_num_rows($result) > 0) : ?>
                  <?php while ($row = mysqli_fetch_assoc($result)) : ?>
                    <tr>
                      <td><?php echo htmlspecialchars($row['name']); ?></td>
                      <td><?php echo htmlspecialchars($row['email']); ?></td>
                      <td><?php echo htmlspecialchars($row['phone']); ?></td>
                      <td><?php echo htmlspecialchars($row['age']); ?></td>
                      <td><?php echo htmlspecialchars($row['last_visit']); ?></td>
                      <td>
                        <a class="action-btn view" href="#">View</a>
                        <a class="action-btn edit" href="#">Edit</a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else : ?>
                  <tr>
                    <td colspan="6" class="empty-state">No customers found.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="content-panel recent-panel">
        <div class="panel-header">
          <h2>Recent Customers</h2>
          <a class="panel-link" href="#add-customer">Add Customer</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Age</th>
                <th>Last Visit</th>
              </tr>
            </thead>
            <tbody>
              <?php if (mysqli_num_rows($recentResult) > 0) : ?>
                <?php while ($row = mysqli_fetch_assoc($recentResult)) : ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                    <td><?php echo htmlspecialchars($row['age']); ?></td>
                    <td><?php echo htmlspecialchars($row['last_visit']); ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else : ?>
                <tr>
                  <td colspan="5" class="empty-state">No customers have been added yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="content-panel" id="add-customer">
        <div class="panel-header">
          <h2>Add New Customer</h2>
          <span class="panel-note">Enter valid customer details to store in the database.</span>
        </div>
        <?php if (isset($_GET['success']) && $_GET['success'] == '1') : ?>
          <div class="message success">Customer added successfully.</div>
        <?php endif; ?>
        <?php if ($message) : ?>
          <div class="message error"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="post" class="customer-form">
          <div class="grid-2">
            <div class="form-control">
              <label for="name">Name</label>
              <input type="text" id="name" name="name" required>
            </div>
            <div class="form-control">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" required>
            </div>
          </div>
          <div class="grid-3">
            <div class="form-control">
              <label for="phone">Phone</label>
              <input type="text" id="phone" name="phone" required>
            </div>
            <div class="form-control">
              <label for="age">Age</label>
              <input type="number" id="age" name="age" min="1" required>
            </div>
            <div class="form-control">
              <label for="last_visit">Last Visit</label>
              <input type="date" id="last_visit" name="last_visit" required>
            </div>
          </div>
          <button type="submit" class="login-btn">Save Customer</button>
        </form>
      </section>
    </main>
  </div>
</body>
</html>
