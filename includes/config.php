<?php
/** Local XAMPP defaults. Prefer environment variables outside development. */
define('CS_DB_HOST', getenv('CS_DB_HOST') ?: '127.0.0.1');
define('CS_DB_NAME', getenv('CS_DB_NAME') ?: 'climasense');
define('CS_DB_USER', getenv('CS_DB_USER') ?: 'root');
define('CS_DB_PASS', getenv('CS_DB_PASS') ?: '');
// Google OAuth credentials. Provide via environment variables for production.
// I've set the client ID you provided as the local default; set CS_GOOGLE_CLIENT_SECRET
// as an environment variable or edit this file to provide the client secret.
define('CS_GOOGLE_CLIENT_ID', getenv('CS_GOOGLE_CLIENT_ID') ?: '951234724843-img0lqq73aj0knfq4jqtqtk0bv1g8t1r.apps.googleusercontent.com');
// Google OAuth secret should come from the environment for security. Do NOT commit secrets to source.
define('CS_GOOGLE_CLIENT_SECRET', getenv('CS_GOOGLE_CLIENT_SECRET') ?: '');

// Role-specific email mapping used to reserve dedicated inboxes for each role.
// You can override these via environment variables, or set your own values here.
define('CS_SUPER_ADMIN_EMAIL', getenv('CS_SUPER_ADMIN_EMAIL') ?: 'superadmin@climasense.local');
define('CS_ADMIN_TECHNICIAN_EMAIL', getenv('CS_ADMIN_TECHNICIAN_EMAIL') ?: 'technician@climasense.local');
define('CS_CONSUMER_EMAIL', getenv('CS_CONSUMER_EMAIL') ?: 'consumer@climasense.local');
define('CS_ROLE_EMAILS', [
    'super_admin' => CS_SUPER_ADMIN_EMAIL,
    'admin_level_1' => CS_ADMIN_TECHNICIAN_EMAIL,
    'consumer' => CS_CONSUMER_EMAIL,
]);
// Optional admin signup code. If set (e.g. in environment), anonymous users may
// register as a `client_admin` if they provide this exact code during signup.
define('CS_ADMIN_SIGNUP_CODE', getenv('CS_ADMIN_SIGNUP_CODE') ?: '');
