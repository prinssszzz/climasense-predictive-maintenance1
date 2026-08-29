<?php
require_once __DIR__ . '/includes/auth.php';

if (cs_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = null;
$values = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['name'] = $_POST['name'] ?? '';
    $values['email'] = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $role = $_POST['role'] ?? $_GET['role'] ?? 'consumer';

    if ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        [$ok, $err] = cs_create_user($values['name'], $values['email'], $password, $role);
        if ($ok) {
            header('Location: login.php?registered=1');
            exit;
        }
        $error = $err;
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<script>
document.documentElement.classList.add('js-anim');
try { if (localStorage.getItem('cs-theme') === 'dark') document.documentElement.setAttribute('data-theme', 'dark'); } catch (e) {}
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign Up · ClimaSense</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="page-loader" id="pageLoader"></div>
<div class="auth-shell">
  <div class="auth-card-wrap">
    <div class="auth-top-brand">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 40 40" width="24" height="24">
          <path d="M20 4c0 8-9 10-9 18a9 9 0 0 0 18 0c0-8-9-10-9-18Z" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/>
          <path d="M20 15c0 4-4.5 5-4.5 9a4.5 4.5 0 0 0 9 0c0-4-4.5-5-4.5-9Z" fill="currentColor"/>
        </svg>
      </span>
      <strong>ClimaSense</strong>
    </div>

    <div class="auth-card">
      <h1>Create your account</h1>
      <p class="sub">Start monitoring your first unit in minutes.</p>

      <?php if ($error): ?>
        <div class="auth-alert">
          <svg width="16" height="16" viewBox="0 0 24 24"><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none"/></svg>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="signup.php">
        <div class="field">
          <label for="name">Full name</label>
          <input class="input" type="text" id="name" name="name" placeholder="Juan Dela Cruz" required autofocus value="<?= htmlspecialchars($values['name']) ?>">
        </div>
        <div class="field">
          <label for="email">Email address</label>
          <input class="input" type="email" id="email" name="email" placeholder="you@company.com" required value="<?= htmlspecialchars($values['email']) ?>">
        </div>
        <div class="field">
          <label for="role">Role</label>
          <select class="input" id="role" name="role">
            <option value="consumer" <?= $role === 'consumer' ? 'selected' : '' ?>>Consumer — air-conditioner owner</option>
            <option value="client_admin" <?= $role === 'client_admin' ? 'selected' : '' ?>>Client administrator — shop owner/admin</option>
          </select>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <div class="pw-field">
            <input class="input" type="password" id="password" name="password" placeholder="At least 8 characters" required minlength="8">
            <button type="button" class="pw-toggle" data-pw-toggle="password" aria-label="Show password">
              <svg width="17" height="17" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.8" fill="none"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8" fill="none"/></svg>
            </button>
          </div>
        </div>
        <div class="field">
          <label for="confirm">Confirm password</label>
          <div class="pw-field">
            <input class="input" type="password" id="confirm" name="confirm" placeholder="Re-enter your password" required minlength="8">
            <button type="button" class="pw-toggle" data-pw-toggle="confirm" aria-label="Show password">
              <svg width="17" height="17" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.8" fill="none"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8" fill="none"/></svg>
            </button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-top-gap">Create Account</button>
      </form>

      <p class="auth-foot">Already have an account? <a href="login.php">Log in</a></p>
    </div>

    <p class="auth-foot-below">© <?= date('Y') ?> ClimaSense. All rights reserved.</p>
  </div>
</div>

<div class="toast-stack" id="toastStack"></div>
<script src="js/app.js"></script>
</body>
</html>
