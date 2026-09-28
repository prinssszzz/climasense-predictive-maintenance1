<?php
require_once __DIR__ . '/includes/auth.php';
if (!cs_google_enabled()) exit('Google sign-in is not configured. Set CS_GOOGLE_CLIENT_ID and CS_GOOGLE_CLIENT_SECRET.');
// Preserve a next/redirect destination if provided
$next = $_GET['next'] ?? ($_POST['next'] ?? null);
if ($next) {
	// Basic safety: avoid open redirects to other hosts
	if (str_starts_with($next, 'http')) $next = null;
	else $_SESSION['cs_google_next'] = $next;
}
// Preserve a requested role (consumer or client_admin) if provided
$role = $_GET['role'] ?? ($_POST['role'] ?? null);
if ($role && in_array($role, ['consumer','client_admin'], true)) {
	$_SESSION['cs_google_role'] = $role;
}
header('Location: ' . cs_google_auth_url());
exit;
