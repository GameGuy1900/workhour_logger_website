<?php
// Copy this file to config.php and fill in the values from your
// DirectAdmin MySQL Management page, then upload config.php too.
// config.php is git-ignored so your real credentials never get committed.

// DirectAdmin database host is almost always "localhost".
define('DB_HOST', 'localhost');

// DirectAdmin prefixes database/user names with your account username,
// e.g. "username_workhours" and "username_wh_user".
define('DB_NAME', 'username_workhours');
define('DB_USER', 'username_wh_user');
define('DB_PASS', 'change-me');

// Weekly target hours, surplus pay rate, and the savings goal are no
// longer set here — edit them from the Settings page in the app
// (settings.php) once it's deployed. They're stored in the `settings`
// table created by schema.sql.
