<?php
require_once __DIR__ . '/includes/auth.php';
if (cs_is_logged_in()) { header('Location: ' . cs_home_for_user()); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<script>document.documentElement.classList.add('js-anim');try{if(localStorage.getItem('cs-theme')==='dark')document.documentElement.setAttribute('data-theme','dark')}catch(e){}</script>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width,initial-scale=1.0">
	<title>ClimaSense · Predictive Maintenance</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="css/style.css">
	<style>
		/* Slight tweak for the landing card logo */
		.auth-card .brand-mark{ width:64px; height:64px; border-radius:14px; background:radial-gradient(circle, rgba(94,221,153,.22), rgba(94,221,153,0) 72%); display:inline-flex; align-items:center; justify-content:center; margin:0 auto 12px; }
		.auth-card .brand-mark svg{ width:36px; height:36px; }
		.btn.btn-primary.btn-block{ padding:14px 18px; font-size:16px; border-radius:12px; }
		.auth-card .sub{ margin-bottom:18px; }
	</style>
</head>
<body>
	<div class="auth-shell">
		<div class="auth-card-wrap">
			<section class="auth-card landing-card" aria-hidden="true">
				<div class="landing-only">
					<div class="landing-brand-vertical">
						<span class="brand-mark logo-animate" aria-hidden="true">
							<img src="img/logo.svg" alt="ClimaSense" class="brand-img" width="64" height="64">
						</span>
						<span class="brand-text logo-text-animate"><strong>ClimaSense</strong></span>
						<a href="login.php" class="btn btn-primary landing-cta" role="button">
							<span class="cta-label">Log In</span>
							<span class="cta-arrow" aria-hidden="true">→</span>
						</a>
					</div>
				</div>
			</section>
		</div>
	</div>
</body>
</html>