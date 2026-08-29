# ClimaSense setup

1. Start Apache and MySQL in XAMPP, then open `http://localhost/phpmyadmin`.
2. Choose **Import**, select [database/climasense.sql](database/climasense.sql), and run it. This creates the `climasense` database and its RBAC, air-conditioner, sensor, prediction, alert, and maintenance tables.
3. The local XAMPP defaults in `includes/config.php` use `root` with a blank password. Change them (or set `CS_DB_HOST`, `CS_DB_NAME`, `CS_DB_USER`, and `CS_DB_PASS`) if yours differs.
4. Register either a **Client administrator** (shop owner/admin) or a **Consumer** (air-conditioner owner). Client administrators enter the predictive-maintenance dashboard; consumers enter the restricted My Air Conditioner portal.

## Google login

Create an OAuth 2.0 Web application in Google Cloud Console and add this authorized redirect URI:

`http://localhost/climasense-predictive-maintenance1/google-callback.php`

Set its client ID and client secret as `CS_GOOGLE_CLIENT_ID` and `CS_GOOGLE_CLIENT_SECRET` in your Apache/XAMPP environment, then restart Apache. Google login creates a consumer account for a new verified Google email; an existing local account retains its assigned RBAC role.

Do not commit real Google secrets to `includes/config.php`.
