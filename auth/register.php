<?php
$page_title = 'Student Registration';
require_once '../config/db.php';
require_once '../config/functions.php';
require_once '../config/constants.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    if (isStudent()) {
        redirect('../student/dashboard.php');
    } elseif (isAdmin()) {
        redirect('../admin/dashboard.php');
    }
}

$error = '';
$success = '';
$form_data = [];

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $student_id = sanitizeInput($_POST['student_id'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $year_of_registration = sanitizeInput($_POST['year_of_registration'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms = $_POST['terms'] ?? '';

    // Validation
    if (empty($full_name) || empty($student_id) || empty($email) || empty($phone) || empty($password) || empty($year_of_registration)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
        $error = "Password must be at least " . MIN_PASSWORD_LENGTH . " characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!$terms) {
        $error = "You must agree to the Terms and Conditions.";
    } else {
        // Check if student ID already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE student_id = ?");
        $stmt->execute([$student_id]);
        if ($stmt->fetch()) {
            $error = "Student ID already registered. Please contact admin.";
        } 
        // Check if email already exists
        else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "Email already registered. Please use a different email.";
            } else {
                // Handle profile picture upload - NOW REQUIRED
                $profile_pic = '';
                $upload_error = false;
                
                // Check if file was uploaded
                if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] === UPLOAD_ERR_NO_FILE) {
                    $error = "Profile picture is required. Please upload a photo.";
                    $upload_error = true;
                } elseif ($_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
                    $error = "Error uploading profile picture. Please try again.";
                    $upload_error = true;
                }
                
                if (!$upload_error && empty($error)) {
                    $upload_dir = '../assets/uploads/Students-profile/';
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_name = time() . '_' . basename($_FILES['profile_pic']['name']);
                    $file_path = $upload_dir . $file_name;
                    $file_type = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                    
                    // Allowed file types
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    
                    if (in_array($file_type, $allowed_types)) {
                        // Max file size 5MB
                        if ($_FILES['profile_pic']['size'] <= 5 * 1024 * 1024) {
                            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $file_path)) {
                                $profile_pic = $file_name;
                            } else {
                                $error = "Failed to upload profile picture.";
                            }
                        } else {
                            $error = "Profile picture must be less than 5MB.";
                        }
                    } else {
                        $error = "Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.";
                    }
                }
                
                if (empty($error)) {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $verification_token = generateToken();
                    
                    try {
                        // Insert user
                        $stmt = $pdo->prepare("
                            INSERT INTO users (full_name, student_id, email, phone, password, profile_pic, year_of_registration, verification_token, role, is_active, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'student', 1, NOW())
                        ");
                        
                        if ($stmt->execute([$full_name, $student_id, $email, $phone, $hashed_password, $profile_pic, $year_of_registration, $verification_token])) {
                            $user_id = $pdo->lastInsertId();
                            
                            // Create clearance records for the new student
                            if (function_exists('syncStudentClearanceRecords')) {
                                $syncResult = syncStudentClearanceRecords($pdo, $user_id);
                                $records_created = $syncResult['created'] ?? 0;
                            } else {
                                $records_created = 0;
                            }
                            
                            // Log activity
                            logActivity($pdo, $user_id, 'Registration', "Student registered successfully. Created {$records_created} clearance records.");
                            
                            $success = "Registration successful! Your account has been created with {$records_created} clearance records.";
                            
                            // Clear form data
                            $form_data = [];
                            
                            // Redirect to login after 3 seconds
                            echo '<script>
                                setTimeout(function() {
                                    window.location.href = "login.php";
                                }, 3000);
                            </script>';
                        } else {
                            $error = "Registration failed. Please try again.";
                        }
                    } catch (PDOException $e) {
                        $error = "Database error: " . $e->getMessage();
                    }
                }
            }
        }
    }
    
    // Store form data for repopulation
    $form_data = [
        'full_name' => $full_name, 
        'student_id' => $student_id, 
        'email' => $email, 
        'phone' => $phone,
        'year_of_registration' => $year_of_registration
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #800020 0%, #5a0016 100%);
            min-height: 100vh;
        }
        
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 550px;
            width: 100%;
            animation: fadeInUp 0.6s ease;
        }
        
        /* Top Bar with Back Button */
        .top-bar {
            padding: 15px 25px 0 25px;
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: none;
            color: #800020;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .back-btn:hover {
            transform: translateX(-3px);
            color: #5a0016;
        }
        
        .logo-small {
            height: 50px;
            width: auto;
            max-height: 50px;
        }
        
        /* Header */
        .register-header {
            background: #800020;
            color: white;
            padding: 25px 30px 30px;
            text-align: center;
        }
        
        /* BIGGER LOGO - Increased to 180px */
        .register-header .logo-header {
            height: 180px;
            width: auto;
            margin-bottom: 20px;
            max-height: 180px;
            filter: none;
            background: transparent;
        }
        
        .register-header h3 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .register-header p {
            margin: 5px 0 0;
            opacity: 0.85;
            font-size: 0.9rem;
        }
        
        /* Body */
        .register-body {
            padding: 30px 30px 35px;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group label {
            font-weight: 500;
            margin-bottom: 6px;
            color: #333;
            display: block;
            font-size: 0.9rem;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #800020;
            width: 18px;
        }
        
        .form-group label .required-star {
            color: #dc3545;
        }
        
        .form-control {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 11px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .form-control:focus {
            border-color: #800020;
            box-shadow: 0 0 0 0.2rem rgba(128, 0, 32, 0.25);
        }
        
        .form-control-file {
            padding: 8px 0;
        }
        
        .profile-pic-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #800020;
            margin-top: 10px;
            display: none;
        }
        
        .profile-pic-preview.show {
            display: inline-block;
        }
        
        .text-muted-small {
            font-size: 11px;
            color: #888;
            margin-top: 4px;
        }
        
        .password-requirements {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }
        
        .btn-register {
            background: #800020;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 13px;
            font-weight: 600;
            width: 100%;
            font-size: 16px;
            transition: all 0.3s ease;
            margin-top: 5px;
        }
        
        .btn-register:hover {
            background: #5a0016;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128, 0, 32, 0.3);
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid #eee;
            font-size: 0.9rem;
        }
        
        .login-link a {
            color: #800020;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 0.9rem;
            padding: 12px 16px;
        }
        
        .alert i {
            margin-right: 8px;
        }
        
        /* Form Check */
        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-check-input {
            margin-top: 0;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .form-check-label {
            font-size: 0.9rem;
            color: #555;
            cursor: pointer;
        }
        
        .form-check-label a {
            color: #800020;
            text-decoration: none;
        }
        
        .form-check-label a:hover {
            text-decoration: underline;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .register-card {
                margin: 15px;
            }
            
            .register-body {
                padding: 20px;
            }
            
            .register-header {
                padding: 20px 20px 25px;
            }
            
            .register-header .logo-header {
                height: 120px;
                max-height: 120px;
            }
            
            .logo-small {
                height: 40px;
                max-height: 40px;
            }
            
            .top-bar {
                padding: 12px 18px 0 18px;
            }
            
            .profile-pic-preview {
                width: 100px;
                height: 100px;
            }
        }
        
        @media (max-width: 576px) {
            .register-container {
                padding: 10px;
            }
            
            .register-body {
                padding: 15px;
            }
            
            .register-header h3 {
                font-size: 1.2rem;
            }
            
            .register-header .logo-header {
                height: 100px;
                max-height: 100px;
            }
            
            .logo-small {
                height: 35px;
                max-height: 35px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <!-- Top Bar: Back Button -->
            <div class="top-bar">
                <a href="javascript:history.back()" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
            
            <!-- Header with BIGGER Logo -->
            <div class="register-header">
                <img src="/assets/uploads/Students-profile/logo.png" alt="AFRU Logo" class="logo-header">
                <h3>Student Registration</h3>
                <p>Create your account to start the clearance process</p>
            </div>
            
            <!-- Body -->
            <div class="register-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="registerForm" enctype="multipart/form-data">
                    <!-- Profile Picture - REQUIRED -->
                    <div class="form-group text-center">
                        <label><i class="fas fa-camera"></i> Profile Picture <span class="required-star">*</span></label>
                        <input type="file" class="form-control form-control-file" name="profile_pic" id="profile_pic" accept="image/*" required>
                        <img id="profilePreview" class="profile-pic-preview" alt="Profile Preview">
                        <div class="text-muted-small">Required. Max 5MB. JPG, PNG, GIF, WEBP</div>
                    </div>
                    
                    <!-- Full Name -->
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Full Name <span class="required-star">*</span></label>
                        <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($form_data['full_name'] ?? ''); ?>" required placeholder="Enter your full name">
                    </div>
                    
                    <!-- Student ID -->
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Enter Reg No <span class="required-star">*</span></label>
                        <input type="text" class="form-control" name="student_id" value="<?php echo htmlspecialchars($form_data['student_id'] ?? ''); ?>" required placeholder="e.g., 23/001BIT/U">
                        <div class="text-muted-small">Enter your university reg no</div>
                    </div>
                    
                    <!-- Email -->
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address <span class="required-star">*</span></label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required placeholder="motome@gmail.com">
                    </div>
                    
                    <!-- Phone -->
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone Number <span class="required-star">*</span></label>
                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>" required placeholder="+256 700 000 000">
                    </div>
                    
                    <!-- Year of Registration - TEXT INPUT -->
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Year of Registration <span class="required-star">*</span></label>
                        <input type="text" class="form-control" name="year_of_registration" value="<?php echo htmlspecialchars($form_data['year_of_registration'] ?? ''); ?>" required placeholder="e.g., 2024">
                        <div class="text-muted-small">Enter the year you registered (e.g., 2024)</div>
                    </div>
                    
                    <!-- Password -->
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password <span class="required-star">*</span></label>
                        <input type="password" class="form-control" name="password" id="password" required placeholder="Create a password">
                        <div class="password-requirements">
                            <i class="fas fa-info-circle"></i> Minimum 8 characters
                        </div>
                    </div>
                    
                    <!-- Confirm Password -->
                    <div class="form-group">
                        <label><i class="fas fa-check-circle"></i> Confirm Password <span class="required-star">*</span></label>
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" required placeholder="Confirm your password">
                    </div>
                    
                    <!-- Terms -->
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="terms" id="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Submit -->
                    <button type="submit" class="btn-register">
                        <i class="fas fa-user-plus"></i> Register Account
                    </button>
                    
                    <!-- Login Link -->
                    <div class="login-link">
                        Already have an account? <a href="login.php">Login here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: #800020; color: white;">
                    <h5 class="modal-title"><i class="fas fa-file-contract me-2"></i>Terms and Conditions</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Account Responsibility</h6>
                    <p>You are responsible for maintaining the confidentiality of your account credentials.</p>
                    
                    <h6>2. Accurate Information</h6>
                    <p>You must provide accurate and complete information during registration.</p>
                    
                    <h6>3. Clearance Process</h6>
                    <p>All clearance requirements must be completed as per university policies.</p>
                    
                    <h6>4. Document Submission</h6>
                    <p>Submitted documents must be genuine and verifiable.</p>
                    
                    <h6>5. Privacy Policy</h6>
                    <p>Your personal data will be protected according to our privacy policy.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" style="background: #800020; border: none; padding: 10px 30px;">I Agree</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password match validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
        });
        
        // Real-time password match indicator
        $('#confirm_password').on('keyup', function() {
            const password = $('#password').val();
            const confirm = $(this).val();
            
            if (password === confirm && password !== '') {
                $(this).css('border-color', '#10b981');
            } else {
                $(this).css('border-color', '#ddd');
            }
        });
        
        // Profile picture preview
        $('#profile_pic').on('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#profilePreview').attr('src', e.target.result);
                    $('#profilePreview').addClass('show');
                };
                reader.readAsDataURL(file);
            } else {
                $('#profilePreview').removeClass('show');
            }
        });
    </script>
</body>
</html>