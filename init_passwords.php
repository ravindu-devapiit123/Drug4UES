<?php
/**
 * Drugs4U PMS – Password Initialiser
 * Run this ONCE via browser: http://localhost/drugs4u/init_passwords.php
 * It hashes the plain-text passwords in the users table.
 * Delete or restrict this file after first run.
 */
require_once __DIR__ . '/includes/db.php';

$users = [
    ['email'=>'admin@drugs4u.co.uk',    'pass'=>'admin123'],
    ['email'=>'chamath@drugs4u.co.uk',  'pass'=>'pharm123'],
    ['email'=>'nadeeshan@drugs4u.co.uk','pass'=>'staff123'],
];

echo '<pre>';
echo "Updating passwords...\n";
foreach ($users as $u) {
    $hash = password_hash($u['pass'], PASSWORD_DEFAULT);
    execute("UPDATE users SET password=? WHERE email=?", 'ss', $hash, $u['email']);
    echo "✓ {$u['email']} → hashed\n";
}
echo "\nDone! Please DELETE this file now.\n";
echo '</pre>';
