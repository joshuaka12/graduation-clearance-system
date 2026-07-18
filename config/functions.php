<?php
// Helper functions

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

function isDepartmentHead() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'department_head';
}

function isRegistrar() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'registrar';
}

function redirect($url) {
    // Prevent redirect loops - don't redirect to the same page
    $current_url = $_SERVER['REQUEST_URI'] ?? '';
    $current_path = parse_url($current_url, PHP_URL_PATH);
    $redirect_path = parse_url($url, PHP_URL_PATH);
    
    // If trying to redirect to the same page, stop to prevent loops
    if ($current_path === $redirect_path) {
        return;
    }
    
    // Also check if the URL contains the current script name
    $current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($url, $current_script) !== false) {
        // Trying to redirect to same script - prevent loop
        return;
    }
    
    header("Location: $url");
    exit();
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function updateLastLogin($pdo, $user_id) {
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    return $stmt->execute([$user_id]);
}

function logActivity($pdo, $user_id, $action, $details = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$user_id, $action, $details, $ip, $user_agent]);
}

/**
 * Get user by ID
 */
function getUserById($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * Get department by ID
 */
function getDepartmentById($pdo, $department_id) {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$department_id]);
    return $stmt->fetch();
}

/**
 * Get all active departments
 */
function getActiveDepartments($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE is_active = 1 ORDER BY clearance_order ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Check if a student is cleared for graduation
 */
function isStudentFullyCleared($pdo, $student_id) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT ci.id) as total_items,
            SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved_count
        FROM clearance_items ci
        LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $result = $stmt->fetch();
    
    return ($result['approved_count'] == $result['total_items'] && $result['total_items'] > 0);
}

/**
 * Sync student clearance records for all active departments
 * Creates missing clearance records for a student
 */
function syncStudentClearanceRecords($pdo, $student_id) {
    $result = [
        'created' => 0,
        'existing' => 0,
        'errors' => 0,
        'departments_processed' => 0
    ];
    
    try {
        // Get all active departments
        $stmt = $pdo->prepare("
            SELECT id, department_name 
            FROM departments 
            WHERE is_active = 1 
            ORDER BY clearance_order ASC, id ASC
        ");
        $stmt->execute();
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($departments)) {
            return $result;
        }
        
        // Get student's existing clearance records (department IDs only)
        $stmt = $pdo->prepare("
            SELECT DISTINCT d.id as department_id
            FROM student_clearance sc
            JOIN clearance_items ci ON sc.clearance_item_id = ci.id
            JOIN departments d ON ci.department_id = d.id
            WHERE sc.student_id = ?
        ");
        $stmt->execute([$student_id]);
        $existingDepartments = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        // Convert to associative array for faster lookup
        $existingDeptMap = array_flip($existingDepartments);
        
        // For each department, check if clearance items exist
        foreach ($departments as $dept) {
            $result['departments_processed']++;
            
            // Get all clearance items for this department
            $stmt = $pdo->prepare("
                SELECT id, item_name, requires_document, is_mandatory
                FROM clearance_items
                WHERE department_id = ? AND is_active = 1
                ORDER BY sort_order ASC, id ASC
            ");
            $stmt->execute([$dept['id']]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($items)) {
                continue; // No items for this department
            }
            
            // Check if student has any clearance records for this department
            $hasDeptRecords = isset($existingDeptMap[$dept['id']]);
            
            if (!$hasDeptRecords) {
                // Student has NO records for this department - create all items
                foreach ($items as $item) {
                    $stmt = $pdo->prepare("
                        INSERT INTO student_clearance (
                            student_id, 
                            department_id,
                            clearance_item_id, 
                            status, 
                            created_at
                        ) VALUES (?, ?, ?, 'pending', NOW())
                    ");
                    $stmt->execute([$student_id, $dept['id'], $item['id']]);
                    $result['created']++;
                }
            } else {
                // Student has SOME records for this department
                // Check if all items exist (in case new items were added)
                $stmt = $pdo->prepare("
                    SELECT clearance_item_id 
                    FROM student_clearance 
                    WHERE student_id = ? AND clearance_item_id IN (
                        SELECT id FROM clearance_items WHERE department_id = ?
                    )
                ");
                $stmt->execute([$student_id, $dept['id']]);
                $existingItemIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                $existingItemMap = array_flip($existingItemIds);
                
                // Create missing items only
                foreach ($items as $item) {
                    if (!isset($existingItemMap[$item['id']])) {
                        $stmt = $pdo->prepare("
                            INSERT INTO student_clearance (
                                student_id, 
                                department_id,
                                clearance_item_id, 
                                status, 
                                created_at
                            ) VALUES (?, ?, ?, 'pending', NOW())
                        ");
                        $stmt->execute([$student_id, $dept['id'], $item['id']]);
                        $result['created']++;
                    }
                }
                $result['existing']++;
            }
        }
        
        return $result;
        
    } catch (PDOException $e) {
        error_log("Student clearance sync error for student $student_id: " . $e->getMessage());
        $result['errors']++;
        return $result;
    }
}

/**
 * Batch sync all students for a new department
 */
function batchSyncNewDepartment($pdo, $department_id) {
    $result = ['students_processed' => 0, 'records_created' => 0];
    
    // Get all clearance items for the new department
    $stmt = $pdo->prepare("SELECT id FROM clearance_items WHERE department_id = ? AND is_active = 1");
    $stmt->execute([$department_id]);
    $item_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    if (empty($item_ids)) {
        return $result;
    }
    
    // Get all active students who don't have records for this department yet
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id 
        FROM users u
        WHERE u.role IN ('student', 'registrar') 
        AND u.is_active = 1
        AND NOT EXISTS (
            SELECT 1 
            FROM student_clearance sc 
            JOIN clearance_items ci ON sc.clearance_item_id = ci.id
            WHERE sc.student_id = u.id 
            AND ci.department_id = ?
        )
    ");
    $stmt->execute([$department_id]);
    $students = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    // Batch insert for all students and items
    $values = [];
    $params = [];
    foreach ($students as $student_id) {
        foreach ($item_ids as $item_id) {
            $values[] = "(?, ?, ?, 'pending', NOW())";
            $params[] = $student_id;
            $params[] = $department_id;
            $params[] = $item_id;
        }
    }
    
    if (!empty($values)) {
        $sql = "INSERT INTO student_clearance (student_id, department_id, clearance_item_id, status, created_at) 
                VALUES " . implode(', ', $values);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result['records_created'] = count($params) / 3;
        $result['students_processed'] = count($students);
    }
    
    return $result;
}
?>