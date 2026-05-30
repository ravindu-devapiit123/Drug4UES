<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db.php';

// Handle medicine actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_medicine'])) {
        $name = $_POST['name'];
        $category = $_POST['category'];
        $qty = $_POST['qty'];
        $unit = $_POST['unit'];
        $expiry = $_POST['expiry'];
        $supplier = $_POST['supplier'];
        $id_check = isset($_POST['id_check']) ? 1 : 0;
        $price = $_POST['price'];

        mysqli_query($conn, "INSERT INTO medicines (name, category, qty, unit, expiry, supplier, id_check, price) VALUES ('$name', '$category', $qty, '$unit', '$expiry', '$supplier', $id_check, $price)");
        header("Location: inventory.php");
        exit();
    }

    if (isset($_POST['update_stock'])) {
        $id = $_POST['medicine_id'];
        $new_qty = $_POST['new_qty'];
        mysqli_query($conn, "UPDATE medicines SET qty = $new_qty WHERE id = $id");
        header("Location: inventory.php");
        exit();
    }

    if (isset($_POST['delete_medicine'])) {
        $id = $_POST['medicine_id'];
        mysqli_query($conn, "DELETE FROM medicines WHERE id = $id");
        header("Location: inventory.php");
        exit();
    }
}

// Get medicines with stock status
$query = "SELECT *,
          CASE
            WHEN qty = 0 THEN 'Out of Stock'
            WHEN qty <= 5 THEN 'Critical'
            WHEN qty <= 10 THEN 'Low'
            ELSE 'Good'
          END as stock_status
          FROM medicines
          ORDER BY
            CASE
              WHEN qty <= 5 THEN 1
              WHEN qty <= 10 THEN 2
              ELSE 3
            END,
            name ASC";
$result = mysqli_query($conn, $query);

// Get statistics
$stats = mysqli_query($conn, "
    SELECT
        COUNT(*) as total_medicines,
        SUM(qty) as total_stock,
        SUM(CASE WHEN qty = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN qty <= 5 THEN 1 ELSE 0 END) as critical_stock,
        SUM(CASE WHEN qty <= 10 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN id_check = 1 THEN 1 ELSE 0 END) as controlled_meds
    FROM medicines
");
$stat = mysqli_fetch_assoc($stats);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - PMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <h1><i class="fas fa-boxes"></i> Medicine Inventory</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['username']; ?></span>
                    <a href="logout.php" class="btn btn-secondary"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            <nav>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
                <a href="inventory.php" class="active"><i class="fas fa-boxes"></i> Inventory</a>
                <a href="prescriptions.php"><i class="fas fa-prescription-bottle-medical"></i> Prescriptions</a>
                <a href="alerts.php"><i class="fas fa-exclamation-triangle"></i> Alerts</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="risks.php"><i class="fas fa-exclamation-triangle"></i> Risks</a>
            </nav>
        </header>

        <main>
            <div class="actions-bar">
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Add Medicine
                </button>
                <div class="search-filter">
                    <input type="text" id="searchInput" placeholder="Search medicines..." onkeyup="filterMedicines()">
                    <select id="categoryFilter" onchange="filterMedicines()">
                        <option value="">All Categories</option>
                        <option value="Analgesic">Analgesic</option>
                        <option value="Antibiotic">Antibiotic</option>
                        <option value="Antidiabetic">Antidiabetic</option>
                        <option value="Anxiolytic">Anxiolytic</option>
                        <option value="Antihypertensive">Antihypertensive</option>
                        <option value="Bronchodilator">Bronchodilator</option>
                        <option value="Thyroid">Thyroid</option>
                        <option value="Antacid">Antacid</option>
                        <option value="Antidepressant">Antidepressant</option>
                        <option value="Corticosteroid">Corticosteroid</option>
                        <option value="Antihistamine">Antihistamine</option>
                    </select>
                    <select id="stockFilter" onchange="filterMedicines()">
                        <option value="">All Stock Levels</option>
                        <option value="Good">Good</option>
                        <option value="Low">Low</option>
                        <option value="Critical">Critical</option>
                        <option value="Out of Stock">Out of Stock</option>
                    </select>
                </div>
            </div>

            <div class="inventory-stats">
                <div class="stat-card stat-total">
                    <h3><?php echo $stat['total_medicines']; ?></h3>
                    <p>Total Medicines</p>
                </div>
                <div class="stat-card stat-stock">
                    <h3><?php echo $stat['total_stock']; ?></h3>
                    <p>Total Stock Units</p>
                </div>
                <div class="stat-card stat-critical">
                    <h3><?php echo $stat['critical_stock']; ?></h3>
                    <p>Critical Stock</p>
                </div>
                <div class="stat-card stat-controlled">
                    <h3><?php echo $stat['controlled_meds']; ?></h3>
                    <p>Controlled Medicines</p>
                </div>
            </div>

            <div class="table-container">
                <table id="inventoryTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Medicine</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Unit</th>
                            <th>Expiry</th>
                            <th>Supplier</th>
                            <th>ID Check</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="stock-<?php echo strtolower(str_replace(' ', '-', $row['stock_status'])); ?>">
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['name']; ?></td>
                            <td><?php echo $row['category']; ?></td>
                            <td><?php echo $row['qty']; ?></td>
                            <td><?php echo $row['unit']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['expiry'])); ?></td>
                            <td><?php echo $row['supplier']; ?></td>
                            <td><?php echo $row['id_check'] ? '<i class="fas fa-check text-danger"></i>' : '-'; ?></td>
                            <td>£<?php echo number_format($row['price'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['stock_status'])); ?>">
                                    <?php echo $row['stock_status']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="updateStock(<?php echo $row['id']; ?>, <?php echo $row['qty']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-info" onclick="viewMedicine(<?php echo $row['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteMedicine(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>')">
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

    <!-- Add Medicine Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus"></i> Add Medicine</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Medicine Name:</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Category:</label>
                        <select name="category" required>
                            <option value="">Select Category</option>
                            <option value="Analgesic">Analgesic</option>
                            <option value="Antibiotic">Antibiotic</option>
                            <option value="Antidiabetic">Antidiabetic</option>
                            <option value="Anxiolytic">Anxiolytic</option>
                            <option value="Antihypertensive">Antihypertensive</option>
                            <option value="Bronchodilator">Bronchodilator</option>
                            <option value="Thyroid">Thyroid</option>
                            <option value="Antacid">Antacid</option>
                            <option value="Antidepressant">Antidepressant</option>
                            <option value="Corticosteroid">Corticosteroid</option>
                            <option value="Antihistamine">Antihistamine</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="qty">Quantity:</label>
                        <input type="number" name="qty" required min="0">
                    </div>
                    <div class="form-group">
                        <label for="unit">Unit:</label>
                        <select name="unit" required>
                            <option value="tablets">Tablets</option>
                            <option value="capsules">Capsules</option>
                            <option value="vials">Vials</option>
                            <option value="inhalers">Inhalers</option>
                            <option value="bottles">Bottles</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="expiry">Expiry Date:</label>
                        <input type="date" name="expiry" required>
                    </div>
                    <div class="form-group">
                        <label for="supplier">Supplier:</label>
                        <input type="text" name="supplier">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Price (£):</label>
                        <input type="number" name="price" step="0.01" required min="0">
                    </div>
                    <div class="form-group">
                        <label for="id_check">
                            <input type="checkbox" name="id_check" value="1"> ID Check Required
                        </label>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="add_medicine" class="btn btn-primary">Add Medicine</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Stock Modal -->
    <div id="stockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Update Stock</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="medicine_id" id="stock_medicine_id">
                <div class="form-group">
                    <label for="new_qty">New Quantity:</label>
                    <input type="number" name="new_qty" id="new_qty" required min="0">
                </div>
                <div class="form-actions">
                    <button type="submit" name="update_stock" class="btn btn-primary">Update Stock</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Medicine Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-trash"></i> Delete Medicine</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <p>Are you sure you want to delete <strong id="delete_medicine_name"></strong>?</p>
            <p class="warning">This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="medicine_id" id="delete_medicine_id">
                <div class="form-actions">
                    <button type="submit" name="delete_medicine" class="btn btn-danger">Delete Medicine</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Medicine Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-eye"></i> Medicine Details</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="medicineDetails"></div>
        </div>
    </div>

    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function updateStock(id, currentQty) {
            document.getElementById('stock_medicine_id').value = id;
            document.getElementById('new_qty').value = currentQty;
            document.getElementById('stockModal').style.display = 'block';
        }

        function deleteMedicine(id, name) {
            document.getElementById('delete_medicine_id').value = id;
            document.getElementById('delete_medicine_name').textContent = name;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function viewMedicine(id) {
            // AJAX call to get medicine details
            fetch('get_medicine_details.php?id=' + id)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('medicineDetails').innerHTML = data;
                    document.getElementById('viewModal').style.display = 'block';
                });
        }

        function closeModal() {
            document.querySelectorAll('.modal').forEach(modal => modal.style.display = 'none');
        }

        function filterMedicines() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value;
            const stockFilter = document.getElementById('stockFilter').value;
            const table = document.getElementById('inventoryTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const medicineName = row.cells[1].textContent.toLowerCase();
                const category = row.cells[2].textContent;
                const stockStatus = row.cells[9].textContent.trim();

                const matchesSearch = medicineName.includes(searchTerm);
                const matchesCategory = !categoryFilter || category === categoryFilter;
                const matchesStock = !stockFilter || stockStatus === stockFilter;

                row.style.display = matchesSearch && matchesCategory && matchesStock ? '' : 'none';
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
