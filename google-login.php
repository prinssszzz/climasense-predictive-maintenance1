<?php
require_once __DIR__ . '/includes/auth.php';
if (!cs_google_enabled()) exit('Google sign-in is not configured. Set CS_GOOGLE_CLIENT_ID and CS_GOOGLE_CLIENT_SECRET.');
header('Location: ' . cs_google_auth_url());
exit;
