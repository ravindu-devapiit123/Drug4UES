<?php
include 'db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $query = "SELECT p.*, c.first, c.last, c.phone, c.email, c.dob, c.address, c.nhs, c.gp, c.allergies, c.conditions,
                     m.name as medicine_name, m.category, m.qty as stock_qty, m.expiry, m.supplier, m.id_check, m.price
              FROM prescriptions p
              JOIN customers c ON p.customer_id = c.id
              JOIN medicines m ON p.medicine_id = m.id
              WHERE p.id = '$id'";

    $result = mysqli_query($conn, $query);
    $prescription = mysqli_fetch_assoc($result);

    if ($prescription) {
        echo "<div class='prescription-detail'>";
        echo "<div class='detail-section'>";
        echo "<h3>Prescription Information</h3>";
        echo "<p><strong>ID:</strong> {$prescription['id']}</p>";
        echo "<p><strong>Date:</strong> " . date('d/m/Y', strtotime($prescription['date'])) . "</p>";
        echo "<p><strong>Prescriber:</strong> {$prescription['prescriber']}</p>";
        echo "<p><strong>Status:</strong> <span class='status-badge status-" . strtolower($prescription['status']) . "'>{$prescription['status']}</span></p>";
        echo "<p><strong>Quantity:</strong> {$prescription['qty']} {$prescription['unit']}</p>";
        if ($prescription['notes']) {
            echo "<p><strong>Notes:</strong> {$prescription['notes']}</p>";
        }
        echo "</div>";

        echo "<div class='detail-section'>";
        echo "<h3>Customer Details</h3>";
        echo "<p><strong>Name:</strong> {$prescription['first']} {$prescription['last']}</p>";
        echo "<p><strong>Phone:</strong> {$prescription['phone']}</p>";
        echo "<p><strong>Email:</strong> {$prescription['email']}</p>";
        echo "<p><strong>Date of Birth:</strong> " . date('d/m/Y', strtotime($prescription['dob'])) . "</p>";
        echo "<p><strong>NHS Number:</strong> {$prescription['nhs']}</p>";
        echo "<p><strong>GP:</strong> {$prescription['gp']}</p>";
        echo "<p><strong>Address:</strong> {$prescription['address']}</p>";
        echo "<p><strong>Allergies:</strong> {$prescription['allergies']}</p>";
        echo "<p><strong>Conditions:</strong> {$prescription['conditions']}</p>";
        echo "</div>";

        echo "<div class='detail-section'>";
        echo "<h3>Medicine Details</h3>";
        echo "<p><strong>Name:</strong> {$prescription['medicine_name']}</p>";
        echo "<p><strong>Category:</strong> {$prescription['category']}</p>";
        echo "<p><strong>Current Stock:</strong> {$prescription['stock_qty']} units</p>";
        echo "<p><strong>Expiry:</strong> " . date('d/m/Y', strtotime($prescription['expiry'])) . "</p>";
        echo "<p><strong>Supplier:</strong> {$prescription['supplier']}</p>";
        echo "<p><strong>ID Check Required:</strong> " . ($prescription['id_check'] ? 'Yes' : 'No') . "</p>";
        echo "<p><strong>Price:</strong> £{$prescription['price']}</p>";
        echo "</div>";

        // Check for associated risks
        $risk_query = mysqli_query($conn, "SELECT * FROM risks WHERE prescription_id = '$id' ORDER BY date DESC");
        if (mysqli_num_rows($risk_query) > 0) {
            echo "<div class='detail-section'>";
            echo "<h3>Associated Risks</h3>";
            while ($risk = mysqli_fetch_assoc($risk_query)) {
                echo "<div class='risk-item risk-{$risk['level']}'>";
                echo "<p><strong>{$risk['type']}</strong> - {$risk['level']} level</p>";
                echo "<p>{$risk['description']}</p>";
                echo "<p><small>" . date('d/m/Y', strtotime($risk['date'])) . "</small></p>";
                echo "</div>";
            }
            echo "</div>";
        }

        echo "</div>";
    } else {
        echo "<p>Prescription not found.</p>";
    }
}
?>