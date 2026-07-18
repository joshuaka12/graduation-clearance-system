<?php
// config/settings_helper.php
// Centralized settings loader for the entire system

// ============================================================
// SETTINGS FUNCTIONS
// ============================================================

/**
 * Get a system setting value by key
 * 
 * @param PDO $pdo Database connection
 * @param string $key Setting key
 * @param string $default Default value if setting not found
 * @return string Setting value or default
 */
function getSetting($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        error_log("Error getting setting '$key': " . $e->getMessage());
        return $default;
    }
}

/**
 * Get all system settings
 * 
 * @param PDO $pdo Database connection
 * @return array Associative array of all settings
 */
function getAllSettings($pdo) {
    $settings = [];
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Error getting all settings: " . $e->getMessage());
    }
    return $settings;
}

/**
 * Get the university name
 * 
 * @param PDO $pdo Database connection
 * @return string University name
 */
function getUniversityName($pdo) {
    return getSetting($pdo, 'university_name', 'Graduation Clearance System');
}

/**
 * Get the university logo path
 * 
 * @param PDO $pdo Database connection
 * @return string Logo filename or default
 */
function getUniversityLogo($pdo) {
    $logo = getSetting($pdo, 'university_logo', 'default-logo.png');
    // Check if the logo file exists
    if ($logo != 'default-logo.png' && file_exists('../assets/uploads/' . $logo)) {
        return $logo;
    }
    return 'default-logo.png';
}

/**
 * Get the university logo URL for use in HTML
 * 
 * @param PDO $pdo Database connection
 * @return string Full URL path to logo
 */
function getUniversityLogoUrl($pdo) {
    $logo = getUniversityLogo($pdo);
    $base_url = getBaseUrl();
    if ($logo != 'default-logo.png') {
        return $base_url . '/assets/uploads/' . $logo;
    }
    return $base_url . '/assets/uploads/default-logo.png';
}

/**
 * Get the academic year
 * 
 * @param PDO $pdo Database connection
 * @return string Academic year
 */
function getAcademicYear($pdo) {
    return getSetting($pdo, 'academic_year', '');
}

/**
 * Get the graduation year
 * 
 * @param PDO $pdo Database connection
 * @return string Graduation year
 */
function getGraduationYear($pdo) {
    return getSetting($pdo, 'graduation_year', '');
}

/**
 * Get the site name
 * 
 * @param PDO $pdo Database connection
 * @return string Site name
 */
function getSiteName($pdo) {
    return getSetting($pdo, 'site_name', getUniversityName($pdo));
}

/**
 * Get the site tagline
 * 
 * @param PDO $pdo Database connection
 * @return string Site tagline
 */
function getSiteTagline($pdo) {
    return getSetting($pdo, 'site_tagline', 'Your Pathway to Graduation');
}

/**
 * Get the primary color
 * 
 * @param PDO $pdo Database connection
 * @return string Primary color
 */
function getPrimaryColor($pdo) {
    return getSetting($pdo, 'primary_color', '#800020');
}

/**
 * Get the secondary color
 * 
 * @param PDO $pdo Database connection
 * @return string Secondary color
 */
function getSecondaryColor($pdo) {
    return getSetting($pdo, 'secondary_color', '#ffffff');
}

/**
 * Get the base URL of the system
 * 
 * @return string Base URL
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . $host;
}

/**
 * Get system information for display
 * 
 * @param PDO $pdo Database connection
 * @return array System information
 */
function getSystemInfo($pdo) {
    return [
        'university_name' => getUniversityName($pdo),
        'university_logo' => getUniversityLogo($pdo),
        'university_logo_url' => getUniversityLogoUrl($pdo),
        'academic_year' => getAcademicYear($pdo),
        'graduation_year' => getGraduationYear($pdo),
        'site_name' => getSiteName($pdo),
        'site_tagline' => getSiteTagline($pdo),
        'site_email' => getSetting($pdo, 'site_email', 'noreply@graduationclearance.com'),
        'primary_color' => getPrimaryColor($pdo),
        'secondary_color' => getSecondaryColor($pdo),
    ];
}

// ============================================================
// DISPLAY HELPER FUNCTIONS
// ============================================================

/**
 * Display the university name (echo with HTML escaping)
 * 
 * @param PDO $pdo Database connection
 */
function displayUniversityName($pdo) {
    echo htmlspecialchars(getUniversityName($pdo));
}

/**
 * Display the university logo
 * 
 * @param PDO $pdo Database connection
 * @param string $class Optional CSS class
 * @param string $alt Optional alt text
 */
function displayUniversityLogo($pdo, $class = '', $alt = '') {
    $logo_url = getUniversityLogoUrl($pdo);
    $name = getUniversityName($pdo);
    $alt_text = $alt ?: $name . ' Logo';
    echo '<img src="' . $logo_url . '" alt="' . htmlspecialchars($alt_text) . '" class="' . $class . '">';
}

/**
 * Display the academic year
 * 
 * @param PDO $pdo Database connection
 */
function displayAcademicYear($pdo) {
    echo htmlspecialchars(getAcademicYear($pdo));
}

/**
 * Display the graduation year
 * 
 * @param PDO $pdo Database connection
 */
function displayGraduationYear($pdo) {
    echo htmlspecialchars(getGraduationYear($pdo));
}

/**
 * Display the site name
 * 
 * @param PDO $pdo Database connection
 */
function displaySiteName($pdo) {
    echo htmlspecialchars(getSiteName($pdo));
}

// ============================================================
// REFRESH FUNCTION
// ============================================================

/**
 * Refresh dynamic settings from database
 * Call this after updating settings to reload constants
 */
function refreshSettings() {
    global $dynamic_settings, $pdo;
    if (isset($pdo) && $pdo) {
        $dynamic_settings = getAllSettings($pdo);
    }
}

// ============================================================
// COMPATIBILITY FUNCTIONS (for pages that don't pass $pdo)
// ============================================================

/**
 * Get setting without passing $pdo (uses global)
 * 
 * @param string $key Setting key
 * @param string $default Default value
 * @return string Setting value
 */
function getSettingGlobal($key, $default = '') {
    global $pdo;
    if (isset($pdo)) {
        return getSetting($pdo, $key, $default);
    }
    return $default;
}

/**
 * Get university name without passing $pdo
 * 
 * @return string University name
 */
function getUniversityNameGlobal() {
    global $pdo;
    if (isset($pdo)) {
        return getUniversityName($pdo);
    }
    return 'Graduation Clearance System';
}

/**
 * Get logo URL without passing $pdo
 * 
 * @return string Logo URL
 */
function getUniversityLogoUrlGlobal() {
    global $pdo;
    if (isset($pdo)) {
        return getUniversityLogoUrl($pdo);
    }
    return getBaseUrl() . '/assets/uploads/default-logo.png';
}

/**
 * Get academic year without passing $pdo
 * 
 * @return string Academic year
 */
function getAcademicYearGlobal() {
    global $pdo;
    if (isset($pdo)) {
        return getAcademicYear($pdo);
    }
    return '';
}

/**
 * Get graduation year without passing $pdo
 * 
 * @return string Graduation year
 */
function getGraduationYearGlobal() {
    global $pdo;
    if (isset($pdo)) {
        return getGraduationYear($pdo);
    }
    return '';
}
?>