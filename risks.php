<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db.php';

// Handle risk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['resolve_risk'])) {
        $id = $_POST['risk_id'];
        mysqli_query($conn, "UPDATE risks SET resolved = TRUE WHERE id = '$id'");
        header("Location: risks.php");
        exit();
    }

    if (isset($_POST['add_risk'])) {
        $type = $_POST['type'];
        $level = $_POST['level'];
        $customer_id = $_POST['cust_id'] ?: null;
        $medicine_id = $_POST['med_id'] ?: null;
        $prescription_id = $_POST['prescription_id'] ?: null;
        $description = $_POST['description'];

        // Generate risk ID
        $result = mysqli_query($conn, "SELECT MAX(CAST(SUBSTRING(id, 3) AS UNSIGNED)) as max_id FROM risks");
        $row = mysqli_fetch_assoc($result);
        $next_id = $row['max_id'] ? $row['max_id'] + 1 : 1;
        $risk_id = 'R-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);

        mysqli_query($conn, "INSERT INTO risks (id, type, level, customer_id, medicine_id, prescription_id, description, date, resolved) VALUES ('$risk_id', '$type', '$level', " . ($customer_id ? $customer_id : 'NULL') . ", " . ($medicine_id ? $medicine_id : 'NULL') . ", " . ($prescription_id ? "'$prescription_id'" : 'NULL') . ", '$description', CURDATE(), FALSE)");
        header("Location: risks.php");
        exit();
    }
}

// Get risks with related data
$query = "SELECT r.*, c.first, c.last, m.name as medicine_name, p.id as prescription_id
          FROM risks r
          LEFT JOIN customers c ON r.customer_id = c.id
          LEFT JOIN medicines m ON r.medicine_id = m.id
          LEFT JOIN prescriptions p ON r.prescription_id = p.id
          ORDER BY r.resolved ASC, r.date DESC, r.id DESC";
$result = mysqli_query($conn, $query);

// Get dropdown data
$customers = mysqli_query($conn, "SELECT id, first, last FROM customers ORDER BY last, first");
$medicines = mysqli_query($conn, "SELECT id, name FROM medicines ORDER BY name");
$prescriptions = mysqli_query($conn, "SELECT id FROM prescriptions ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risks - PMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <h1><i class="fas fa-exclamation-triangle"></i> Risk Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['username']; ?></span>
                    <a href="logout.php" class="btn btn-secondary"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            <nav>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
                <a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a>
                <a href="prescriptions.php"><i class="fas fa-prescription-bottle-medical"></i> Prescriptions</a>
                <a href="alerts.php"><i class="fas fa-exclamation-triangle"></i> Alerts</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="risks.php" class="active"><i class="fas fa-exclamation-triangle"></i> Risks</a>
            </nav>
        </header>

        <main>
            <div class="actions-bar">
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Add Risk
                </button>
                <div class="search-filter">
                    <input type="text" id="searchInput" placeholder="Search risks..." onkeyup="filterRisks()">
                    <select id="statusFilter" onchange="filterRisks()">
                        <option value="">All Status</option>
                        <option value="0">Active</option>
                        <option value="1">Resolved</option>
                    </select>
                    <select id="levelFilter" onchange="filterRisks()">
                        <option value="">All Levels</option>
                        <option value="red">Red</option>
                        <option value="amber">Amber</option>
                        <option value="blue">Blue</option>
                    </select>
                </div>
            </div>

            <div class="risk-stats">
                <?php
                $stats = mysqli_query($conn, "
                    SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN resolved = 0 THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN level = 'red' AND resolved = 0 THEN 1 ELSE 0 END) as red_alerts,
                        SUM(CASE WHEN level = 'amber' AND resolved = 0 THEN 1 ELSE 0 END) as amber_alerts
                    FROM risks
                ");
                $stat = mysqli_fetch_assoc($stats);
                ?>
                <div class="stat-card">
                    <h3><?php echo $stat['total']; ?></h3>
                    <p>Total Risks</p>
                </div>
                <div class="stat-card alert">
                    <h3><?php echo $stat['active']; ?></h3>
                    <p>Active Risks</p>
                </div>
                <div class="stat-card danger">
                    <h3><?php echo $stat['red_alerts']; ?></h3>
                    <p>Red Alerts</p>
                </div>
                <div class="stat-card warning">
                    <h3><?php echo $stat['amber_alerts']; ?></h3>
                    <p>Amber Alerts</p>
                </div>
            </div>

            <div class="table-container">
                <table id="risksTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Level</th>
                            <th>Patient</th>
                            <th>Medicine</th>
                            <th>Prescription</th>
                            <th>Description</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="level-<?php echo $row['level']; ?> <?php echo $row['resolved'] ? 'resolved' : 'active'; ?>">
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['type']; ?></td>
                            <td>
                                <span class="risk-badge level-<?php echo $row['level']; ?>">
                                    <?php echo ucfirst($row['level']); ?>
                                </span>
                            </td>
                            <td><?php echo $row['first'] && $row['last'] ? $row['first'] . ' ' . $row['last'] : '-'; ?></td>
                            <td><?php echo $row['medicine_name'] ?: '-'; ?></td>
                            <td><?php echo $row['prescription_id'] ?: '-'; ?></td>
                            <td><?php echo $row['description']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['date'])); ?></td>
                            <td>
                                <span class="status-badge <?php echo $row['resolved'] ? 'status-resolved' : 'status-active'; ?>">
                                    <?php echo $row['resolved'] ? 'Resolved' : 'Active'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!$row['resolved']): ?>
                                    <button class="btn btn-sm btn-success" onclick="resolveRisk('<?php echo $row['id']; ?>')">
                                        <i class="fas fa-check"></i> Resolve
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Add Risk Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h2><i class="fas fa-plus"></i> Add New Risk</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="type">Risk Type:</label>
                        <select name="type" required>
                            <option value="">Select Type</option>
                            <option value="ID Check">ID Check</option>
                            <option value="Allergy Conflict">Allergy Conflict</option>
                            <option value="Low Stock">Low Stock</option>
                            <option value="Expiry Alert">Expiry Alert</option>
                            <option value="Prescription Risk">Prescription Risk</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="level">Risk Level:</label>
                        <select name="level" required>
                            <option value="blue">Blue (Low)</option>
                            <option value="amber">Amber (Medium)</option>
                            <option value="red">Red (High)</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="customer_id">Patient (Optional):</label>
                        <select name="customer_id">
                            <option value="">Select Patient</option>
                            <?php while ($customer = mysqli_fetch_assoc($customers)): ?>
                                <option value="<?php echo $customer['id']; ?>"><?php echo $customer['first'] . ' ' . $customer['last']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="medicine_id">Medicine (Optional):</label>
                        <select name="medicine_id">
                            <option value="">Select Medicine</option>
                            <?php mysqli_data_seek($medicines, 0); while ($medicine = mysqli_fetch_assoc($medicines)): ?>
                                <option value="<?php echo $medicine['id']; ?>"><?php echo $medicine['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="prescription_id">Prescription ID (Optional):</label>
                    <select name="prescription_id">
                        <option value="">Select Prescription</option>
                        <?php while ($prescription = mysqli_fetch_assoc($prescriptions)): ?>
                            <option value="<?php echo $prescription['id']; ?>"><?php echo $prescription['id']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea name="description" rows="3" required placeholder="Detailed description of the risk"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="add_risk" class="btn btn-primary">Add Risk</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function closeModal() {
            document.querySelectorAll('.modal').forEach(modal => modal.style.display = 'none');
        }

        function resolveRisk(id) {
            if (confirm('Are you sure you want to mark this risk as resolved?')) {
                const formData = new FormData();
                formData.append('risk_id', id);
                formData.append('resolve_risk', '1');

                fetch('risks.php', {
                    method: 'POST',
                    body: formData
                }).then(() => {
                    location.reload();
                });
            }
        }

        function filterRisks() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const levelFilter = document.getElementById('levelFilter').value;
            const table = document.getElementById('risksTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                const status = row.classList.contains('resolved') ? '1' : '0';
                const level = row.cells[2].textContent.trim().toLowerCase();

                const matchesSearch = text.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;
                const matchesLevel = !levelFilter || level === levelFilter;

                row.style.display = matchesSearch && matchesStatus && matchesLevel ? '' : 'none';
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
$prescriptions = mysqli_query($conn, "SELECT id FROM prescriptions ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risks & Alerts - PMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <h1><i class="fas fa-exclamation-triangle"></i> Risk Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['username']; ?></span>
                    <a href="logout.php" class="btn btn-secondary"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            <nav>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
                <a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a>
                <a href="prescriptions.php"><i class="fas fa-prescription-bottle-medical"></i> Prescriptions</a>
                <a href="alerts.php"><i class="fas fa-exclamation-triangle"></i> Alerts</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="risks.php" class="active"><i class="fas fa-exclamation-triangle"></i> Risks</a>
            </nav>
        </header>

        <main>
            <div class="actions-bar">
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Add Risk Alert
                </button>
                <div class="filter-options">
                    <select id="statusFilter" onchange="filterRisks()">
                        <option value="">All Risks</option>
                        <option value="active">Active Only</option>
                        <option value="resolved">Resolved Only</option>
                    </select>
                    <select id="levelFilter" onchange="filterRisks()">
                        <option value="">All Levels</option>
                        <option value="red">Red</option>
                        <option value="amber">Amber</option>
                        <option value="blue">Blue</option>
                    </select>
                </div>
            </div>

            <div class="risk-stats">
                <?php
                $stats = mysqli_query($conn, "
                    SELECT
                        SUM(CASE WHEN resolved = FALSE THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN resolved = TRUE THEN 1 ELSE 0 END) as resolved,
                        SUM(CASE WHEN level = 'red' AND resolved = FALSE THEN 1 ELSE 0 END) as red_active,
                        SUM(CASE WHEN level = 'amber' AND resolved = FALSE THEN 1 ELSE 0 END) as amber_active,
                        SUM(CASE WHEN level = 'blue' AND resolved = FALSE THEN 1 ELSE 0 END) as blue_active
                    FROM risks
                ");
                $stat = mysqli_fetch_assoc($stats);
                ?>
                <div class="stat-card stat-active">
                    <h3><?php echo $stat['active']; ?></h3>
                    <p>Active Risks</p>
                </div>
                <div class="stat-card stat-resolved">
                    <h3><?php echo $stat['resolved']; ?></h3>
                    <p>Resolved</p>
                </div>
                <div class="stat-card stat-red">
                    <h3><?php echo $stat['red_active']; ?></h3>
                    <p>Red Level</p>
                </div>
                <div class="stat-card stat-amber">
                    <h3><?php echo $stat['amber_active']; ?></h3>
                    <p>Amber Level</p>
                </div>
                <div class="stat-card stat-blue">
                    <h3><?php echo $stat['blue_active']; ?></h3>
                    <p>Blue Level</p>
                </div>
            </div>

            <div class="table-container">
                <table id="risksTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Level</th>
                            <th>Customer</th>
                            <th>Medicine</th>
                            <th>Prescription</th>
                            <th>Description</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="risk-<?php echo $row['level']; ?> <?php echo $row['resolved'] ? 'resolved' : 'active'; ?>">
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['type']; ?></td>
                            <td>
                                <span class="risk-badge risk-<?php echo $row['level']; ?>">
                                    <?php echo ucfirst($row['level']); ?>
                                </span>
                            </td>
                            <td><?php echo $row['first'] && $row['last'] ? $row['first'] . ' ' . $row['last'] : '-'; ?></td>
                            <td><?php echo $row['medicine_name'] ?: '-'; ?></td>
                            <td><?php echo $row['prescription_id'] ?: '-'; ?></td>
                            <td><?php echo $row['description']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['date'])); ?></td>
                            <td>
                                <span class="status-badge <?php echo $row['resolved'] ? 'status-resolved' : 'status-active'; ?>">
                                    <?php echo $row['resolved'] ? 'Resolved' : 'Active'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!$row['resolved']): ?>
                                <button class="btn btn-sm btn-success" onclick="resolveRisk('<?php echo $row['id']; ?>')">
                                    <i class="fas fa-check"></i> Resolve
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-info" onclick="viewRisk('<?php echo $row['id']; ?>')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Add Risk Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus"></i> Add Risk Alert</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="type">Risk Type:</label>
                        <select name="type" required>
                            <option value="">Select Type</option>
                            <option value="Allergy Conflict">Allergy Conflict</option>
                            <option value="ID Check">ID Check</option>
                            <option value="Low Stock">Low Stock</option>
                            <option value="Duplicate Prescription">Duplicate Prescription</option>
                            <option value="Expired Medicine">Expired Medicine</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="level">Risk Level:</label>
                        <select name="level" required>
                            <option value="blue">Blue - Low</option>
                            <option value="amber">Amber - Medium</option>
                            <option value="red">Red - High</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="cust_id">Customer (Optional):</label>
                        <select name="cust_id">
                            <option value="">Select Customer</option>
                            <?php mysqli_data_seek($customers, 0); ?>
                            <?php while ($cust = mysqli_fetch_assoc($customers)): ?>
                            <option value="<?php echo $cust['id']; ?>"><?php echo $cust['first'] . ' ' . $cust['last']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="med_id">Medicine (Optional):</label>
                        <select name="med_id">
                            <option value="">Select Medicine</option>
                            <?php mysqli_data_seek($medicines, 0); ?>
                            <?php while ($med = mysqli_fetch_assoc($medicines)): ?>
                            <option value="<?php echo $med['id']; ?>"><?php echo $med['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="rx_id">Prescription ID (Optional):</label>
                    <select name="rx_id">
                        <option value="">Select Prescription</option>
                        <?php mysqli_data_seek($prescriptions, 0); ?>
                        <?php while ($rx = mysqli_fetch_assoc($prescriptions)): ?>
                        <option value="<?php echo $rx['id']; ?>"><?php echo $rx['id']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea name="description" required rows="4" placeholder="Describe the risk or alert..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="add_risk" class="btn btn-primary">Add Risk Alert</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Resolve Risk Modal -->
    <div id="resolveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-check"></i> Resolve Risk</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <p>Are you sure you want to mark this risk as resolved?</p>
            <form method="POST">
                <input type="hidden" name="risk_id" id="resolve_risk_id">
                <div class="form-actions">
                    <button type="submit" name="resolve_risk" class="btn btn-success">Yes, Resolve</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Risk Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-eye"></i> Risk Details</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="riskDetails"></div>
        </div>
    </div>

    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function resolveRisk(id) {
            document.getElementById('resolve_risk_id').value = id;
            document.getElementById('resolveModal').style.display = 'block';
        }

        function viewRisk(id) {
            // AJAX call to get risk details
            fetch('get_risk_details.php?id=' + id)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('riskDetails').innerHTML = data;
                    document.getElementById('viewModal').style.display = 'block';
                });
        }

        function closeModal() {
            document.querySelectorAll('.modal').forEach(modal => modal.style.display = 'none');
        }

        function filterRisks() {
            const statusFilter = document.getElementById('statusFilter').value;
            const levelFilter = document.getElementById('levelFilter').value;
            const table = document.getElementById('risksTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const isResolved = row.classList.contains('resolved');
                const level = row.cells[2].textContent.trim().toLowerCase();

                const matchesStatus = !statusFilter ||
                    (statusFilter === 'active' && !isResolved) ||
                    (statusFilter === 'resolved' && isResolved);

                const matchesLevel = !levelFilter || level === levelFilter;

                row.style.display = matchesStatus && matchesLevel ? '' : 'none';
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