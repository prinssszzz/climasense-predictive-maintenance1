<?php
require_once __DIR__ . '/includes/auth.php';
if (!hash_equals($_SESSION['cs_google_state'] ?? '', $_GET['state'] ?? '') || empty($_GET['code'])) exit('Invalid Google sign-in request.');
unset($_SESSION['cs_google_state']);
$body=http_build_query(['code'=>$_GET['code'],'client_id'=>CS_GOOGLE_CLIENT_ID,'client_secret'=>CS_GOOGLE_CLIENT_SECRET,'redirect_uri'=>cs_google_redirect_uri(),'grant_type'=>'authorization_code']);
$ctx=stream_context_create(['http'=>['method'=>'POST','header'=>'Content-Type: application/x-www-form-urlencoded','content'=>$body,'ignore_errors'=>true]]);
$token=json_decode(file_get_contents('https://oauth2.googleapis.com/token',false,$ctx),true);
if (empty($token['access_token'])) exit('Google sign-in could not be completed.');
$profile=json_decode(file_get_contents('https://openidconnect.googleapis.com/v1/userinfo',false,stream_context_create(['http'=>['header'=>'Authorization: Bearer '.$token['access_token']]])),true);
if (empty($profile['email']) || empty($profile['sub']) || empty($profile['email_verified'])) exit('A verified Google email is required.');
$u = cs_user_row_by_email($profile['email']);
$db = cs_db();
// Determine role for new account (preserve from session if set), default to consumer
$role = $_SESSION['cs_google_role'] ?? 'consumer';
$mappedRole = cs_role_for_email($profile['email']);
if ($mappedRole) $role = $mappedRole;
if (!in_array($role, ['consumer','client_admin','super_admin','admin_level_1','admin_level_2'], true)) $role = 'consumer';
if (!$u) {
	// If creating a client_admin, also create an organization row
	if ($role === 'client_admin') {
		$db->beginTransaction();
		try {
			$db->prepare('INSERT INTO organizations(name) VALUES(?)')->execute([($profile['name'] ?? $profile['email'])."'s Shop"]);
			$orgId = (int)$db->lastInsertId();
			$q = $db->prepare("INSERT INTO users(organization_id,name,email,google_sub,avatar_url,auth_provider) VALUES(?,?,?,?,?, 'google')");
			$q->execute([$orgId, $profile['name'] ?? $profile['email'], $profile['email'], $profile['sub'], $profile['picture'] ?? null]);
			$id = (int)$db->lastInsertId();
			$db->prepare("INSERT INTO user_roles(user_id,role_id) SELECT ?,id FROM roles WHERE code=?")->execute([$id, $role]);
			$db->commit();
		} catch (Throwable $e) {
			if ($db->inTransaction()) $db->rollBack();
			exit('Could not create account from Google sign-in.');
		}
	} else {
		$q = $db->prepare("INSERT INTO users(name,email,google_sub,avatar_url,auth_provider) VALUES(?,?,?,?, 'google')");
		$q->execute([$profile['name'] ?? $profile['email'], $profile['email'], $profile['sub'], $profile['picture'] ?? null]);
		$id = (int)$db->lastInsertId();
		$db->prepare("INSERT INTO user_roles(user_id,role_id) SELECT ?,id FROM roles WHERE code=?")->execute([$id, $role]);
	}
	$u = cs_user_row_by_email($profile['email']);
} else {
	$db->prepare("UPDATE users SET google_sub=?,avatar_url=?,auth_provider='google' WHERE id=?")->execute([$profile['sub'], $profile['picture'] ?? null, $u['id']]);
	$id = (int)$u['id'];
	// If the Google sign-in requested a role and the user doesn't have it, add it (e.g., consumer -> admin if allowed)
	if ($role && $role !== '') {
		$has = $db->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON ur.role_id=r.id WHERE ur.user_id=? AND r.code=? LIMIT 1");
		$has->execute([$id, $role]);
		if (!$has->fetchColumn()) {
			$db->prepare("INSERT INTO user_roles(user_id,role_id) SELECT ?,id FROM roles WHERE code=?")->execute([$id, $role]);
		}
	}
}
cs_assign_role_for_email((int)($u['id'] ?? 0), $profile['email']);
// Clear the preserved google role
unset($_SESSION['cs_google_role']);

// If this Google email matches the configured super admin email (env or admin setting), ensure the user has the super_admin role
$superEmail = cs_get_super_admin_email();
if ($superEmail && strcasecmp($profile['email'], $superEmail) === 0) {
	$has = $db->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON ur.role_id=r.id WHERE ur.user_id=? AND r.code='super_admin' LIMIT 1");
	$has->execute([$id]);
	if (!$has->fetchColumn()) {
		$db->prepare("INSERT INTO user_roles(user_id,role_id) SELECT ?,id FROM roles WHERE code='super_admin'")->execute([$id]);
	}
}

cs_set_session(cs_user_row_by_email($profile['email']));
// Redirect to saved destination if present and safe
$dest = $_SESSION['cs_google_next'] ?? null;
if ($dest && !str_starts_with($dest, 'http')) {
	unset($_SESSION['cs_google_next']);
	header('Location: ' . $dest);
	exit;
}
header('Location: '.cs_home_for_user()); exit;
