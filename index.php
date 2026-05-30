<?php
$error = '';
$success = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'invalid') {
        $error = 'Invalid username or password.';
    } elseif ($_GET['error'] === 'empty') {
        $error = 'Please enter both username and password.';
    }
}
if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success = 'Signup complete. Please log in.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Drugs 4U PMS - Login</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="page-shell">
    <div class="login-card">
      <div class="brand">
        <div class="brand-logo">Drugs<span>4U</span></div>
        <div class="brand-subtitle">Pharmacy Management System</div>
      </div>

      <?php if ($error) : ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if ($success) : ?>
        <div class="message success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <form action="login.php" method="post" class="login-form">
        <div class="form-control">
          <label for="username">Email</label>
          <input type="text" id="username" name="username" placeholder="Enter your email" required>
        </div>

        <div class="form-control">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Enter your password" required>
        </div>

        <div class="form-meta">
          <label class="remember">
            <input type="checkbox" name="remember"> Remember me
          </label>
          <a href="#" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="login-btn">Log In</button>
      </form>

      <div class="signup-copy">
        Don't have an account? <a href="signup.php">Sign Up</a>
      </div>
    </div>
  </div>
</body>
</html>
