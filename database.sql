-- ============================================================
-- Drugs4U Prescription Management System
-- MySQL 9.2 Database Schema + Seed Data
-- ============================================================

CREATE DATABASE IF NOT EXISTS drugs4u CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE drugs4u;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    role        ENUM('Admin','Pharmacist','Staff') NOT NULL DEFAULT 'Staff',
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,  -- stored as password_hash
    avatar      VARCHAR(5),
    avatar_path VARCHAR(255) DEFAULT '',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- CUSTOMERS
-- ============================================================
CREATE TABLE IF NOT EXISTS customers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    first_name  VARCHAR(80) NOT NULL,
    last_name   VARCHAR(80) NOT NULL,
    dob         DATE NOT NULL,
    phone       VARCHAR(30),
    email       VARCHAR(150),
    address     VARCHAR(255),
    nhs_number  VARCHAR(30),
    gp_name     VARCHAR(100),
    allergies   VARCHAR(255) DEFAULT 'None',
    conditions  TEXT,
    last_visit  DATE,
    status      ENUM('Active','Inactive','Alert') NOT NULL DEFAULT 'Active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- MEDICINES
-- ============================================================
CREATE TABLE IF NOT EXISTS medicines (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    category    VARCHAR(80) NOT NULL,
    qty         INT NOT NULL DEFAULT 0,
    unit        VARCHAR(30) NOT NULL DEFAULT 'tablets',
    expiry      DATE,
    supplier    VARCHAR(100),
    id_check    TINYINT(1) NOT NULL DEFAULT 0,
    price       DECIMAL(10,2) DEFAULT 0.00,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- PRESCRIPTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS prescriptions (
    id          VARCHAR(10) PRIMARY KEY,   -- e.g. P-1007
    cust_id     INT NOT NULL,
    med_id      INT NOT NULL,
    qty         INT NOT NULL DEFAULT 1,
    unit        VARCHAR(30),
    rx_date     DATE NOT NULL,
    prescriber  VARCHAR(100),
    status      ENUM('Pending','Completed','Alert') NOT NULL DEFAULT 'Pending',
    notes       TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cust_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (med_id)  REFERENCES medicines(id)  ON DELETE CASCADE
);

-- ============================================================
-- RISK ALERTS
-- ============================================================
CREATE TABLE IF NOT EXISTS risks (
    id          VARCHAR(10) PRIMARY KEY,   -- e.g. R-001
    type        VARCHAR(50),
    level       ENUM('red','amber','blue') NOT NULL DEFAULT 'amber',
    cust_id     INT,
    med_id      INT,
    rx_id       VARCHAR(10),
    description TEXT,
    risk_date   DATE NOT NULL,
    resolved    TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Users (passwords are PHP password_hash of the plain text)
-- admin123 / pharm123 / staff123
INSERT INTO users (name, role, email, password, avatar) VALUES
('Ravindu Yasarathne', 'Admin',      'admin@drugs4u.co.uk',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'RY'),
('Chamath Randula',    'Pharmacist', 'chamath@drugs4u.co.uk',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CR'),
('Nadeeshan Gunasekaran','Staff',    'nadeeshan@drugs4u.co.uk','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'NG');

-- NOTE: The hash above is the Laravel default test hash for "password".
-- Run  php -r "echo password_hash('admin123',PASSWORD_DEFAULT);"  and replace if needed.
-- Or use the init_passwords.php helper (included in package).

-- Insert New Customers
INSERT INTO customers (first_name,last_name,dob,phone,email,address,nhs_number,gp_name,allergies,conditions,last_visit,status) VALUES
('Ahmed','Perera','1987-03-12','0765-123-456','ahmed.perera@email.com','45 Mill Lane, Stafford ST16 2AA','NHS-10234567','Dr. Sarah Williams','None','Hypertension','2026-04-12','Active'),
('Nimal','Silva','1982-07-05','0772-987-654','nimal.silva@email.com','12 Castle Street, Stafford ST17 4BQ','NHS-20345678','Dr. James Patel','Penicillin','Type 2 Diabetes','2026-04-10','Active'),
('Kumari','Fernando','1990-11-19','0715-456-789','kumari.f@email.com','78 Victoria Road, Stafford ST16 3PL','NHS-30456789','Dr. Sarah Williams','Aspirin','Asthma','2026-04-08','Active'),
('Bob','Stevenson','1975-08-22','0723-654-321','bob.stevenson@email.com','3 Park Avenue, Stoke ST4 2HJ','NHS-40567890','Dr. Mark Thompson','None','Anxiety Disorder','2026-04-05','Active'),
('Samanthi','Jayawardena','1985-01-30','0789-111-222','samanthi.j@email.com','22 Queen Street, Stafford ST16 2NR','NHS-50678901','Dr. Emily Chen','Sulfonamides','Hypothyroidism','2026-04-02','Active'),
('Linda','Garrett','1965-06-14','0741-332-211','linda.g@email.com','9 Brook Road, Stafford ST17 0EL','NHS-60789012','Dr. James Patel','None','Arthritis, Hypertension','2026-04-14','Active'),
('Fred','McCoy','1958-12-03','0778-445-566','fred.m@email.com','56 Elm Drive, Burton DE14 2QP','NHS-70890123','Dr. Mark Thompson','NSAIDs','COPD, Heart Disease','2026-04-12','Active'),
('Samuel','Reynolds','1972-04-28','0756-667-788','samuel.r@email.com','31 Cedar Close, Lichfield WS13 6RR','NHS-80901234','Dr. Sarah Williams','Penicillin, Aspirin','Epilepsy, Anxiety','2026-04-11','Alert'),
('Anita','Lee','1979-09-07','0712-889-900','anita.l@email.com','14 Rose Gardens, Cannock WS11 5TG','NHS-91012345','Dr. Emily Chen','None','Type 1 Diabetes','2026-04-09','Active'),
('Ruwan','Bandara','1993-02-17','0799-001-122','ruwan.b@email.com','66 Maple Street, Stafford ST16 1FG','NHS-01123456','Dr. James Patel','Codeine','Anxiety, Insomnia','2026-04-05','Active'),
('Julia','Cole','1988-05-21','0733-223-344','julia.c@email.com','88 Birch Way, Tamworth B77 2RQ','NHS-11234567','Dr. Mark Thompson','Penicillin','Respiratory Infection','2026-04-14','Alert'),
('Michael','Hartley','1950-08-11','0766-445-667','m.hartley@email.com','2 Oak Lane, Stafford ST17 9PQ','NHS-21345678','Dr. Sarah Williams','None','Parkinson''s Disease','2026-03-28','Active');

-- Insrt New Medicines
INSERT INTO medicines (name,category,qty,unit,expiry,supplier,id_check,price) VALUES
('Paracetamol','Analgesic',10,'tablets','2027-06-30','PharmaCo Ltd',0,2.50),
('Ibuprofen','Analgesic',2,'tablets','2027-03-15','MedSupply UK',0,3.20),
('Amoxicillin','Antibiotic',1,'bottle','2026-12-01','BioPharm Ltd',0,8.50),
('Diazepam','Anxiolytic',45,'tablets','2027-09-20','PharmaCo Ltd',1,15.00),
('Insulin (Lantus)','Antidiabetic',24,'vials','2026-11-15','SanofiMed',0,42.00),
('Atorvastatin','Antihypertensive',85,'tablets','2028-02-28','PharmaCo Ltd',0,6.80),
('Amlodipine','Antihypertensive',120,'tablets','2028-05-10','MedSupply UK',0,4.50),
('Metformin','Antidiabetic',200,'tablets','2027-08-22','BioPharm Ltd',0,3.80),
('Salbutamol','Bronchodilator',6,'inhalers','2027-01-15','PharmaCo Ltd',0,14.00),
('Codeine Phosphate','Analgesic',3,'tablets','2026-10-30','MedSupply UK',1,12.00),
('Levothyroxine','Thyroid',150,'tablets','2027-12-01','BioPharm Ltd',0,5.20),
('Omeprazole','Antacid',7,'capsules','2027-04-20','PharmaCo Ltd',0,4.90),
('Sertraline','Antidepressant',65,'tablets','2028-01-10','MedSupply UK',1,9.50),
('Prednisone','Corticosteroid',40,'tablets','2025-12-15','BioPharm Ltd',0,7.30),
('Loratadine','Antihistamine',90,'tablets','2027-07-25','PharmaCo Ltd',0,3.60);

-- Insert New Prescriptions
INSERT INTO prescriptions (id,cust_id,med_id,qty,unit,rx_date,prescriber,status,notes) VALUES
('P-1007',6,2,4,'tablets','2026-04-14','Dr. James Patel','Completed','Take twice daily with food'),
('P-1006',7,1,20,'tablets','2026-04-12','Dr. Mark Thompson','Pending','As needed for pain'),
('P-1005',8,4,10,'tablets','2026-04-11','Dr. Sarah Williams','Alert','ID check required for Diazepam'),
('P-1004',4,4,5,'tablets','2026-04-10','Dr. Mark Thompson','Completed',''),
('P-1003',9,5,1,'bottle','2026-04-09','Dr. Emily Chen','Completed','Refrigerate after opening'),
('P-1002',1,6,10,'tablets','2026-04-12','Dr. Sarah Williams','Completed',''),
('P-1001',3,2,6,'tablets','2026-04-08','Dr. Sarah Williams','Pending','Patient allergic to Aspirin - verify medication'),
('P-1000',2,8,30,'tablets','2026-04-05','Dr. James Patel','Completed','With evening meal'),
('P-0999',5,11,28,'tablets','2026-04-02','Dr. Emily Chen','Completed','Take in the morning'),
('P-0998',10,4,6,'tablets','2026-04-05','Dr. James Patel','Alert','ID check required. Possible duplicate request.'),
('P-0997',11,3,1,'bottle','2026-04-14','Dr. Mark Thompson','Alert','Patient allergic to Penicillin - flagged'),
('P-0996',12,7,28,'tablets','2026-03-28','Dr. Sarah Williams','Completed',''),
('P-0995',6,7,14,'tablets','2026-04-05','Dr. James Patel','Completed',''),
('P-0994',7,13,28,'tablets','2026-04-01','Dr. Mark Thompson','Completed','');

-- Insert new Risks
INSERT INTO risks (id,type,level,cust_id,med_id,rx_id,description,risk_date,resolved) VALUES
('R-001','ID Check','red',8,4,'P-1005','Diazepam requires mandatory ID verification for date of birth check.','2026-04-11',0),
('R-002','Low Stock','amber',NULL,2,NULL,'Ibuprofen stock critically low — only 2 tablets remaining. Reorder immediately.','2026-04-12',0),
('R-003','Allergy Conflict','red',3,2,'P-1001','Patient Kumari Fernando has documented Aspirin allergy. Ibuprofen prescribed — please verify with prescriber.','2026-04-08',0),
('R-004','Low Stock','amber',NULL,1,NULL,'Paracetamol stock low — 10 tablets remaining. Reorder threshold reached.','2026-04-14',0),
('R-005','ID Check','red',10,4,'P-0998','Diazepam request from Ruwan Bandara — possible duplicate prescription. ID check mandatory.','2026-04-05',0),
('R-006','Allergy Conflict','red',11,3,'P-0997','Julia Cole is allergic to Penicillin. Amoxicillin (Penicillin-based) prescribed — URGENT review required.','2026-04-14',0),
('R-007','Low Stock','amber',NULL,3,NULL,'Amoxicillin — only 1 bottle remaining.','2026-04-10',1),
('R-008','Low Stock','amber',NULL,10,NULL,'Codeine Phosphate — only 3 tablets remaining. Requires ID check.','2026-04-09',1);
