<?php
/**
 * SOC Reporting System - Application Configuration
 */

// Set timezone to match system/MySQL timezone
date_default_timezone_set('Europe/Vienna');

define('APP_NAME', 'SOC Reporting System');
define('APP_VERSION', '1.0.0');
define('APP_ROOT', dirname(__DIR__));

// Session settings
define('SESSION_LIFETIME', 3600);       // 1 hour
define('SESSION_NAME', 'SOC_SESSION');
define('SESSION_SECURE', false);        // Set true if using HTTPS
define('SESSION_HTTPONLY', true);

// Auth settings
define('AUTH_MAX_ATTEMPTS', 5);
define('AUTH_LOCKOUT_TIME', 900);       // 15 minutes
define('AUTH_BCRYPT_COST', 12);
define('ADMIN_SESSION_LIFETIME', 1800); // 30 minutes for admin sessions

// CSRF token lifetime
define('CSRF_TOKEN_LIFETIME', 3600);

// Pagination
define('REPORTS_PER_PAGE', 25);

// Date/time format
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i:s');

// Event ID format: M{machine_id}-{YYYYMMDD}-{sequential}
define('EVENT_ID_PREFIX', 'M');

// Supported languages
define('LANGUAGES', serialize(['en', 'de']));
define('DEFAULT_LANGUAGE', 'en');

// Number of machine slots
define('MACHINE_SLOTS', 4);
