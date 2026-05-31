<?php
require_once __DIR__ . '/includes/db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email && $pass) {
        $user = fetchOne("SELECT * FROM users WHERE email = ?", 's', $email);
        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_avatar'] = $user['avatar'];
            $_SESSION['user_avatar_path'] = $user['avatar_path'];
            header('Location: app.php');
            exit;
        }
    }
    header('Location: index.php?error=1');
    exit;
}

if ($action === 'change_password') {
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';

    if ($currentPass && $newPass) {
        $user = fetchOne("SELECT * FROM users WHERE id=?", 'i', (int)$_SESSION['user_id']);
        if ($user && password_verify($currentPass, $user['password'])) {
            if (strlen($newPass) >= 6) {
                execute("UPDATE users SET password=? WHERE id=?", 'si', password_hash($newPass, PASSWORD_DEFAULT), (int)$_SESSION['user_id']);
                header('Location: app.php?toast='.urlencode('Password changed successfully').'&toast_type=success');
                exit;
            } else {
                header('Location: app.php?toast='.urlencode('Password must be at least 6 characters').'&toast_type=error');
                exit;
            }
        } else {
            header('Location: app.php?toast='.urlencode('Current password is incorrect').'&toast_type=error');
            exit;
        }
    }
    header('Location: app.php');
    exit;
}

header('Location: index.php');
exit;
