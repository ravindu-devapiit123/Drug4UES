<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db.php';

// Get statistics for reports
$customer_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM customers"))['count'];
$medicine_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM medicines"))['count'];
$prescription_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM prescriptions"))['count'];
$alert_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM risks WHERE resolved = FALSE"))['count'];

// Monthly prescription trends (last 6 months)
$monthly_data = mysqli_query($conn, "
    SELECT DATE_FORMAT(date, '%Y-%m') as month, COUNT(*) as count
    FROM prescriptions
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month
");

// Medicine categories distribution
$category_data = mysqli_query($conn, "
    SELECT category, COUNT(*) as count
    FROM medicines
    GROUP BY category
    ORDER BY count DESC
");

// Risk levels distribution
$risk_data = mysqli_query($conn, "
    SELECT level, COUNT(*) as count
    FROM risks
    WHERE resolved = FALSE
    GROUP BY level
");

// Top prescribed medicines
$top_medicines = mysqli_query($conn, "
    SELECT m.name, m.category, SUM(p.qty) as total_qty, COUNT(p.id) as prescription_count
    FROM medicines m
    JOIN prescriptions p ON m.id = p.medicine_id
    GROUP BY m.id, m.name, m.category
    ORDER BY total_qty DESC
    LIMIT 10
");

// Low stock alerts
$low_stock = mysqli_query($conn, "
    SELECT name, category, qty, unit
    FROM medicines
    WHERE qty <= 10
    ORDER BY qty ASC
");

// Recent prescriptions
$recent_prescriptions = mysqli_query($conn, "
    SELECT p.id, p.date, p.quantity, m.unit, p.status,
           c.first, c.last,
           m.name as medicine_name
    FROM prescriptions p
    JOIN customers c ON p.customer_id = c.id
    JOIN medicines m ON p.medicine_id = m.id
    ORDER BY p.date DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - PMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
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
                <a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a>
            </nav>
        </header>

        <main>
            <!-- Key Metrics -->
            <div class="report-metrics">
                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="metric-content">
                        <h3><?php echo $customer_count; ?></h3>
                        <p>Total Customers</p>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="fas fa-pills"></i>
                    </div>
                    <div class="metric-content">
                        <h3><?php echo $medicine_count; ?></h3>
                        <p>Medicines in Stock</p>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="fas fa-prescription-bottle-medical"></i>
                    </div>
                    <div class="metric-content">
                        <h3><?php echo $prescription_count; ?></h3>
                        <p>Total Prescriptions</p>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="metric-content">
                        <h3><?php echo $alert_count; ?></h3>
                        <p>Active Alerts</p>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="charts-row">
                <div class="chart-container">
                    <h3>Monthly Prescription Trends</h3>
                    <canvas id="monthlyChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3>Medicine Categories</h3>
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>

            <div class="charts-row">
                <div class="chart-container">
                    <h3>Risk Distribution</h3>
                    <canvas id="riskChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3>Top Prescribed Medicines</h3>
                    <canvas id="topMedicinesChart"></canvas>
                </div>
            </div>

            <!-- Tables Row -->
            <div class="tables-row">
                <div class="table-section">
                    <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Alerts</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Category</th>
                                    <th>Current Stock</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = mysqli_fetch_assoc($low_stock)): ?>
                                <tr class="<?php echo $item['qty'] <= 5 ? 'alert-row' : 'warning-row'; ?>">
                                    <td><?php echo $item['name']; ?></td>
                                    <td><?php echo $item['category']; ?></td>
                                    <td><?php echo $item['qty'] . ' ' . $item['unit']; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $item['qty'] <= 5 ? 'status-alert' : 'status-warning'; ?>">
                                            <?php echo $item['qty'] <= 5 ? 'Critical' : 'Low'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="table-section">
                    <h3><i class="fas fa-clock"></i> Recent Prescriptions</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Medicine</th>
                                    <th>Quantity</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($rx = mysqli_fetch_assoc($recent_prescriptions)): ?>
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
            </div>

            <!-- Export Options -->
            <div class="export-section">
                <h3>Data Export</h3>
                <div class="export-buttons">
                    <button class="btn btn-primary" onclick="exportReport('pdf')">
                        <i class="fas fa-file-pdf"></i> Export PDF Report
                    </button>
                    <button class="btn btn-secondary" onclick="exportReport('csv')">
                        <i class="fas fa-file-csv"></i> Export CSV Data
                    </button>
                    <button class="btn btn-info" onclick="printReport()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Monthly Prescription Trends Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyData = {
            labels: [<?php
                mysqli_data_seek($monthly_data, 0);
                $labels = [];
                while ($row = mysqli_fetch_assoc($monthly_data)) {
                    $labels[] = "'" . date('M Y', strtotime($row['month'] . '-01')) . "'";
                }
                echo implode(',', $labels);
            ?>],
            datasets: [{
                label: 'Prescriptions',
                data: [<?php
                    mysqli_data_seek($monthly_data, 0);
                    $data = [];
                    while ($row = mysqli_fetch_assoc($monthly_data)) {
                        $data[] = $row['count'];
                    }
                    echo implode(',', $data);
                ?>],
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                tension: 0.4
            }]
        };
        new Chart(monthlyCtx, {
            type: 'line',
            data: monthlyData,
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // Medicine Categories Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryData = {
            labels: [<?php
                mysqli_data_seek($category_data, 0);
                $labels = [];
                while ($row = mysqli_fetch_assoc($category_data)) {
                    $labels[] = "'" . $row['category'] . "'";
                }
                echo implode(',', $labels);
            ?>],
            datasets: [{
                data: [<?php
                    mysqli_data_seek($category_data, 0);
                    $data = [];
                    while ($row = mysqli_fetch_assoc($category_data)) {
                        $data[] = $row['count'];
                    }
                    echo implode(',', $data);
                ?>],
                backgroundColor: [
                    '#3498db', '#e74c3c', '#2ecc71', '#f39c12',
                    '#9b59b6', '#1abc9c', '#34495e', '#e67e22'
                ]
            }]
        };
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: categoryData,
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Risk Distribution Chart
        const riskCtx = document.getElementById('riskChart').getContext('2d');
        const riskData = {
            labels: ['Red', 'Amber', 'Blue'],
            datasets: [{
                label: 'Risks',
                data: [<?php
                    $red = 0; $amber = 0; $blue = 0;
                    mysqli_data_seek($risk_data, 0);
                    while ($row = mysqli_fetch_assoc($risk_data)) {
                        if ($row['level'] == 'red') $red = $row['count'];
                        if ($row['level'] == 'amber') $amber = $row['count'];
                        if ($row['level'] == 'blue') $blue = $row['count'];
                    }
                    echo "$red, $amber, $blue";
                ?>],
                backgroundColor: ['#e74c3c', '#f39c12', '#3498db']
            }]
        };
        new Chart(riskCtx, {
            type: 'bar',
            data: riskData,
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Top Medicines Chart
        const topMedicinesCtx = document.getElementById('topMedicinesChart').getContext('2d');
        const topMedicinesData = {
            labels: [<?php
                mysqli_data_seek($top_medicines, 0);
                $labels = [];
                while ($row = mysqli_fetch_assoc($top_medicines)) {
                    $labels[] = "'" . substr($row['name'], 0, 15) . "...'";
                }
                echo implode(',', $labels);
            ?>],
            datasets: [{
                label: 'Total Quantity Prescribed',
                data: [<?php
                    mysqli_data_seek($top_medicines, 0);
                    $data = [];
                    while ($row = mysqli_fetch_assoc($top_medicines)) {
                        $data[] = $row['total_qty'];
                    }
                    echo implode(',', $data);
                ?>],
                backgroundColor: '#2ecc71'
            }]
        };
        new Chart(topMedicinesCtx, {
            type: 'bar',
            data: topMedicinesData,
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        function exportReport(type) {
            if (type === 'pdf') {
                alert('PDF export functionality would be implemented here');
            } else if (type === 'csv') {
                alert('CSV export functionality would be implemented here');
            }
        }

        function printReport() {
            window.print();
        }
    </script>

    <style>
        .report-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .metric-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .metric-icon {
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

        .metric-content h3 {
            margin: 0;
            font-size: 28px;
            color: #2c3e50;
        }

        .metric-content p {
            margin: 5px 0 0 0;
            color: #7f8c8d;
            font-size: 14px;
        }

        .charts-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .chart-container h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #2c3e50;
        }

        .tables-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .table-section h3 {
            margin-bottom: 15px;
            color: #2c3e50;
        }

        .alert-row {
            background-color: #fee;
        }

        .warning-row {
            background-color: #fff8e1;
        }

        .export-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .export-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        @media print {
            nav, .actions-bar, .export-section {
                display: none;
            }
        }
    </style>
</body>
</html>