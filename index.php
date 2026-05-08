<?php
require_once __DIR__ . '/includes/config.php';
// Already logged in?
if (!empty($_SESSION['user_id'])) {
    header('Location: app.php');
    exit;
}
$error = isset($_GET['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Drugs4U – Login</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{
  --teal:#1D9E75;--teal-dark:#0F6E56;
  --border:#E2E8F0;--text:#2D3748;--text-muted:#718096;
}
body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#1a2e3b 0%,#2d4a5e 50%,#1D9E75 100%);min-height:100vh;display:flex;align-items:center;justify-content:center}
.login-card{background:#fff;border-radius:16px;padding:48px 40px;width:420px;box-shadow:0 20px 60px rgba(0,0,0,0.3)}
.login-logo{text-align:center;margin-bottom:32px}
.brand{font-size:32px;font-weight:800;color:var(--teal)}
.brand span{color:#333}
.login-logo small{display:block;color:var(--text-muted);font-size:13px;margin-top:4px}
h2{font-size:18px;margin-bottom:24px;color:var(--text)}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:13px;color:var(--text-muted);margin-bottom:6px;font-weight:500}
.form-group input{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;outline:none;transition:.2s}
.form-group input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(29,158,117,0.12)}
.btn-login{width:100%;padding:12px;background:var(--teal);color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;transition:.2s;margin-top:8px}
.btn-login:hover{background:var(--teal-dark)}
.error-box{background:#FCEBEB;color:#A32D2D;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
.hint{text-align:center;font-size:12px;color:var(--text-muted);margin-top:20px;background:#F5F6FA;padding:10px;border-radius:8px;line-height:1.8}
</style>
</head>
<body>
<div class="login-card">
  <div class="login-logo">
    <div class="brand">Drugs<span>4U</span></div>
    <small>Prescription Management System</small>
  </div>
  <h2>Sign in to your account</h2>
  <?php if ($error): ?>
  <div class="error-box">Invalid email or password. Please try again.</div>
  <?php endif; ?>
  <form method="POST" action="auth.php">
    <input type="hidden" name="action" value="login">
    <div class="form-group">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="admin@drugs4u.co.uk" required autofocus>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn-login">Log In</button>
  </form>
  <div class="hint">
    <strong>Demo Accounts</strong><br>
    admin@drugs4u.co.uk / admin123<br>
    phama@drugs4u.co.uk / pharm123<br>
    staff@drugs4u.co.uk / staff123
  </div>
</div>
</body>
</html>
