<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header('Location: index.php?error=empty');
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT id, password FROM users WHERE username = ?');
if (!$stmt) {
    header('Location: index.php?error=invalid');
    exit;
}
mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
if (mysqli_stmt_num_rows($stmt) === 1) {
    mysqli_stmt_bind_result($stmt, $userId, $passwordHash);
    mysqli_stmt_fetch($stmt);
    if (password_verify($password, $passwordHash)) {
        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        header('Location: dashboard.php');
        exit;
    }
}

header('Location: index.php?error=invalid');
exit;
