<?php
require_once 'db.php';

if (mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM users LIMIT 1")) == 0) {
    $users = [
        ['Ravindu Yasarathne', 'Admin', 'admin@drugs4u.co.uk', 'admin123', 'RY'],
        ['Chamath Randula', 'Pharmacist', 'chamath@drugs4u.co.uk', 'pharm123', 'CR'],
        ['Nadeeshan Gunasekaran', 'Staff', 'nadeeshan@drugs4u.co.uk', 'staff123', 'NG'],
    ];
    foreach ($users as $u) {
        $pw = password_hash($u[3], PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "INSERT INTO users (name, role, email, password, avatar) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sssss', $u[0], $u[1], $u[2], $pw, $u[4]);
        mysqli_stmt_execute($stmt);
    }
}

if (mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM customers LIMIT 1")) == 0) {
    $customers = [
        ['Ahmed', 'Perera', '1987-03-12', '0765-123-456', 'ahmed.perera@email.com', '45 Mill Lane, Stafford ST16 2AA', 'NHS-10234567', 'Dr. Sarah Williams', 'None', 'Hypertension', '2026-04-12', 'Active'],
        ['Nimal', 'Silva', '1982-07-05', '0772-987-654', 'nimal.silva@email.com', '12 Castle Street, Stafford ST17 4BQ', 'NHS-20345678', 'Dr. James Patel', 'Penicillin', 'Type 2 Diabetes', '2026-04-10', 'Active'],
        ['Kumari', 'Fernando', '1990-11-19', '0715-456-789', 'kumari.f@email.com', '78 Victoria Road, Stafford ST16 3PL', 'NHS-30456789', 'Dr. Sarah Williams', 'Aspirin', 'Asthma', '2026-04-08', 'Active'],
        ['Bob', 'Stevenson', '1975-08-22', '0723-654-321', 'bob.stevenson@email.com', '3 Park Avenue, Stoke ST4 2HJ', 'NHS-40567890', 'Dr. Mark Thompson', 'None', 'Anxiety Disorder', '2026-04-05', 'Active'],
        ['Samanthi', 'Jayawardena', '1985-01-30', '0789-111-222', 'samanthi.j@email.com', '22 Queen Street, Stafford ST16 2NR', 'NHS-50678901', 'Dr. Emily Chen', 'Sulfonamides', 'Hypothyroidism', '2026-04-02', 'Active'],
        ['Linda', 'Garrett', '1965-06-14', '0741-332-211', 'linda.g@email.com', '9 Brook Road, Stafford ST17 0EL', 'NHS-60789012', 'Dr. James Patel', 'None', 'Arthritis, Hypertension', '2026-04-14', 'Active'],
        ['Fred', 'McCoy', '1958-12-03', '0778-445-566', 'fred.m@email.com', '56 Elm Drive, Burton DE14 2QP', 'NHS-70890123', 'Dr. Mark Thompson', 'NSAIDs', 'COPD, Heart Disease', '2026-04-12', 'Active'],
        ['Samuel', 'Reynolds', '1972-04-28', '0756-667-788', 'samuel.r@email.com', '31 Cedar Close, Lichfield WS13 6RR', 'NHS-80901234', 'Dr. Sarah Williams', 'Penicillin, Aspirin', 'Epilepsy, Anxiety', '2026-04-11', 'Alert'],
        ['Anita', 'Lee', '1979-09-07', '0712-889-900', 'anita.l@email.com', '14 Rose Gardens, Cannock WS11 5TG', 'NHS-91012345', 'Dr. Emily Chen', 'None', 'Type 1 Diabetes', '2026-04-09', 'Active'],
        ['Ruwan', 'Bandara', '1993-02-17', '0799-001-122', 'ruwan.b@email.com', '66 Maple Street, Stafford ST16 1FG', 'NHS-01123456', 'Dr. James Patel', 'Codeine', 'Anxiety, Insomnia', '2026-04-05', 'Active'],
        ['Julia', 'Cole', '1988-05-21', '0733-223-344', 'julia.c@email.com', '88 Birch Way, Tamworth B77 2RQ', 'NHS-11234567', 'Dr. Mark Thompson', 'Penicillin', 'Respiratory Infection', '2026-04-14', 'Alert'],
        ['Michael', 'Hartley', '1950-08-11', '0766-445-667', 'm.hartley@email.com', '2 Oak Lane, Stafford ST17 9PQ', 'NHS-21345678', 'Dr. Sarah Williams', 'None', "Parkinson's Disease", '2026-03-28', 'Active'],
    ];
    foreach ($customers as $c) {
        $stmt = mysqli_prepare($conn, "INSERT INTO customers (first, last, dob, phone, email, address, nhs, gp, allergies, conditions, last_visit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssssssssssss', $c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6], $c[7], $c[8], $c[9], $c[10], $c[11]);
        mysqli_stmt_execute($stmt);
    }
}

if (mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM medicines LIMIT 1")) == 0) {
    $medicines = [
        ['Paracetamol', 'Analgesic', 10, 'tablets', '2027-06-30', 'PharmaCo Ltd', 0, 2.50],
        ['Ibuprofen', 'Analgesic', 2, 'tablets', '2027-03-15', 'MedSupply UK', 0, 3.20],
        ['Amoxicillin', 'Antibiotic', 1, 'bottle', '2026-12-01', 'BioPharm Ltd', 0, 8.50],
        ['Diazepam', 'Anxiolytic', 45, 'tablets', '2027-09-20', 'PharmaCo Ltd', 1, 15.00],
        ['Insulin (Lantus)', 'Antidiabetic', 24, 'vials', '2026-11-15', 'SanofiMed', 0, 42.00],
        ['Atorvastatin', 'Antihypertensive', 85, 'tablets', '2028-02-28', 'PharmaCo Ltd', 0, 6.80],
        ['Amlodipine', 'Antihypertensive', 120, 'tablets', '2028-05-10', 'MedSupply UK', 0, 4.50],
        ['Metformin', 'Antidiabetic', 200, 'tablets', '2027-08-22', 'BioPharm Ltd', 0, 3.80],
        ['Salbutamol', 'Bronchodilator', 6, 'inhalers', '2027-01-15', 'PharmaCo Ltd', 0, 14.00],
        ['Codeine Phosphate', 'Analgesic', 3, 'tablets', '2026-10-30', 'MedSupply UK', 1, 12.00],
        ['Levothyroxine', 'Thyroid', 150, 'tablets', '2027-12-01', 'BioPharm Ltd', 0, 5.20],
        ['Omeprazole', 'Antacid', 7, 'capsules', '2027-04-20', 'PharmaCo Ltd', 0, 4.90],
        ['Sertraline', 'Antidepressant', 65, 'tablets', '2028-01-10', 'MedSupply UK', 1, 9.50],
        ['Prednisone', 'Corticosteroid', 40, 'tablets', '2025-12-15', 'BioPharm Ltd', 0, 7.30],
        ['Loratadine', 'Antihistamine', 90, 'tablets', '2027-07-25', 'PharmaCo Ltd', 0, 3.60],
    ];
    foreach ($medicines as $m) {
        $stmt = mysqli_prepare($conn, "INSERT INTO medicines (name, category, qty, unit, expiry, supplier, id_check, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssissdsd', $m[0], $m[1], $m[2], $m[3], $m[4], $m[5], $m[6], $m[7]);
        mysqli_stmt_execute($stmt);
    }
}

if (mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM prescriptions LIMIT 1")) == 0) {
    $prescriptions = [
        ['P-1007', 6, 2, 4, 'tablets', 'Dr. James Patel', 'Take twice daily with food', 'Completed', '2026-04-14'],
        ['P-1006', 7, 1, 20, 'tablets', 'Dr. Mark Thompson', 'As needed for pain', 'Pending', '2026-04-12'],
        ['P-1005', 8, 4, 10, 'tablets', 'Dr. Sarah Williams', 'ID check required for Diazepam', 'Alert', '2026-04-11'],
        ['P-1004', 4, 4, 5, 'tablets', 'Dr. Mark Thompson', '', 'Completed', '2026-04-10'],
        ['P-1003', 9, 5, 1, 'vials', 'Dr. Emily Chen', 'Refrigerate after opening', 'Completed', '2026-04-09'],
        ['P-1002', 1, 6, 10, 'tablets', 'Dr. Sarah Williams', '', 'Completed', '2026-04-12'],
        ['P-1001', 3, 2, 6, 'tablets', 'Dr. Sarah Williams', 'Patient allergic to Aspirin - verify medication', 'Pending', '2026-04-08'],
        ['P-1000', 2, 8, 30, 'tablets', 'Dr. James Patel', 'With evening meal', 'Completed', '2026-04-05'],
        ['P-0999', 5, 11, 28, 'tablets', 'Dr. Emily Chen', 'Take in the morning', 'Completed', '2026-04-02'],
        ['P-0998', 10, 4, 6, 'tablets', 'Dr. James Patel', 'ID check required. Possible duplicate request.', 'Alert', '2026-04-05'],
        ['P-0997', 11, 3, 1, 'bottle', 'Dr. Mark Thompson', 'Patient allergic to Penicillin - flagged', 'Alert', '2026-04-14'],
        ['P-0996', 12, 7, 28, 'tablets', 'Dr. Sarah Williams', '', 'Completed', '2026-03-28'],
        ['P-0995', 6, 7, 14, 'tablets', 'Dr. James Patel', '', 'Completed', '2026-04-05'],
        ['P-0994', 7, 13, 28, 'tablets', 'Dr. Mark Thompson', '', 'Completed', '2026-04-01'],
    ];
    foreach ($prescriptions as $p) {
        $stmt = mysqli_prepare($conn, "INSERT INTO prescriptions (id, customer_id, medicine_id, quantity, unit, prescriber, notes, status, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'siissssss', $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8]);
        mysqli_stmt_execute($stmt);
    }
}

if (mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM risks LIMIT 1")) == 0) {
    $risks = [
        ['R-001', 'ID Check', 'red', 8, 4, 'P-1005', 'Diazepam requires mandatory ID verification for date of birth check.', '2026-04-11', 0],
        ['R-002', 'Low Stock', 'amber', null, 2, null, 'Ibuprofen stock critically low - only 2 tablets remaining. Reorder immediately.', '2026-04-12', 0],
        ['R-003', 'Allergy Conflict', 'red', 3, 2, 'P-1001', 'Patient Kumari Fernando has documented Aspirin allergy. Ibuprofen prescribed - please verify with prescriber.', '2026-04-08', 0],
        ['R-004', 'Low Stock', 'amber', null, 1, null, 'Paracetamol stock low - 10 tablets remaining. Reorder threshold reached.', '2026-04-14', 0],
        ['R-005', 'ID Check', 'red', 10, 4, 'P-0998', 'Diazepam request from Ruwan Bandara - possible duplicate prescription. ID check mandatory.', '2026-04-05', 0],
        ['R-006', 'Allergy Conflict', 'red', 11, 3, 'P-0997', 'Julia Cole is allergic to Penicillin. Amoxicillin (Penicillin-based) prescribed - URGENT review required.', '2026-04-14', 0],
        ['R-007', 'Low Stock', 'amber', null, 3, null, 'Amoxicillin - only 1 bottle remaining.', '2026-04-10', 1],
        ['R-008', 'Low Stock', 'amber', null, 10, null, 'Codeine Phosphate - only 3 tablets remaining. Requires ID check.', '2026-04-09', 1],
    ];
    foreach ($risks as $r) {
        $stmt = mysqli_prepare($conn, "INSERT INTO risks (id, type, level, customer_id, medicine_id, prescription_id, description, date, resolved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sssiissii', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $r[7], $r[8]);
        mysqli_stmt_execute($stmt);
    }
}

echo "Database seeded successfully.";
?>
