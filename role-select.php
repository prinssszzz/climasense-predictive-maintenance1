<?php
require_once __DIR__ . '/includes/auth.php';
if (cs_is_logged_in()) { header('Location: ' . cs_home_for_user()); exit; }
header('Location: signup.php');
exit;
