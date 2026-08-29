<?php
/** Local XAMPP defaults. Prefer environment variables outside development. */
define('CS_DB_HOST', getenv('CS_DB_HOST') ?: '127.0.0.1');
define('CS_DB_NAME', getenv('CS_DB_NAME') ?: 'climasense');
define('CS_DB_USER', getenv('CS_DB_USER') ?: 'root');
define('CS_DB_PASS', getenv('CS_DB_PASS') ?: '');
define('CS_GOOGLE_CLIENT_ID', getenv('CS_GOOGLE_CLIENT_ID') ?: '');
define('CS_GOOGLE_CLIENT_SECRET', getenv('CS_GOOGLE_CLIENT_SECRET') ?: '');
