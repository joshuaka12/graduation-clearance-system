<?php
// config/constants.php
// System Constants with dynamic settings from database

// ============================================================
// BASE CONSTANTS (Static - not loaded from database)
// ============================================================

// Base URL - automatically detect or set manually
define('BASE_URL', '/');

// Current Year
define('CURRENT_YEAR', date('Y'));

// Password Requirements
define('MIN_PASSWORD_LENGTH', 8);
define('MAX_PASSWORD_LENGTH', 50);

// Session Timeout (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Upload Settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'gif', 'webp', 'svg']);

// Date Format Settings
define('DATE_FORMAT', 'F d, Y');
define('DATETIME_FORMAT', 'F d, Y h:i A');

// Pagination Settings
define('ITEMS_PER_PAGE', 15);

// Clearance Status Constants
define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');
define('STATUS_WAIVED', 'waived');

// User Roles
define('ROLE_STUDENT', 'student');
define('ROLE_ADMIN', 'admin');
define('ROLE_DEPARTMENT_HEAD', 'department_head');
define('ROLE_REGISTRAR', 'registrar');

// ============================================================
// DYNAMIC SETTINGS (Loaded from database)
// ============================================================

// Check if database is available and load settings
function loadDynamicSettings() {
    try {
        // Only attempt to load if pdo is available
        global $pdo;
        if (isset($pdo) && $pdo) {
            return loadSettingsFromDatabase($pdo);
        }
        
        // Try to establish database connection if not available
        require_once __DIR__ . '/db.php';
        if (isset($pdo) && $pdo) {
            return loadSettingsFromDatabase($pdo);
        }
        
        // Fallback to default values if database connection fails
        return getDefaultSettings();
        
    } catch (Exception $e) {
        // If database fails, use default values
        error_log("Failed to load dynamic settings: " . $e->getMessage());
        return getDefaultSettings();
    }
}

function loadSettingsFromDatabase($pdo) {
    $settings = [];
    try {
        // Check if system_settings table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    } catch (Exception $e) {
        error_log("Database error loading settings: " . $e->getMessage());
    }
    return $settings;
}

function getDefaultSettings() {
    return [
        'university_name' => 'Graduation Clearance System',
        'university_logo' => 'default-logo.png',
        'site_name' => 'Graduation Clearance System',
        'site_tagline' => 'Your Pathway to Graduation',
        'site_email' => 'noreply@graduationclearance.com',
        'academic_year' => date('Y') . '/' . (date('Y') + 1),
        'graduation_year' => date('Y'),
        'primary_color' => '#800020',
        'secondary_color' => '#ffffff',
    ];
}

// Load dynamic settings
$dynamic_settings = loadDynamicSettings();

// ============================================================
// DEFINE DYNAMIC CONSTANTS
// ============================================================

// University Information
define('UNIVERSITY_NAME', $dynamic_settings['university_name'] ?? 'Graduation Clearance System');
define('UNIVERSITY_LOGO', $dynamic_settings['university_logo'] ?? 'default-logo.png');
define('SITE_NAME', $dynamic_settings['site_name'] ?? $dynamic_settings['university_name'] ?? 'Graduation Clearance System');
define('SITE_TAGLINE', $dynamic_settings['site_tagline'] ?? 'Your Pathway to Graduation');
define('SITE_EMAIL', $dynamic_settings['site_email'] ?? 'noreply@graduationclearance.com');
define('ACADEMIC_YEAR', $dynamic_settings['academic_year'] ?? (date('Y') . '/' . (date('Y') + 1)));
define('GRADUATION_YEAR', $dynamic_settings['graduation_year'] ?? date('Y'));
define('PRIMARY_COLOR', $dynamic_settings['primary_color'] ?? '#800020');
define('SECONDARY_COLOR', $dynamic_settings['secondary_color'] ?? '#ffffff');

// ============================================================
// DERIVED CONSTANTS
// ============================================================

// Logo URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL_FULL', $protocol . $host);
define('LOGO_PATH', '/assets/uploads/');
define('LOGO_URL', LOGO_PATH . UNIVERSITY_LOGO);
define('LOGO_FULL_URL', BASE_URL_FULL . LOGO_PATH . UNIVERSITY_LOGO);

// ============================================================
// LEGACY CONSTANTS (for backward compatibility)
// ============================================================

// Alias for backward compatibility
if (!defined('SITE_NAME')) {
    define('SITE_NAME', UNIVERSITY_NAME);
}

// Database Constants (if not already defined in db.php)
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'clearance_system');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}

// ============================================================
// HELPER FUNCTIONS - MOVED TO settings_helper.php
// ============================================================
// NOTE: All helper functions (getSetting, getUniversityName, etc.)
// have been moved to settings_helper.php to avoid redeclaration.
// The functions below are kept for backward compatibility but
// will be removed in a future version.

// ============================================================
// START SESSION IF NOT STARTED
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// DEBUGGING - Uncomment to debug
// ============================================================

// if (isset($_GET['debug_settings'])) {
//     echo '<pre>';
//     echo 'UNIVERSITY_NAME: ' . UNIVERSITY_NAME . "\n";
//     echo 'UNIVERSITY_LOGO: ' . UNIVERSITY_LOGO . "\n";
//     echo 'SITE_NAME: ' . SITE_NAME . "\n";
//     echo 'ACADEMIC_YEAR: ' . ACADEMIC_YEAR . "\n";
//     echo 'GRADUATION_YEAR: ' . GRADUATION_YEAR . "\n";
//     echo 'LOGO_URL: ' . LOGO_FULL_URL . "\n";
//     echo 'Dynamic Settings: ';
//     print_r($dynamic_settings);
//     echo '</pre>';
//     exit;
// }
?>