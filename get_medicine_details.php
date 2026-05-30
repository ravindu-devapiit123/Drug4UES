<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db.php';

$id = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['json'])) {
    // Return JSON for edit modal
    $stmt = mysqli_prepare($conn, "SELECT * FROM medicines WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $medicine = mysqli_fetch_assoc($result);

    header('Content-Type: application/json');
    echo json_encode($medicine);
    exit();
}

// Return HTML for view modal
$stmt = mysqli_prepare($conn, "SELECT * FROM medicines WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$medicine = mysqli_fetch_assoc($result);

if (!$medicine) {
    echo "<p>Medicine not found.</p>";
    exit();
}

// Get related prescriptions
$prescriptions_query = mysqli_prepare($conn, "
    SELECT p.*, c.first, c.last
    FROM prescriptions p
    JOIN customers c ON p.customer_id = c.id
    WHERE p.medicine_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
");
mysqli_stmt_bind_param($prescriptions_query, "i", $id);
mysqli_stmt_execute($prescriptions_query);
$prescriptions_result = mysqli_stmt_get_result($prescriptions_query);
?>

<div class="medicine-detail-grid">
    <div class="detail-section">
        <h3><i class="fas fa-pills"></i> Medicine Information</h3>
        <div class="detail-row">
            <span class="label">Name:</span>
            <span class="value"><?php echo $medicine['name']; ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Category:</span>
            <span class="value"><?php echo $medicine['category']; ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Stock Quantity:</span>
            <span class="value <?php echo $medicine['qty'] <= 5 ? 'low-stock' : ''; ?>"><?php echo $medicine['qty']; ?> <?php echo $medicine['unit']; ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Unit:</span>
            <span class="value"><?php echo $medicine['unit']; ?></span>
        </div>
    </div>

    <div class="detail-section">
        <h3><i class="fas fa-info-circle"></i> Additional Details</h3>
        <div class="detail-row">
            <span class="label">Expiry Date:</span>
            <span class="value <?php echo strtotime($medicine['expiry']) < time() ? 'expired' : (strtotime($medicine['expiry']) < strtotime('+30 days') ? 'expiring-soon' : ''); ?>">
                <?php echo date('d/m/Y', strtotime($medicine['expiry'])); ?>
                <?php if (strtotime($medicine['expiry']) < time()): ?>
                    <span class="status-badge status-expired">EXPIRED</span>
                <?php elseif (strtotime($medicine['expiry']) < strtotime('+30 days')): ?>
                    <span class="status-badge status-warning">EXPIRING SOON</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="detail-row">
            <span class="label">Supplier:</span>
            <span class="value"><?php echo $medicine['supplier'] ?: '-'; ?></span>
        </div>
        <div class="detail-row">
            <span class="label">ID Check Required:</span>
            <span class="value"><?php echo $medicine['id_check'] ? '<span class="status-badge status-alert">YES</span>' : 'No'; ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Price:</span>
            <span class="value">£<?php echo number_format($medicine['price'], 2); ?></span>
        </div>
    </div>

    <div class="detail-section full-width">
        <h3><i class="fas fa-prescription-bottle-medical"></i> Recent Prescriptions</h3>
        <?php if (mysqli_num_rows($prescriptions_result) > 0): ?>
            <div class="prescription-list">
                <?php while ($prescription = mysqli_fetch_assoc($prescriptions_result)): ?>
                    <div class="prescription-item">
                        <div class="prescription-header">
                            <strong><?php echo $prescription['id']; ?> - <?php echo $prescription['first'] . ' ' . $prescription['last']; ?></strong>
                            <span class="prescription-date"><?php echo date('d/m/Y', strtotime($prescription['created_at'])); ?></span>
                        </div>
                        <div class="prescription-details">
                            <span>Quantity: <?php echo $prescription['quantity']; ?></span>
                            <span>Dosage: <?php echo $prescription['dosage']; ?></span>
                            <span>Instructions: <?php echo $prescription['instructions']; ?></span>
                            <span class="status-badge status-<?php echo strtolower($prescription['status']); ?>"><?php echo $prescription['status']; ?></span>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p>No prescriptions found for this medicine.</p>
        <?php endif; ?>
    </div>
</div>

<style>
.medicine-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.detail-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.detail-section.full-width {
    grid-column: 1 / -1;
}

.detail-section h3 {
    margin: 0 0 15px 0;
    color: #495057;
    font-size: 16px;
    border-bottom: 2px solid #007bff;
    padding-bottom: 5px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding: 5px 0;
    border-bottom: 1px solid #e9ecef;
}

.detail-row:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.label {
    font-weight: 600;
    color: #495057;
}

.value {
    color: #212529;
    text-align: right;
}

.value.low-stock {
    color: #dc3545;
    font-weight: bold;
}

.value.expired {
    color: #dc3545;
}

.value.expiring-soon {
    color: #fd7e14;
}

.prescription-list {
    max-height: 300px;
    overflow-y: auto;
}

.prescription-item {
    background: white;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

.prescription-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.prescription-date {
    color: #6c757d;
    font-size: 14px;
}

.prescription-details {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    font-size: 14px;
    color: #495057;
}

@media (max-width: 768px) {
    .medicine-detail-grid {
        grid-template-columns: 1fr;
    }

    .detail-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .value {
        text-align: left;
        margin-top: 5px;
    }
}
</style>
            echo "<h3>Active Risks</h3>";
            echo "<p class='warning'><strong>Active Risk Alerts:</strong> $risk_count</p>";
            echo "</div>";
        }

        echo "</div>";
    } else {
        echo "<p>Medicine not found.</p>";
    }
}
?>