<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db.php';

$id = $_GET['id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['json'])) {
    // Return JSON for edit modal
    $stmt = mysqli_prepare($conn, "SELECT * FROM risks WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "s", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $risk = mysqli_fetch_assoc($result);

    header('Content-Type: application/json');
    echo json_encode($risk);
    exit();
}

// Return HTML for view modal
$stmt = mysqli_prepare($conn, "SELECT * FROM risks WHERE id = ?");
mysqli_stmt_bind_param($stmt, "s", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$risk = mysqli_fetch_assoc($result);

if (!$risk) {
    echo "<p>Risk not found.</p>";
    exit();
}

// Get related data
$customer = null;
$medicine = null;
$prescription = null;

if ($risk['customer_id']) {
    $customer_query = mysqli_prepare($conn, "SELECT first, last, phone FROM customers WHERE id = ?");
    mysqli_stmt_bind_param($customer_query, "i", $risk['customer_id']);
    mysqli_stmt_execute($customer_query);
    $customer = mysqli_fetch_assoc(mysqli_stmt_get_result($customer_query));
}

if ($risk['medicine_id']) {
    $medicine_query = mysqli_prepare($conn, "SELECT name, category FROM medicines WHERE id = ?");
    mysqli_stmt_bind_param($medicine_query, "i", $risk['medicine_id']);
    mysqli_stmt_execute($medicine_query);
    $medicine = mysqli_fetch_assoc(mysqli_stmt_get_result($medicine_query));
}

if ($risk['prescription_id']) {
    $prescription_query = mysqli_prepare($conn, "SELECT status FROM prescriptions WHERE id = ?");
    mysqli_stmt_bind_param($prescription_query, "s", $risk['prescription_id']);
    mysqli_stmt_execute($prescription_query);
    $prescription = mysqli_fetch_assoc(mysqli_stmt_get_result($prescription_query));
}
?>

<div class="risk-detail-grid">
    <div class="detail-section">
        <h3><i class="fas fa-exclamation-triangle"></i> Risk Information</h3>
        <div class="detail-row">
            <span class="label">Risk ID:</span>
            <span class="value"><?php echo $risk['id']; ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Type:</span>
            <span class="value"><?php echo $risk['type']; ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Level:</span>
            <span class="risk-badge level-<?php echo $risk['level']; ?>"><?php echo ucfirst($risk['level']); ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Status:</span>
            <span class="status-badge <?php echo $risk['resolved'] ? 'status-resolved' : 'status-active'; ?>">
                <?php echo $risk['resolved'] ? 'Resolved' : 'Active'; ?>
            </span>
        </div>
        <div class="detail-row">
            <span class="label">Date:</span>
            <span class="value"><?php echo date('d/m/Y', strtotime($risk['date'])); ?></span>
        </div>
    </div>

    <div class="detail-section">
        <h3><i class="fas fa-info-circle"></i> Related Information</h3>
        <?php if ($customer): ?>
            <div class="detail-row">
                <span class="label">Patient:</span>
                <span class="value"><?php echo $customer['first'] . ' ' . $customer['last']; ?> (<?php echo $customer['phone']; ?>)</span>
            </div>
        <?php endif; ?>

        <?php if ($medicine): ?>
            <div class="detail-row">
                <span class="label">Medicine:</span>
                <span class="value"><?php echo $medicine['name']; ?> (<?php echo $medicine['category']; ?>)</span>
            </div>
        <?php endif; ?>

        <?php if ($prescription): ?>
            <div class="detail-row">
                <span class="label">Prescription:</span>
                <span class="value"><?php echo $risk['prescription_id']; ?> - <?php echo $prescription['status']; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$customer && !$medicine && !$prescription): ?>
            <div class="detail-row">
                <span class="label">Related To:</span>
                <span class="value">General/System Risk</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="detail-section full-width">
        <h3><i class="fas fa-file-alt"></i> Description</h3>
        <div class="description-box">
            <?php echo nl2br(htmlspecialchars($risk['description'])); ?>
        </div>
    </div>

    <?php if (!$risk['resolved']): ?>
    <div class="detail-section full-width">
        <h3><i class="fas fa-tasks"></i> Actions Required</h3>
        <div class="actions-list">
            <?php
            $actions = [];
            switch ($risk['type']) {
                case 'ID Check':
                    $actions = ['Verify patient ID with date of birth', 'Document ID check in patient record', 'Update prescription status if verified'];
                    break;
                case 'Allergy Conflict':
                    $actions = ['Review patient allergy records', 'Contact prescriber for alternative medication', 'Update prescription with safe alternative'];
                    break;
                case 'Low Stock':
                    $actions = ['Contact supplier for reorder', 'Check alternative suppliers', 'Update inventory levels'];
                    break;
                case 'Expiry Alert':
                    $actions = ['Check current stock expiry dates', 'Remove expired stock from inventory', 'Reorder fresh stock'];
                    break;
                case 'Prescription Risk':
                    $actions = ['Review prescription details', 'Verify with prescriber', 'Update risk status based on review'];
                    break;
                default:
                    $actions = ['Review risk details', 'Take appropriate action', 'Document resolution'];
            }

            foreach ($actions as $action): ?>
                <div class="action-item">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $action; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.risk-detail-grid {
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
    border-bottom: 2px solid #dc3545;
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

.description-box {
    background: white;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
    line-height: 1.5;
}

.actions-list {
    background: white;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

.action-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 4px;
}

.action-item:last-child {
    margin-bottom: 0;
}

.action-item i {
    color: #28a745;
    font-size: 14px;
}

.action-item span {
    color: #495057;
    font-size: 14px;
}

@media (max-width: 768px) {
    .risk-detail-grid {
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
        }

        echo "</div>";
    } else {
        echo "<p>Risk not found.</p>";
    }
}
?>