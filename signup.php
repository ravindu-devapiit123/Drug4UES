<?php
session_start();
include 'db.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($username === '' || $password === '' || $confirm === '') {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ?');
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = 'That email is already registered.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $insert = mysqli_prepare($conn, 'INSERT INTO users (username, password) VALUES (?, ?)');
            mysqli_stmt_bind_param($insert, 'ss', $username, $passwordHash);
            if (mysqli_stmt_execute($insert)) {
                header('Location: index.php?success=1');
                exit;
            }
            $error = 'Unable to create account. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up - Drugs 4U PMS</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="page-shell">
    <div class="login-card">
      <div class="brand">
        <div class="brand-logo">Drugs<span>4U</span></div>
        <div class="brand-subtitle">Create your account</div>
      </div>

      <?php if ($error) : ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form action="signup.php" method="post" class="login-form">
        <div class="form-control">
          <label for="username">Email</label>
          <input type="text" id="username" name="username" placeholder="Enter your email" required>
        </div>

        <div class="form-control">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Create a password" required>
        </div>

        <div class="form-control">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
        </div>

        <button type="submit" class="login-btn">Sign Up</button>
      </form>

      <div class="signup-copy">
        Already have an account? <a href="index.php">Log In</a>
      </div>
    </div>
  </div>
</body>
</html>