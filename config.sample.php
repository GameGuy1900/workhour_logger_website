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

// Weekly hour target. Any shortfall in a week is added on top of this
// amount for the following week.
define('WEEKLY_TARGET_HOURS', 12);
