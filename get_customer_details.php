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
    $stmt = mysqli_prepare($conn, "SELECT * FROM customers WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $customer = mysqli_fetch_assoc($result);

    header('Content-Type: application/json');
    echo json_encode($customer);
    exit();
}

// Return HTML for view modal
$stmt = mysqli_prepare($conn, "SELECT * FROM customers WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($result);

if (!$customer) {
    echo "<p>Customer not found.</p>";
    exit();
}

// Get prescriptions for this customer
$prescriptions_query = mysqli_prepare($conn, "
    SELECT p.*, m.name as medicine_name, m.dosage
    FROM prescriptions p
    JOIN medicines m ON p.medicine_id = m.id
    WHERE p.customer_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
");
mysqli_stmt_bind_param($prescriptions_query, "i", $id);
mysqli_stmt_execute($prescriptions_query);
$prescriptions_result = mysqli_stmt_get_result($prescriptions_query);
?>

<div class="customer-detail-grid">
    <div class="detail-section">
        <h3><i class="fas fa-user"></i> Personal Information</h3>
        <div class="detail-row">
            <span class="label">Name:</span>
            <span class="value"><?php echo $customer['first'] . ' ' . $customer['last']; ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Date of Birth:</span>
            <span class="value"><?php echo date('d/m/Y', strtotime($customer['dob'])); ?> (<?php echo date_diff(date_create($customer['dob']), date_create('today'))->y; ?> years old)</span>
        </div>
        <div class="detail-row">
            <span class="label">Phone:</span>
            <span class="value"><?php echo $customer['phone']; ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Email:</span>
            <span class="value"><?php echo $customer['email'] ?: '-'; ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Address:</span>
            <span class="value"><?php echo $customer['address'] ?: '-'; ?></span>
        </div>
    </div>

    <div class="detail-section">
        <h3><i class="fas fa-stethoscope"></i> Medical Information</h3>
        <div class="detail-row">
            <span class="label">NHS Number:</span>
            <span class="value"><?php echo $customer['nhs'] ?: '-'; ?></span>
        </div>
        <div class="detail-row">
            <span class="label">GP:</span>
            <span class="value"><?php echo $customer['gp'] ?: '-'; ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Allergies:</span>
            <span class="value"><?php echo $customer['allergies'] ?: 'None recorded'; ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Medical Conditions:</span>
            <span class="value"><?php echo $customer['conditions'] ?: 'None recorded'; ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Status:</span>
            <span class="status-badge status-<?php echo strtolower($customer['status']); ?>"><?php echo $customer['status']; ?></span>
        </div>
    </div>

    <div class="detail-section">
        <h3><i class="fas fa-calendar-alt"></i> Visit Information</h3>
        <div class="detail-row">
            <span class="label">Last Visit:</span>
            <span class="value"><?php echo $customer['last_visit'] ? date('d/m/Y', strtotime($customer['last_visit'])) : 'Never'; ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Days Since Last Visit:</span>
            <span class="value">
                <?php
                if ($customer['last_visit']) {
                    $days = date_diff(date_create($customer['last_visit']), date_create('today'))->days;
                    echo $days . ' days';
                } else {
                    echo 'N/A';
                }
                ?>
            </span>
        </div>
    </div>

    <div class="detail-section full-width">
        <h3><i class="fas fa-prescription-bottle-medical"></i> Recent Prescriptions</h3>
        <?php if (mysqli_num_rows($prescriptions_result) > 0): ?>
            <div class="prescription-list">
                <?php while ($prescription = mysqli_fetch_assoc($prescriptions_result)): ?>
                    <div class="prescription-item">
                        <div class="prescription-header">
                            <strong><?php echo $prescription['medicine_name']; ?> (<?php echo $prescription['dosage']; ?>)</strong>
                            <span class="prescription-date"><?php echo date('d/m/Y', strtotime($prescription['created_at'])); ?></span>
                        </div>
                        <div class="prescription-details">
                            <span>Quantity: <?php echo $prescription['quantity']; ?></span>
                            <span>Instructions: <?php echo $prescription['instructions']; ?></span>
                            <span class="status-badge status-<?php echo strtolower($prescription['status']); ?>"><?php echo $prescription['status']; ?></span>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p>No prescriptions found for this customer.</p>
        <?php endif; ?>
    </div>
</div>

<style>
.customer-detail-grid {
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
    .customer-detail-grid {
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