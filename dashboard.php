<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db.php';

// Get statistics
$totalCustomers = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS total FROM customers'))['total'] ?: 0;
$totalMedicines = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS total FROM medicines'))['total'] ?: 0;
$totalPrescriptions = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS total FROM prescriptions'))['total'] ?: 0;
$activeRisks = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS total FROM risks WHERE resolved = FALSE'))['total'] ?: 0;
$lowStockCount = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS total FROM medicines WHERE qty <= 10'))['total'] ?: 0;
$todayPrescriptions = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM prescriptions WHERE DATE(date) = CURDATE()"))['total'] ?: 0;

// Get recent prescriptions with real data
$recentPrescriptions = mysqli_query($conn, "
    SELECT p.id, p.date, p.quantity, m.unit, p.status,
           c.first, c.last,
           m.name as medicine_name
    FROM prescriptions p
    JOIN customers c ON p.customer_id = c.id
    JOIN medicines m ON p.medicine_id = m.id
    ORDER BY p.date DESC, p.id DESC
    LIMIT 5
");

// Get low stock items
$lowStockItems = mysqli_query($conn, "
    SELECT name, qty, unit
    FROM medicines
    WHERE qty <= 10
    ORDER BY qty ASC
    LIMIT 5
");

// Get recent customers
$recentCustomers = mysqli_query($conn, "
    SELECT first, last, created_at
    FROM customers
    ORDER BY created_at DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['username']; ?></span>
                    <a href="logout.php" class="btn btn-secondary"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            <nav>
                <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
                <a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a>
                <a href="prescriptions.php"><i class="fas fa-prescription-bottle-medical"></i> Prescriptions</a>
                <a href="alerts.php"><i class="fas fa-exclamation-triangle"></i> Alerts</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="risks.php"><i class="fas fa-exclamation-triangle"></i> Risks</a>
            </nav>
        </header>

        <main>
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $totalCustomers; ?></h3>
                        <p>Total Customers</p>
                        <span class="stat-note">Registered patients</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-pills"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $totalMedicines; ?></h3>
                        <p>Medicines</p>
                        <span class="stat-note">In inventory</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-prescription-bottle-medical"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $totalPrescriptions; ?></h3>
                        <p>Prescriptions</p>
                        <span class="stat-note">Total issued</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $activeRisks; ?></h3>
                        <p>Active Risks</p>
                        <span class="stat-note">Require attention</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $lowStockCount; ?></h3>
                        <p>Low Stock</p>
                        <span class="stat-note">Items ≤ 10 units</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $todayPrescriptions; ?></h3>
                        <p>Today's Rx</p>
                        <span class="stat-note"><?php echo date('d/m/Y'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Recent Prescriptions -->
                <div class="dashboard-panel">
                    <div class="panel-header">
                        <h2><i class="fas fa-prescription-bottle-medical"></i> Recent Prescriptions</h2>
                        <a href="prescriptions.php" class="panel-link">View All</a>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient</th>
                                    <th>Medicine</th>
                                    <th>Quantity</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($rx = mysqli_fetch_assoc($recentPrescriptions)): ?>
                                <tr>
                                    <td><?php echo $rx['id']; ?></td>
                                    <td><?php echo $rx['first'] . ' ' . $rx['last']; ?></td>
                                    <td><?php echo $rx['medicine_name']; ?></td>
                                    <td><?php echo $rx['qty'] . ' ' . $rx['unit']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($rx['date'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($rx['status']); ?>">
                                            <?php echo $rx['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Low Stock Alert & Recent Customers -->
                <div class="dashboard-panels-row">
                    <div class="dashboard-panel">
                        <div class="panel-header">
                            <h2><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h2>
                            <a href="alerts.php" class="panel-link">View All</a>
                        </div>
                        <div class="alert-list">
                            <?php if (mysqli_num_rows($lowStockItems) > 0): ?>
                                <?php while ($item = mysqli_fetch_assoc($lowStockItems)): ?>
                                <div class="alert-item <?php echo $item['qty'] <= 5 ? 'critical' : 'warning'; ?>">
                                    <div class="alert-content">
                                        <strong><?php echo $item['name']; ?></strong>
                                        <span><?php echo $item['qty'] . ' ' . $item['unit']; ?> remaining</span>
                                    </div>
                                    <div class="alert-severity">
                                        <?php echo $item['qty'] <= 5 ? 'Critical' : 'Low'; ?>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="no-alerts">
                                    <i class="fas fa-check-circle"></i>
                                    <p>All medicines are well stocked</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="dashboard-panel">
                        <div class="panel-header">
                            <h2><i class="fas fa-users"></i> Recent Customers</h2>
                            <a href="customers.php" class="panel-link">View All</a>
                        </div>
                        <div class="customer-list">
                            <?php while ($customer = mysqli_fetch_assoc($recentCustomers)): ?>
                            <div class="customer-item">
                                <div class="customer-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="customer-info">
                                    <strong><?php echo $customer['first'] . ' ' . $customer['last']; ?></strong>
                                    <span>Added <?php echo date('d/m/Y', strtotime($customer['created_at'])); ?></span>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-panel">
                    <div class="panel-header">
                        <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                    </div>
                    <div class="quick-actions">
                        <a href="prescriptions.php" class="action-btn">
                            <i class="fas fa-plus"></i>
                            <span>New Prescription</span>
                        </a>
                        <a href="customers.php" class="action-btn">
                            <i class="fas fa-user-plus"></i>
                            <span>Add Customer</span>
                        </a>
                        <a href="inventory.php" class="action-btn">
                            <i class="fas fa-pills"></i>
                            <span>Update Stock</span>
                        </a>
                        <a href="alerts.php" class="action-btn">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>View Alerts</span>
                        </a>
                        <a href="reports.php" class="action-btn">
                            <i class="fas fa-chart-bar"></i>
                            <span>Generate Report</span>
                        </a>
                        <a href="risks.php" class="action-btn">
                            <i class="fas fa-shield-alt"></i>
                            <span>Risk Management</span>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: #3498db;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .stat-content h3 {
            margin: 0;
            font-size: 28px;
            color: #2c3e50;
        }

        .stat-content p {
            margin: 5px 0 0 0;
            color: #7f8c8d;
            font-size: 14px;
        }

        .stat-note {
            font-size: 12px;
            color: #95a5a6;
        }

        .dashboard-content {
            display: grid;
            gap: 30px;
        }

        .dashboard-panel {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .panel-header {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-header h2 {
            margin: 0;
            color: #2c3e50;
        }

        .panel-link {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }

        .dashboard-panels-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .alert-list {
            padding: 0;
        }

        .alert-item {
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .alert-item:last-child {
            border-bottom: none;
        }

        .alert-item.critical {
            background-color: #fee;
        }

        .alert-item.warning {
            background-color: #fff8e1;
        }

        .alert-content strong {
            display: block;
            color: #2c3e50;
        }

        .alert-content span {
            color: #7f8c8d;
            font-size: 14px;
        }

        .alert-severity {
            font-weight: bold;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .alert-item.critical .alert-severity {
            background: #e74c3c;
            color: white;
        }

        .alert-item.warning .alert-severity {
            background: #f39c12;
            color: white;
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

        .customer-list {
            padding: 0;
        }

        .customer-item {
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .customer-item:last-child {
            border-bottom: none;
        }

        .customer-avatar {
            width: 40px;
            height: 40px;
            background: #3498db;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .customer-info strong {
            display: block;
            color: #2c3e50;
        }

        .customer-info span {
            color: #7f8c8d;
            font-size: 14px;
        }

        .quick-actions {
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 20px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .action-btn i {
            font-size: 24px;
        }

        .action-btn span {
            font-weight: 500;
            text-align: center;
        }

        @media (max-width: 768px) {
            .dashboard-panels-row {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }
        }
    </style>
</body>
</html>