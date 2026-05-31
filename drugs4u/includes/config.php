<?php
// ============================================================
// Drugs4U PMS – Configuration
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Change to your MySQL username
define('DB_PASS', '');              // Change to your MySQL password
define('DB_NAME', 'drugs4u');
define('DB_PORT', 3306);

define('SITE_NAME', 'Drugs4U PMS');
define('LOW_STOCK_THRESHOLD', 10);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
