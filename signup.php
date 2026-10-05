<?php
require_once __DIR__ . '/includes/auth.php';

if (cs_is_logged_in() && !cs_has_role('client_admin')) {
  header('Location: index.php');
  exit;
}

$error = null;
$values = ['name' => '', 'email' => ''];
$role = 'client_admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['name'] = $_POST['name'] ?? '';
    $values['email'] = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $signupCode = (string) ($_POST['admin_code'] ?? '');
    $adminCodeRequired = defined('CS_ADMIN_SIGNUP_CODE') && CS_ADMIN_SIGNUP_CODE !== '';
    if ($role === 'client_admin' && $adminCodeRequired && !hash_equals(CS_ADMIN_SIGNUP_CODE, $signupCode)) {
        $error = 'A valid admin signup code is required.';
    } elseif ($password !== $confirm) {
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
<style>
  .auth-shell {
    padding: 32px 16px 24px;
  }

  .auth-card-wrap {
    width: min(100%, 600px);
    margin: 0 auto;
  }

  .auth-top-brand {
    padding: 8px 0 18px;
    margin-bottom: 0;
  }

  .auth-card {
    padding: 28px 30px 22px;
    border-radius: 18px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
  }

  .auth-card h1 {
    font-size: clamp(2rem, 2.5vw, 2.6rem);
    margin-bottom: 8px;
    letter-spacing: -0.04em;
  }

  .auth-card .sub {
    margin-bottom: 22px;
    font-size: 0.98rem;
  }

  .field {
    margin-bottom: 16px;
  }

  .field label {
    font-size: 0.88rem;
    margin-bottom: 8px;
  }

  .input {
    min-height: 46px;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 1rem;
  }

  .pw-field .input {
    padding-right: 46px;
  }

  .pw-toggle {
    width: 38px;
    height: 38px;
    right: 7px;
  }

  .checkbox-row {
    margin: 6px 0 20px;
    font-size: 0.88rem;
  }

  .btn.btn-primary.btn-block,
  .btn-top-gap {
    min-height: 46px;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 0.01em;
  }

  .auth-foot {
    margin-top: 16px;
    font-size: 0.92rem;
  }

  .auth-foot-below {
    margin-top: 18px;
    font-size: 0.8rem;
  }

  @media (max-width: 640px) {
    .auth-card {
      padding: 22px 18px 18px;
    }
  }
</style>
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
      <p class="sub">Create your shop administrator account.</p>

      <?php if ($error): ?>
        <div class="auth-alert">
          <svg width="16" height="16" viewBox="0 0 24 24"><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none"/></svg>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="signup.php">
        <?php if ($role === 'client_admin' && defined('CS_ADMIN_SIGNUP_CODE') && CS_ADMIN_SIGNUP_CODE !== ''): ?>
        <div class="field">
          <label for="admin_code">Admin signup code</label>
          <input class="input" type="password" id="admin_code" name="admin_code" autocomplete="off" required>
        </div>
        <?php endif; ?>
        <div class="field">
          <label for="name">Full name</label>
          <input class="input" type="text" id="name" name="name" placeholder="Juan Dela Cruz" required autofocus value="<?= htmlspecialchars($values['name']) ?>">
        </div>
        <div class="field">
          <label for="email">Email address</label>
          <input class="input" type="email" id="email" name="email" placeholder="you@company.com" required value="<?= htmlspecialchars($values['email']) ?>">
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
        <?php if (cs_google_enabled()): ?>
          <p class="auth-foot" style="margin-top:12px;"><a href="google-login.php">Continue with Google</a></p>
        <?php endif; ?>
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
