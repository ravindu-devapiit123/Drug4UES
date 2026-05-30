<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db.php';

// Handle prescription actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_prescription'])) {
        $customer_id = $_POST['cust_id'];
        $medicine_id = $_POST['med_id'];
        $quantity = $_POST['quantity'];
        $dosage = $_POST['dosage'];
        $instructions = $_POST['instructions'];

        // Generate prescription ID
        $result = mysqli_query($conn, "SELECT MAX(CAST(SUBSTRING(id, 3) AS UNSIGNED)) as max_id FROM prescriptions");
        $row = mysqli_fetch_assoc($result);
        $next_id = $row['max_id'] ? $row['max_id'] + 1 : 1000;
        $prescription_id = 'P-' . str_pad($next_id, 4, '0', STR_PAD_LEFT);

        // Check for risks
        $risk_level = 'blue';
        $risk_notes = '';

        // Check allergies
        $customer_query = mysqli_query($conn, "SELECT allergies FROM customers WHERE id = $customer_id");
        $customer = mysqli_fetch_assoc($customer_query);
        $allergies = explode(',', $customer['allergies']);

        $medicine_query = mysqli_query($conn, "SELECT name, id_check FROM medicines WHERE id = $medicine_id");
        $medicine = mysqli_fetch_assoc($medicine_query);

        foreach ($allergies as $allergy) {
            $allergy = trim($allergy);
            if (stripos($medicine['name'], $allergy) !== false && $allergy != 'None') {
                $risk_level = 'red';
                $risk_notes = "⚠️ Patient allergic to $allergy - flagged";
                break;
            }
        }

        // Check ID requirements
        if ($medicine['id_check']) {
            $risk_level = 'red';
            $risk_notes .= ($risk_notes ? ' ' : '') . '⚠️ ID check required for ' . $medicine['name'];
        }

        // Check stock levels
        $stock_query = mysqli_query($conn, "SELECT qty FROM medicines WHERE id = $medicine_id");
        $stock = mysqli_fetch_assoc($stock_query);
        if ($stock['qty'] < $quantity) {
            $risk_level = 'red';
            $risk_notes .= ($risk_notes ? ' ' : '') . '⚠️ Insufficient stock';
        }

        $status = ($risk_level == 'red') ? 'Alert' : 'Pending';

        mysqli_query($conn, "INSERT INTO prescriptions (id, customer_id, medicine_id, quantity, dosage, instructions, status, date) VALUES ('$prescription_id', $customer_id, $medicine_id, $quantity, '$dosage', '$instructions', '$status', CURDATE())");

        if ($risk_level != 'blue') {
            $risk_id = 'R-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
            mysqli_query($conn, "INSERT INTO risks (id, type, level, customer_id, medicine_id, prescription_id, description, date, resolved) VALUES ('$risk_id', 'Prescription Risk', '$risk_level', $customer_id, $medicine_id, '$prescription_id', '$risk_notes', CURDATE(), FALSE)");
        }

        header("Location: prescriptions.php");
        exit();
    }

    if (isset($_POST['update_status'])) {
        $id = $_POST['prescription_id'];
        $status = $_POST['status'];
        mysqli_query($conn, "UPDATE prescriptions SET status = '$status' WHERE id = '$id'");
        header("Location: prescriptions.php");
        exit();
    }
}

// Get prescriptions with customer and medicine info
$query = "SELECT p.*, c.first, c.last, c.phone, m.name as medicine_name, m.category, m.qty as stock_qty
          FROM prescriptions p
          JOIN customers c ON p.customer_id = c.id
          JOIN medicines m ON p.medicine_id = m.id
          ORDER BY p.created_at DESC, p.id DESC";
$result = mysqli_query($conn, $query);

// Get customers and medicines for dropdowns
$customers = mysqli_query($conn, "SELECT id, first, last FROM customers ORDER BY last, first");
$medicines = mysqli_query($conn, "SELECT id, name, category, qty FROM medicines ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions - PMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <h1><i class="fas fa-prescription-bottle-medical"></i> Prescription Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['username']; ?></span>
                    <a href="logout.php" class="btn btn-secondary"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            <nav>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
                <a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a>
                <a href="prescriptions.php" class="active"><i class="fas fa-prescription-bottle-medical"></i> Prescriptions</a>
                <a href="alerts.php"><i class="fas fa-exclamation-triangle"></i> Alerts</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="risks.php"><i class="fas fa-exclamation-triangle"></i> Risks</a>
            </nav>
        </header>

        <main>
            <div class="actions-bar">
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> New Prescription
                </button>
                <div class="search-filter">
                    <input type="text" id="searchInput" placeholder="Search prescriptions..." onkeyup="filterPrescriptions()">
                    <select id="statusFilter" onchange="filterPrescriptions()">
                        <option value="">All Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Completed">Completed</option>
                        <option value="Alert">Alert</option>
                    </select>
                </div>
            </div>

            <div class="prescription-stats">
                <?php
                $stats = mysqli_query($conn, "
                    SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'Alert' THEN 1 ELSE 0 END) as alerts
                    FROM prescriptions
                ");
                $stat = mysqli_fetch_assoc($stats);
                ?>
                <div class="stat-card">
                    <h3><?php echo $stat['total']; ?></h3>
                    <p>Total Prescriptions</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stat['pending']; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stat['completed']; ?></h3>
                    <p>Completed</p>
                </div>
                <div class="stat-card alert">
                    <h3><?php echo $stat['alerts']; ?></h3>
                    <p>Alerts</p>
                </div>
            </div>

            <div class="table-container">
                <table id="prescriptionsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Medicine</th>
                            <th>Dosage</th>
                            <th>Instructions</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="status-<?php echo strtolower($row['status']); ?>">
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['first'] . ' ' . $row['last']; ?><br><small><?php echo $row['phone']; ?></small></td>
                            <td><?php echo $row['medicine_name']; ?><br><small><?php echo $row['category']; ?></small></td>
                            <td><?php echo $row['dosage']; ?></td>
                            <td><?php echo $row['instructions'] ?: '-'; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <select onchange="updateStatus('<?php echo $row['id']; ?>', this.value)" class="status-select">
                                    <option value="Pending" <?php echo $row['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Completed" <?php echo $row['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Alert" <?php echo $row['status'] == 'Alert' ? 'selected' : ''; ?>>Alert</option>
                                </select>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Add Prescription Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h2><i class="fas fa-plus"></i> New Prescription</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="customer_id">Patient:</label>
                        <select name="customer_id" required>
                            <option value="">Select Patient</option>
                            <?php while ($customer = mysqli_fetch_assoc($customers)): ?>
                                <option value="<?php echo $customer['id']; ?>"><?php echo $customer['first'] . ' ' . $customer['last']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="medicine_id">Medicine:</label>
                        <select name="medicine_id" id="medicine_id" required onchange="checkStock()">
                            <option value="">Select Medicine</option>
                            <?php mysqli_data_seek($medicines, 0); while ($medicine = mysqli_fetch_assoc($medicines)): ?>
                                <option value="<?php echo $medicine['id']; ?>" data-stock="<?php echo $medicine['qty']; ?>">
                                    <?php echo $medicine['name']; ?> (<?php echo $medicine['category']; ?>) - Stock: <?php echo $medicine['qty']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity">Quantity:</label>
                        <input type="number" name="quantity" id="quantity" min="1" required onchange="checkStock()">
                    </div>
                    <div class="form-group">
                        <label for="dosage">Dosage:</label>
                        <input type="text" name="dosage" placeholder="e.g., 2 tablets twice daily" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="instructions">Instructions:</label>
                    <textarea name="instructions" rows="3" placeholder="Special instructions or notes"></textarea>
                </div>
                <div id="stockWarning" class="warning-message" style="display: none;">
                    ⚠️ Insufficient stock for selected quantity!
                </div>
                <div class="form-actions">
                    <button type="submit" name="add_prescription" class="btn btn-primary">Create Prescription</button>
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

        function updateStatus(id, status) {
            const formData = new FormData();
            formData.append('prescription_id', id);
            formData.append('status', status);
            formData.append('update_status', '1');

            fetch('prescriptions.php', {
                method: 'POST',
                body: formData
            }).then(() => {
                location.reload();
            });
        }

        function checkStock() {
            const medicineSelect = document.getElementById('medicine_id');
            const quantityInput = document.getElementById('quantity');
            const warning = document.getElementById('stockWarning');

            if (medicineSelect.value && quantityInput.value) {
                const selectedOption = medicineSelect.options[medicineSelect.selectedIndex];
                const stock = parseInt(selectedOption.getAttribute('data-stock'));
                const quantity = parseInt(quantityInput.value);

                if (quantity > stock) {
                    warning.style.display = 'block';
                } else {
                    warning.style.display = 'none';
                }
            }
        }

        function filterPrescriptions() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const table = document.getElementById('prescriptionsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                const status = row.cells[5].textContent.trim();

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
    <title>Prescriptions - PMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <h1><i class="fas fa-prescription-bottle-medical"></i> Prescription Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['username']; ?></span>
                    <a href="logout.php" class="btn btn-secondary"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            <nav>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
                <a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a>
                <a href="prescriptions.php" class="active"><i class="fas fa-prescription-bottle-medical"></i> Prescriptions</a>
                <a href="alerts.php"><i class="fas fa-exclamation-triangle"></i> Alerts</a>
            </nav>
        </header>

        <main>
            <div class="actions-bar">
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> New Prescription
                </button>
                <div class="search-filter">
                    <input type="text" id="searchInput" placeholder="Search prescriptions..." onkeyup="filterPrescriptions()">
                    <select id="statusFilter" onchange="filterPrescriptions()">
                        <option value="">All Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Completed">Completed</option>
                        <option value="Alert">Alert</option>
                    </select>
                </div>
            </div>

            <div class="prescription-stats">
                <?php
                $stats = mysqli_query($conn, "SELECT status, COUNT(*) as count FROM prescriptions GROUP BY status");
                $total = 0;
                while ($stat = mysqli_fetch_assoc($stats)) {
                    $total += $stat['count'];
                    echo "<div class='stat-card stat-{$stat['status']}'>";
                    echo "<h3>{$stat['count']}</h3>";
                    echo "<p>{$stat['status']} Prescriptions</p>";
                    echo "</div>";
                }
                ?>
                <div class="stat-card stat-total">
                    <h3><?php echo $total; ?></h3>
                    <p>Total Prescriptions</p>
                </div>
            </div>

            <div class="table-container">
                <table id="prescriptionsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Medicine</th>
                            <th>Quantity</th>
                            <th>Prescriber</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="status-<?php echo strtolower($row['status']); ?>">
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['first'] . ' ' . $row['last']; ?><br><small><?php echo $row['phone']; ?></small></td>
                            <td><?php echo $row['medicine_name']; ?><br><small><?php echo $row['category']; ?></small></td>
                            <td><?php echo $row['qty'] . ' ' . $row['unit']; ?></td>
                            <td><?php echo $row['prescriber']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['date'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="viewPrescription('<?php echo $row['id']; ?>')">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="editStatus('<?php echo $row['id']; ?>', '<?php echo $row['status']; ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Add Prescription Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus"></i> New Prescription</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="cust_id">Customer:</label>
                        <select name="cust_id" id="cust_id" required>
                            <option value="">Select Customer</option>
                            <?php while ($cust = mysqli_fetch_assoc($customers)): ?>
                            <option value="<?php echo $cust['id']; ?>"><?php echo $cust['first'] . ' ' . $cust['last']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="med_id">Medicine:</label>
                        <select name="med_id" id="med_id" required onchange="updateUnit()">
                            <option value="">Select Medicine</option>
                            <?php mysqli_data_seek($medicines, 0); ?>
                            <?php while ($med = mysqli_fetch_assoc($medicines)): ?>
                            <option value="<?php echo $med['id']; ?>" data-unit="<?php echo $med['qty'] > 0 ? 'tablets' : 'units'; ?>" data-stock="<?php echo $med['qty']; ?>">
                                <?php echo $med['name'] . ' (' . $med['category'] . ') - Stock: ' . $med['qty']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="qty">Quantity:</label>
                        <input type="number" name="qty" id="qty" required min="1">
                    </div>
                    <div class="form-group">
                        <label for="unit">Unit:</label>
                        <input type="text" name="unit" id="unit" required readonly>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="prescriber">Prescriber:</label>
                        <select name="prescriber" required>
                            <option value="">Select Prescriber</option>
                            <option value="Dr. Sarah Williams">Dr. Sarah Williams</option>
                            <option value="Dr. James Patel">Dr. James Patel</option>
                            <option value="Dr. Mark Thompson">Dr. Mark Thompson</option>
                            <option value="Dr. Emily Chen">Dr. Emily Chen</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea name="notes" id="notes" rows="3"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="add_prescription" class="btn btn-primary">Create Prescription</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Update Prescription Status</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="prescription_id" id="edit_prescription_id">
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select name="status" id="edit_status" required>
                        <option value="Pending">Pending</option>
                        <option value="Completed">Completed</option>
                        <option value="Alert">Alert</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Prescription Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-eye"></i> Prescription Details</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="prescriptionDetails"></div>
        </div>
    </div>

    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function editStatus(id, currentStatus) {
            document.getElementById('edit_prescription_id').value = id;
            document.getElementById('edit_status').value = currentStatus;
            document.getElementById('statusModal').style.display = 'block';
        }

        function viewPrescription(id) {
            // AJAX call to get prescription details
            fetch('get_prescription_details.php?id=' + id)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('prescriptionDetails').innerHTML = data;
                    document.getElementById('viewModal').style.display = 'block';
                });
        }

        function closeModal() {
            document.querySelectorAll('.modal').forEach(modal => modal.style.display = 'none');
        }

        function updateUnit() {
            const select = document.getElementById('med_id');
            const unitInput = document.getElementById('unit');
            const selectedOption = select.options[select.selectedIndex];
            if (selectedOption.value) {
                unitInput.value = selectedOption.getAttribute('data-unit') || 'tablets';
            }
        }

        function filterPrescriptions() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const table = document.getElementById('prescriptionsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                const status = row.cells[6].textContent.trim();

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