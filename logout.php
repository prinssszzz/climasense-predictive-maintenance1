<?php
require_once __DIR__ . '/includes/auth.php';
cs_logout();
header('Location: login.php');
exit;
