<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db.php';

// Get active alerts from risks table
$query = "SELECT r.*, c.first, c.last, m.name as medicine_name
          FROM risks r
          LEFT JOIN customers c ON r.customer_id = c.id
          LEFT JOIN medicines m ON r.medicine_id = m.id
          WHERE r.resolved = FALSE
          ORDER BY
            CASE r.level
              WHEN 'red' THEN 1
              WHEN 'amber' THEN 2
              WHEN 'blue' THEN 3
            END,
            r.date DESC";
$result = mysqli_query($conn, $query);

// Get low stock alerts from medicines table
$low_stock = mysqli_query($conn, "
    SELECT name, category, qty, unit,
           CASE
             WHEN qty = 0 THEN 'Out of Stock'
             WHEN qty <= 5 THEN 'Critical'
             ELSE 'Low'
           END as severity
    FROM medicines
    WHERE qty <= 10
    ORDER BY qty ASC
");

// Get expiring medicines (within 3 months)
$expiring = mysqli_query($conn, "
    SELECT name, category, expiry, qty, unit
    FROM medicines
    WHERE expiry <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH) AND expiry >= CURDATE()
    ORDER BY expiry ASC
");

// Get pending prescriptions that need attention
$pending_rx = mysqli_query($conn, "
    SELECT p.id, p.date, c.first, c.last, m.name as medicine_name, p.qty, p.unit
    FROM prescriptions p
    JOIN customers c ON p.customer_id = c.id
    JOIN medicines m ON p.medicine_id = m.id
    WHERE p.status = 'Pending' AND p.date <= CURDATE()
    ORDER BY p.date ASC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerts & Notifications - PMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <h1><i class="fas fa-exclamation-triangle"></i> Alerts & Notifications</h1>
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
                <a href="alerts.php" class="active"><i class="fas fa-exclamation-triangle"></i> Alerts</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="risks.php"><i class="fas fa-exclamation-triangle"></i> Risks</a>
            </nav>
        </header>

        <main>
            <!-- Alert Summary -->
            <div class="alert-summary">
                <?php
                $risk_count = mysqli_num_rows($result);
                $stock_count = mysqli_num_rows($low_stock);
                $expiring_count = mysqli_num_rows($expiring);
                $pending_count = mysqli_num_rows($pending_rx);
                ?>
                <div class="summary-card risk-alerts">
                    <div class="summary-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="summary-content">
                        <h3><?php echo $risk_count; ?></h3>
                        <p>Risk Alerts</p>
                    </div>
                </div>
                <div class="summary-card stock-alerts">
                    <div class="summary-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="summary-content">
                        <h3><?php echo $stock_count; ?></h3>
                        <p>Low Stock Items</p>
                    </div>
                </div>
                <div class="summary-card expiry-alerts">
                    <div class="summary-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div class="summary-content">
                        <h3><?php echo $expiring_count; ?></h3>
                        <p>Expiring Soon</p>
                    </div>
                </div>
                <div class="summary-card pending-alerts">
                    <div class="summary-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="summary-content">
                        <h3><?php echo $pending_count; ?></h3>
                        <p>Pending Prescriptions</p>
                    </div>
                </div>
            </div>

            <!-- Risk Alerts -->
            <div class="alerts-section">
                <h2><i class="fas fa-exclamation-triangle"></i> Risk Alerts</h2>
                <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="alerts-list">
                    <?php while ($alert = mysqli_fetch_assoc($result)): ?>
                    <div class="alert-item alert-<?php echo $alert['level']; ?>">
                        <div class="alert-header">
                            <span class="alert-level"><?php echo ucfirst($alert['level']); ?> Level</span>
                            <span class="alert-date"><?php echo date('d/m/Y', strtotime($alert['date'])); ?></span>
                        </div>
                        <div class="alert-content">
                            <h4><?php echo $alert['type']; ?> - <?php echo $alert['id']; ?></h4>
                            <p><?php echo $alert['description']; ?></p>
                            <?php if ($alert['first'] && $alert['last']): ?>
                            <p><strong>Customer:</strong> <?php echo $alert['first'] . ' ' . $alert['last']; ?></p>
                            <?php endif; ?>
                            <?php if ($alert['medicine_name']): ?>
                            <p><strong>Medicine:</strong> <?php echo $alert['medicine_name']; ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="alert-actions">
                            <a href="risks.php" class="btn btn-sm btn-info">View Details</a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="no-alerts">
                    <i class="fas fa-check-circle"></i>
                    <p>No active risk alerts</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Low Stock Alerts -->
            <div class="alerts-section">
                <h2><i class="fas fa-boxes"></i> Low Stock Alerts</h2>
                <?php if (mysqli_num_rows($low_stock) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Severity</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = mysqli_fetch_assoc($low_stock)): ?>
                            <tr class="stock-<?php echo strtolower($item['severity']); ?>">
                                <td><?php echo $item['name']; ?></td>
                                <td><?php echo $item['category']; ?></td>
                                <td><?php echo $item['qty'] . ' ' . $item['unit']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $item['severity'])); ?>">
                                        <?php echo $item['severity']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="inventory.php" class="btn btn-sm btn-warning">Update Stock</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-alerts">
                    <i class="fas fa-check-circle"></i>
                    <p>All medicines are well stocked</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Expiring Medicines -->
            <div class="alerts-section">
                <h2><i class="fas fa-calendar-times"></i> Medicines Expiring Soon</h2>
                <?php if (mysqli_num_rows($expiring) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Category</th>
                                <th>Expiry Date</th>
                                <th>Current Stock</th>
                                <th>Days Left</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = mysqli_fetch_assoc($expiring)):
                                $days_left = ceil((strtotime($item['expiry']) - time()) / (60*60*24));
                            ?>
                            <tr class="<?php echo $days_left <= 30 ? 'alert-row' : 'warning-row'; ?>">
                                <td><?php echo $item['name']; ?></td>
                                <td><?php echo $item['category']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($item['expiry'])); ?></td>
                                <td><?php echo $item['qty'] . ' ' . $item['unit']; ?></td>
                                <td><?php echo $days_left; ?> days</td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-alerts">
                    <i class="fas fa-check-circle"></i>
                    <p>No medicines expiring in the next 3 months</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pending Prescriptions -->
            <div class="alerts-section">
                <h2><i class="fas fa-clock"></i> Pending Prescriptions</h2>
                <?php if (mysqli_num_rows($pending_rx) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Medicine</th>
                                <th>Quantity</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($rx = mysqli_fetch_assoc($pending_rx)): ?>
                            <tr>
                                <td><?php echo $rx['id']; ?></td>
                                <td><?php echo $rx['first'] . ' ' . $rx['last']; ?></td>
                                <td><?php echo $rx['medicine_name']; ?></td>
                                <td><?php echo $rx['qty'] . ' ' . $rx['unit']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($rx['date'])); ?></td>
                                <td>
                                    <a href="prescriptions.php" class="btn btn-sm btn-success">Process</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-alerts">
                    <i class="fas fa-check-circle"></i>
                    <p>No pending prescriptions</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <style>
        .alert-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .risk-alerts .summary-icon { background: #e74c3c; }
        .stock-alerts .summary-icon { background: #f39c12; }
        .expiry-alerts .summary-icon { background: #3498db; }
        .pending-alerts .summary-icon { background: #9b59b6; }

        .summary-content h3 {
            margin: 0;
            font-size: 28px;
            color: #2c3e50;
        }

        .summary-content p {
            margin: 5px 0 0 0;
            color: #7f8c8d;
            font-size: 14px;
        }

        .alerts-section {
            background: white;
            margin-bottom: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .alerts-section h2 {
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            color: #2c3e50;
        }

        .alerts-list {
            padding: 0;
        }

        .alert-item {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .alert-item:last-child {
            border-bottom: none;
        }

        .alert-red { border-left: 4px solid #e74c3c; }
        .alert-amber { border-left: 4px solid #f39c12; }
        .alert-blue { border-left: 4px solid #3498db; }

        .alert-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .alert-level {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        .alert-red .alert-level { background: #e74c3c; color: white; }
        .alert-amber .alert-level { background: #f39c12; color: white; }
        .alert-blue .alert-level { background: #3498db; color: white; }

        .alert-date {
            color: #7f8c8d;
            font-size: 14px;
        }

        .alert-content h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }

        .alert-content p {
            margin: 5px 0;
            color: #555;
        }

        .alert-actions {
            margin-top: 10px;
        }

        .no-alerts {
            padding: 40px 20px;
            text-align: center;
            color: #27ae60;
        }

        .no-alerts i {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .alert-row {
            background-color: #fee;
        }

        .warning-row {
            background-color: #fff8e1;
        }

        .stock-critical {
            background-color: #fee;
        }

        .stock-low {
            background-color: #fff8e1;
        }

        .stock-out-of-stock {
            background-color: #f8d7da;
        }
    </style>
</body>
</html>
