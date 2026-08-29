<?php
require_once __DIR__ . '/includes/auth.php';
$selectedRole = $_GET['role'] ?? $_POST['role'] ?? '';
if (!in_array($selectedRole, ['client_admin', 'consumer'], true)) { header('Location: role-select.php'); exit; }

if (cs_is_logged_in()) {
    header('Location: ' . cs_home_for_user());
    exit;
}

$error = null;
$justRegistered = isset($_GET['registered']);
$next = $_GET['next'] ?? 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    [$ok, $err] = cs_attempt_login($email, $password);
    if ($ok && !cs_has_role($selectedRole)) { cs_logout(); $ok = false; $err = 'This account belongs to the other portal. Please choose the correct account type.'; }
    if ($ok) {
        $redirect = $_POST['next'] ?? 'index.php';
        if (!$redirect || str_starts_with($redirect, 'http')) $redirect = 'index.php';
        header('Location: ' . cs_home_for_user());
        exit;
    }
    $error = $err;
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
<title>Log In · ClimaSense</title>
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
      <h1>Welcome back</h1>
      <p class="sub">Log in to monitor your fleet.</p>

      <?php if ($error): ?>
        <div class="auth-alert">
          <svg width="16" height="16" viewBox="0 0 24 24"><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none"/></svg>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php elseif ($justRegistered): ?>
        <div class="auth-alert ok">
          <svg width="16" height="16" viewBox="0 0 24 24"><path d="M9 12.5 11 15l4.5-6" stroke="currentColor" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>
          <span>Account created — log in to continue.</span>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php">
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>"><input type="hidden" name="role" value="<?= htmlspecialchars($selectedRole) ?>">
        <div class="field">
          <label for="email">Email address</label>
          <input class="input" type="email" id="email" name="email" placeholder="you@company.com" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <div class="pw-field">
            <input class="input" type="password" id="password" name="password" placeholder="••••••••" required>
            <button type="button" class="pw-toggle" data-pw-toggle="password" aria-label="Show password">
              <svg width="17" height="17" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.8" fill="none"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8" fill="none"/></svg>
            </button>
          </div>
        </div>
        <div class="checkbox-row">
          <label><input type="checkbox" name="remember"> Remember me</label>
          <a href="#" onclick="csToast && csToast('Password reset isn\'t wired up in this demo yet.'); return false;">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Log In</button>
        <?php if (cs_google_enabled()): ?><p class="auth-foot"><a href="google-login.php">Continue with Google</a></p><?php endif; ?>
      </form>

      <p class="auth-foot">Don't have an account? <a href="signup.php?role=<?= urlencode($selectedRole) ?>">Sign up</a></p>
    </div>

    <p class="auth-foot-below">© <?= date('Y') ?> ClimaSense. All rights reserved.</p>
  </div>
</div>

<div class="toast-stack" id="toastStack"></div>
<script src="js/app.js"></script>
</body>
</html>
