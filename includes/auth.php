<?php
require_once __DIR__ . '/db.php';
if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) session_start();
function cs_ensure_user_phone_column(): void {
    $column = cs_db()->query("SHOW COLUMNS FROM users LIKE 'phone'")->fetch();
    if (!$column) cs_db()->exec('ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL AFTER email');
}
function cs_ensure_consumer_unit_schema(): void {
    $db = cs_db();
    $organizationColumn = $db->query("SHOW COLUMNS FROM ac_units LIKE 'organization_id'")->fetch();
    if ($organizationColumn && $organizationColumn['Null'] === 'NO') {
        $foreignKeyQuery = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ac_units' AND COLUMN_NAME='organization_id' AND REFERENCED_TABLE_NAME='organizations' LIMIT 1");
        $foreignKey = $foreignKeyQuery->fetchColumn();
        if ($foreignKey) $db->exec('ALTER TABLE ac_units DROP FOREIGN KEY `' . str_replace('`', '``', $foreignKey) . '`');
        try {
            $db->exec('ALTER TABLE ac_units MODIFY organization_id BIGINT UNSIGNED NULL');
            if ($foreignKey) $db->exec('ALTER TABLE ac_units ADD CONSTRAINT `' . str_replace('`', '``', $foreignKey) . '` FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE');
        } catch (Throwable $e) {
            if ($foreignKey) {
                $remaining = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ac_units' AND COLUMN_NAME='organization_id' AND REFERENCED_TABLE_NAME='organizations' LIMIT 1")->fetchColumn();
                if (!$remaining) $db->exec('ALTER TABLE ac_units ADD CONSTRAINT `' . str_replace('`', '``', $foreignKey) . '` FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE');
            }
            throw $e;
        }
    }
    foreach ([
        'brand' => "ALTER TABLE ac_units ADD COLUMN brand VARCHAR(80) NULL AFTER name",
        'ac_type' => "ALTER TABLE ac_units ADD COLUMN ac_type VARCHAR(40) NOT NULL DEFAULT 'Split-Type' AFTER brand",
        'capacity' => 'ALTER TABLE ac_units ADD COLUMN capacity VARCHAR(30) NULL AFTER model',
        'verification_status' => "ALTER TABLE ac_units ADD COLUMN verification_status ENUM('pending','active','rejected') NOT NULL DEFAULT 'active' AFTER installed_on",
    ] as $columnName => $alterSql) {
        $column = $db->query('SHOW COLUMNS FROM ac_units LIKE ' . $db->quote($columnName))->fetch();
        if (!$column) $db->exec($alterSql);
    }
}
function cs_roles_for_user(int $id): array { $q=cs_db()->prepare('SELECT r.code FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?'); $q->execute([$id]); return array_column($q->fetchAll(),'code'); }
function cs_user_row_by_email(string $email): ?array { $q=cs_db()->prepare('SELECT * FROM users WHERE email=? LIMIT 1'); $q->execute([strtolower(trim($email))]); return $q->fetch() ?: null; }
function cs_role_email_map(): array { return [
    'super_admin' => defined('CS_ROLE_EMAILS') && isset(CS_ROLE_EMAILS['super_admin']) ? (string) CS_ROLE_EMAILS['super_admin'] : '',
    'admin_level_1' => defined('CS_ROLE_EMAILS') && isset(CS_ROLE_EMAILS['admin_level_1']) ? (string) CS_ROLE_EMAILS['admin_level_1'] : '',
    'consumer' => defined('CS_ROLE_EMAILS') && isset(CS_ROLE_EMAILS['consumer']) ? (string) CS_ROLE_EMAILS['consumer'] : '',
]; }
function cs_role_for_email(string $email): ?string {
    $email = strtolower(trim($email));
    foreach (cs_role_email_map() as $role => $mappedEmail) {
        if ($mappedEmail !== '' && strtolower(trim((string) $mappedEmail)) === $email) return $role;
    }
    return null;
}
function cs_email_is_reserved_for_role(string $email, string $role): bool {
    $mappedRole = cs_role_for_email($email);
    if ($mappedRole === null) return false;
    return $mappedRole !== $role;
}
function cs_assign_role_for_email(int $userId, string $email): void {
    $mappedRole = cs_role_for_email($email);
    if ($mappedRole === null) return;
    $db = cs_db();
    $has = $db->prepare('SELECT 1 FROM user_roles ur JOIN roles r ON ur.role_id=r.id WHERE ur.user_id=? AND r.code=? LIMIT 1');
    $has->execute([$userId, $mappedRole]);
    if (!$has->fetchColumn()) {
        $db->prepare('INSERT INTO user_roles(user_id,role_id) SELECT ?,id FROM roles WHERE code=?')->execute([$userId, $mappedRole]);
    }
}
function cs_set_session(array $u): void { $_SESSION['cs_user']=['id'=>(int)$u['id'],'name'=>$u['name'],'email'=>$u['email'],'organization_id'=>$u['organization_id'] ? (int)$u['organization_id'] : null,'roles'=>cs_roles_for_user((int)$u['id']),'role'=>cs_primary_role_for_user(['id'=>(int)$u['id'],'name'=>$u['name'],'email'=>$u['email'],'organization_id'=>$u['organization_id'] ? (int)$u['organization_id'] : null,'roles'=>cs_roles_for_user((int)$u['id'])])]; }
function cs_create_user(string $name,string $email,string $password,string $role='consumer'): array {
	$name = trim($name);
	$email = strtolower(trim($email));
	if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) return [false, 'Provide a name, valid email, and an 8+ character password.'];
	if (!in_array($role, ['client_admin', 'consumer', 'super_admin', 'admin_level_1', 'admin_level_2'], true)) return [false, 'Choose a valid account type.'];
	if (cs_email_is_reserved_for_role($email, $role)) {
		$roleLabel = ['super_admin' => 'Super Admin', 'admin_level_1' => 'Admin / Technician', 'consumer' => 'Consumer', 'client_admin' => 'Client Admin', 'admin_level_2' => 'Admin Level 2'][$role] ?? 'this role';
		return [false, 'This email is reserved for the ' . $roleLabel . ' account.'];
	}
	if (cs_user_row_by_email($email)) return [false, 'An account with that email already exists.'];
	$db = cs_db();
	$db->beginTransaction();
	try {
		$orgId = null;
		if ($role === 'client_admin') {
			$q = $db->prepare('INSERT INTO organizations(name) VALUES(?)');
			$q->execute([$name . "'s Shop"]);
			$orgId = (int)$db->lastInsertId();
		}
		$q = $db->prepare('INSERT INTO users(organization_id,name,email,password_hash) VALUES(?,?,?,?)');
		$q->execute([$orgId, $name, $email, password_hash($password, PASSWORD_DEFAULT)]);
		$id = (int)$db->lastInsertId();
		$q = $db->prepare('INSERT INTO user_roles(user_id,role_id) SELECT ?,id FROM roles WHERE code=?');
		$q->execute([$id, $role]);
		cs_assign_role_for_email($id, $email);
		$db->commit();
		return [true, null];
	} catch (Throwable $e) {
		if ($db->inTransaction()) $db->rollBack();
		return [false, 'Could not create the account.'];
	}
}
function cs_attempt_login(string $email,string $password): array { $u=cs_user_row_by_email($email); if (!$u || !$u['password_hash'] || !password_verify($password,$u['password_hash'])) return [false,'Incorrect email or password.']; cs_assign_role_for_email((int)$u['id'], $u['email']); cs_set_session($u); return [true,null]; }
function cs_current_user(): ?array { return $_SESSION['cs_user'] ?? null; }
function cs_is_logged_in(): bool { return cs_current_user() !== null; }
function cs_has_role(string $role): bool { return in_array($role,cs_current_user()['roles'] ?? [],true); }
function cs_primary_role_for_user(?array $user = null): ?string {
    $user = $user ?? cs_current_user();
    if (!$user) return null;
    $roles = $user['roles'] ?? [];
    $priority = ['super_admin', 'admin_level_1', 'admin_level_2', 'client_admin', 'consumer'];
    foreach ($priority as $role) {
        if (in_array($role, $roles, true)) return $role;
    }
    return $roles[0] ?? null;
}
function cs_user_can(string $permission, ?int $orgId = null): bool {
    if (!cs_is_logged_in()) return false;
    $user = cs_current_user();
    $roles = $user['roles'] ?? [];
    if (in_array('super_admin', $roles, true)) return true;
    // Consumer accounts only view their own assigned units. Older installs may
    // still have the former broad consumer permission mapping in the database.
    if (cs_primary_role_for_user($user) === 'consumer' && $permission !== 'units.view_own') return false;
    $db = cs_db();
    $q = $db->prepare('SELECT 1 FROM user_roles ur JOIN role_permissions rp ON rp.role_id = ur.role_id JOIN permissions p ON p.id = rp.permission_id WHERE ur.user_id = ? AND p.code = ? LIMIT 1');
    $q->execute([(int)$user['id'], $permission]);
    if ($q->fetchColumn()) return true;
    return false;
}
function cs_require_login(): void { if (!cs_is_logged_in()) { header('Location: login.php?next='.urlencode($_SERVER['REQUEST_URI'] ?? 'index.php')); exit; } }
function cs_require_permission(string $permission, ?int $orgId = null): void {
    cs_require_login();
    if (!cs_user_can($permission, $orgId)) {
        http_response_code(403);
        exit('You do not have permission to view this page.');
    }
}
function cs_require_role(string $role): void { cs_require_login(); if (!cs_has_role($role)) { http_response_code(403); exit('You do not have permission to view this page.'); } }
function cs_home_for_user(): string {
    $roles = cs_current_user()['roles'] ?? [];
    if (in_array('super_admin', $roles, true)) return 'super-admin-dashboard.php';
    if (in_array('admin_level_1', $roles, true) || in_array('admin_level_2', $roles, true) || in_array('client_admin', $roles, true)) return 'admin-dashboard.php';
    return 'consumer-dashboard.php';
}
function cs_google_enabled(): bool { return CS_GOOGLE_CLIENT_ID !== '' && CS_GOOGLE_CLIENT_SECRET !== ''; }
function cs_google_redirect_uri(): string { $scheme=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http'; return $scheme.'://'.$_SERVER['HTTP_HOST'].'/google-callback.php'; }
function cs_google_auth_url(): string { $_SESSION['cs_google_state']=bin2hex(random_bytes(32)); return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id'=>CS_GOOGLE_CLIENT_ID,'redirect_uri'=>cs_google_redirect_uri(),'response_type'=>'code','scope'=>'openid email profile','state'=>$_SESSION['cs_google_state'],'prompt'=>'select_account']); }
function cs_logout(): void { $_SESSION=[]; session_destroy(); }
/**
 * Return all users assigned to a given role code (e.g. 'consumer' or 'client_admin').
 */
function cs_users_by_role(string $role): array {
	$db = cs_db();
	$q = $db->prepare('SELECT u.* FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.code = ? ORDER BY u.created_at DESC');
	$q->execute([$role]);
	return $q->fetchAll();
}

function cs_get_consumers(): array { return cs_users_by_role('consumer'); }
function cs_get_client_admins(): array { return cs_users_by_role('client_admin'); }
