<?php
require_once __DIR__ . '/includes/auth.php';
cs_require_login();
if (cs_primary_role_for_user() !== 'consumer') { header('Location: ' . cs_home_for_user()); exit; }
cs_ensure_user_phone_column();
$user = cs_current_user();
$db = cs_db();
$profileQuery = $db->prepare('SELECT name,email,phone,auth_provider,google_sub FROM users WHERE id=? LIMIT 1');
$profileQuery->execute([$user['id']]);
$profile = $profileQuery->fetch();
if (!$profile) { http_response_code(404); exit('Account not found.'); }
$pageTitle = 'Profile';
$activeNav = 'profile';
$error = null;
$success = null;
$csrfKey = 'cs_profile_csrf';
if (empty($_SESSION[$csrfKey])) $_SESSION[$csrfKey] = bin2hex(random_bytes(32));
$isGoogleAccount = ($profile['auth_provider'] ?? '') === 'google' || !empty($profile['google_sub']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $token = (string)($_POST['csrf_token'] ?? '');
    $digits = preg_replace('/\D+/', '', $phone);
    if (!hash_equals($_SESSION[$csrfKey], $token)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif ($name === '' || strlen($name) > 120) {
        $error = 'Enter your name using no more than 120 characters.';
    } elseif ($phone === '' || !preg_match('/^\+?[0-9 ().-]+$/', $phone) || strlen($digits) < 7 || strlen($digits) > 15) {
        $error = 'Enter a contact number with 7 to 15 digits. You can include a country code, spaces, or punctuation.';
    } elseif (!$isGoogleAccount && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190)) {
        $error = 'Enter a valid email address.';
    } else {
        if ($isGoogleAccount) $email = $profile['email'];
        $duplicate = $db->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');
        $duplicate->execute([$email, $user['id']]);
        if ($duplicate->fetchColumn()) {
            $error = 'That email address is already used by another account.';
        } elseif (cs_email_is_reserved_for_role($email, 'consumer')) {
            $error = 'That email address is reserved for a different account type.';
        } else {
            try {
                $save = $db->prepare('UPDATE users SET name=?,email=?,phone=? WHERE id=?');
                $save->execute([$name, $email, $phone, $user['id']]);
                $_SESSION['cs_user']['name'] = $name;
                $_SESSION['cs_user']['email'] = $email;
                $profile['name'] = $name;
                $profile['email'] = $email;
                $profile['phone'] = $phone;
                $success = 'Your contact information has been saved.';
            } catch (Throwable $e) {
                $error = 'Your changes could not be saved. Please try again.';
            }
        }
    }
    if ($error) {
        $profile['name'] = $name;
        if (!$isGoogleAccount) $profile['email'] = $email;
        $profile['phone'] = $phone;
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">Account</span><h1>Profile &amp; Contact Information</h1><p>Keep your name and contact details up to date so your service provider can reach you about your AC.</p></div></div>
<?php if ($error): ?><div class="panel"><div class="panel-body" role="alert" style="color:var(--rust)"><?= htmlspecialchars($error) ?></div></div><?php endif; ?>
<?php if ($success): ?><div class="panel"><div class="panel-body" role="status" style="color:var(--forest-600)"><?= htmlspecialchars($success) ?></div></div><?php endif; ?>
<div class="panel"><div class="panel-body">
  <form method="post" action="profile.php" class="profile-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[$csrfKey]) ?>">
    <div class="field"><label for="profileName">Customer Name</label><input class="input" id="profileName" name="name" type="text" maxlength="120" autocomplete="name" required value="<?= htmlspecialchars($profile['name']) ?>"></div>
    <div class="field"><label for="profileEmail">Email Address</label><input class="input" id="profileEmail" name="email" type="email" maxlength="190" autocomplete="email" required value="<?= htmlspecialchars($profile['email']) ?>" <?= $isGoogleAccount ? 'readonly aria-describedby="emailHelp"' : '' ?>><?php if ($isGoogleAccount): ?><small class="profile-help" id="emailHelp">This email is managed by your Google sign-in.</small><?php endif; ?></div>
    <div class="field"><label for="profilePhone">Contact Number</label><input class="input" id="profilePhone" name="phone" type="tel" maxlength="30" autocomplete="tel" placeholder="+63 917 123 4567" required value="<?= htmlspecialchars($profile['phone'] ?? '') ?>"><small class="profile-help">Include your country code so your service provider can reach you by phone or SMS.</small></div>
    <div class="page-actions"><button class="btn btn-primary" type="submit">Save Contact Information</button></div>
  </form>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; require __DIR__ . '/includes/footer-end.php'; ?>
