-- ======================================================
-- GRADUATION CLEARANCE SYSTEM - COMPLETE DATABASE
-- ======================================================

DROP DATABASE IF EXISTS clearance_system;
CREATE DATABASE clearance_system;
USE clearance_system;

-- ======================================================
-- 1. USERS TABLE
-- ======================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    student_id VARCHAR(20) UNIQUE,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    profile_pic VARCHAR(255) DEFAULT 'default-avatar.png',
    role ENUM('student', 'admin', 'department_head', 'registrar') DEFAULT 'student',
    is_active BOOLEAN DEFAULT TRUE,
    verification_token VARCHAR(255),
    reset_token VARCHAR(255),
    reset_token_expiry DATETIME,
    remember_token VARCHAR(255),
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ======================================================
-- 2. DEPARTMENTS TABLE
-- ======================================================
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    department_name VARCHAR(100) NOT NULL,
    department_code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    head_of_department VARCHAR(100),
    hod_email VARCHAR(100),
    hod_user_id INT,
    clearance_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    is_mandatory BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hod_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ======================================================
-- 3. CLEARANCE ITEMS TABLE
-- ======================================================
CREATE TABLE clearance_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    department_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    description TEXT,
    requires_document BOOLEAN DEFAULT FALSE,
    document_types VARCHAR(255),
    is_mandatory BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- ======================================================
-- 4. STUDENT CLEARANCE TABLE
-- ======================================================
CREATE TABLE student_clearance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    department_id INT NOT NULL,
    clearance_item_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'waived') DEFAULT 'pending',
    remarks TEXT,
    document_path VARCHAR(255),
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (clearance_item_id) REFERENCES clearance_items(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_clearance (student_id, clearance_item_id)
);

-- ======================================================
-- 5. ACTIVITY LOGS TABLE
-- ======================================================
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ======================================================
-- 6. NOTIFICATIONS TABLE
-- ======================================================
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ======================================================
-- 7. CONTACT MESSAGES TABLE (FIXED)
-- ======================================================
CREATE TABLE contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ======================================================
-- 8. CLEARANCE CERTIFICATES TABLE
-- ======================================================
CREATE TABLE clearance_certificates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    certificate_number VARCHAR(50) UNIQUE NOT NULL,
    file_path VARCHAR(255),
    issued_date DATE,
    issued_by INT,
    qr_code VARCHAR(255),
    verification_code VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ======================================================
-- 9. SYSTEM SETTINGS TABLE
-- ======================================================
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ======================================================
-- 10. DOCUMENTS TABLE
-- ======================================================
CREATE TABLE documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    clearance_item_id INT,
    document_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT,
    file_type VARCHAR(50),
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verified_by INT,
    verified_at TIMESTAMP NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (clearance_item_id) REFERENCES clearance_items(id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ======================================================
-- 11. SUPPORT TICKETS TABLE
-- ======================================================
CREATE TABLE support_tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    category VARCHAR(50),
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- ======================================================
-- 12. ACADEMIC YEARS TABLE
-- ======================================================
CREATE TABLE academic_years (
    id INT PRIMARY KEY AUTO_INCREMENT,
    year_name VARCHAR(20) NOT NULL,
    semester ENUM('Fall', 'Spring', 'Summer') NOT NULL,
    start_date DATE,
    end_date DATE,
    is_current BOOLEAN DEFAULT FALSE,
    graduation_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ======================================================
-- INSERT DEFAULT DATA
-- ======================================================

-- Insert admin (password: password)
INSERT INTO users (full_name, student_id, email, phone, password, role, is_active) 
VALUES ('System Administrator', NULL, 'admin@clearance.edu', '+1234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);

-- Insert sample student (password: password)
INSERT INTO users (full_name, student_id, email, phone, password, role, is_active) 
VALUES ('John Doe', 'STU2024001', 'student@clearance.edu', '+1234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 1);

-- Insert system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'Graduation Clearance System', 'text', 'Name of the system'),
('site_email', 'admin@clearance.edu', 'text', 'System email address'),
('current_semester', 'Spring 2024', 'text', 'Current academic semester'),
('graduation_date', '2024-06-15', 'text', 'Upcoming graduation date'),
('clearance_deadline', '2024-06-01', 'text', 'Clearance completion deadline'),
('enable_registration', 'true', 'boolean', 'Allow student registration'),
('maintenance_mode', 'false', 'boolean', 'System maintenance mode');

-- ======================================================
-- VERIFY DATA
-- ======================================================
SELECT 'Database created successfully!' AS Status;
SELECT COUNT(*) as Total_Users FROM users;
SELECT COUNT(*) as Total_Settings FROM system_settings;