<?php
$page_title = 'User Guides';
require_once 'config/db.php';
require_once 'config/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Clearance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #800020;
            --primary-dark: #5a0016;
            --primary-light: #9e0028;
            --primary-soft: rgba(128, 0, 32, 0.08);
            --gray-50: #fafafc;
            --gray-100: #f8f9fc;
            --gray-200: #e8ecef;
            --gray-300: #dce1e8;
            --gray-600: #6c7683;
            --gray-700: #4a5360;
            --gray-800: #2d3440;
            --success: #10b981;
            --info: #3b82f6;
            --warning: #f59e0b;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 12px 0;
            box-shadow: 0 2px 20px rgba(128, 0, 32, 0.15);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.3rem;
            color: white !important;
        }
        
        .navbar-brand i { margin-right: 10px; }
        
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .nav-link:hover {
            color: white !important;
            transform: translateY(-2px);
        }
        
        .nav-link.active {
            color: white !important;
            border-bottom: 2px solid white;
        }
        
        .main-content {
            padding: 60px 30px;
            min-height: calc(100vh - 70px);
        }
        
        .hero-section {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .hero-section h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-800);
            margin-bottom: 15px;
        }
        
        .hero-section h1 i {
            color: var(--primary);
            margin-right: 10px;
        }
        
        .hero-section p {
            color: var(--gray-600);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Guide Cards */
        .guide-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        .guide-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.04);
        }
        
        .guide-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(128, 0, 32, 0.1);
        }
        
        .guide-icon {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 30px;
            text-align: center;
            color: white;
        }
        
        .guide-icon i {
            font-size: 2.5rem;
        }
        
        .guide-content {
            padding: 25px;
        }
        
        .guide-content h3 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--gray-800);
        }
        
        .guide-content p {
            color: var(--gray-600);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        
        .guide-steps {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }
        
        .guide-steps li {
            padding: 8px 0;
            border-bottom: 1px solid var(--gray-200);
            font-size: 0.85rem;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .guide-steps li i {
            color: var(--primary);
            font-size: 0.8rem;
        }
        
        .guide-steps li:last-child {
            border-bottom: none;
        }
        
        .btn-read {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: var(--primary-soft);
            color: var(--primary);
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-read:hover {
            background: var(--primary);
            color: white;
        }
        
        /* FAQ Section */
        .faq-section {
            background: white;
            border-radius: 24px;
            padding: 40px;
            margin-top: 20px;
            border: 1px solid rgba(0,0,0,0.04);
        }
        
        .faq-section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .faq-item {
            border-bottom: 1px solid var(--gray-200);
            padding: 15px 0;
        }
        
        .faq-question {
            font-weight: 600;
            color: var(--gray-800);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .faq-question:hover {
            color: var(--primary);
        }
        
        .faq-answer {
            display: none;
            padding-top: 10px;
            color: var(--gray-600);
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .faq-answer.show {
            display: block;
        }
        
        .footer {
            background: var(--gray-800);
            color: white;
            padding: 30px 0;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .main-content { padding: 40px 20px; }
            .hero-section h1 { font-size: 1.8rem; }
            .guide-grid { grid-template-columns: 1fr; }
            .faq-section { padding: 25px; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-graduation-cap"></i> Clearance System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link active" href="guides.php">Guides</a></li>
                    <li class="nav-item"><a class="nav-link" href="auth/login.php">Login</a></li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="main-content">
        <div class="container">
            <div class="hero-section">
                <h1><i class="fas fa-book-open"></i> User Guides</h1>
                <p>Everything you need to know about using the Graduation Clearance System</p>
            </div>
            
            <!-- Student Guides -->
            <div class="guide-grid">
                <div class="guide-card">
                    <div class="guide-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="guide-content">
                        <h3>How to Register an Account</h3>
                        <p>Create your student account to start the clearance process.</p>
                        <ul class="guide-steps">
                            <li><i class="fas fa-check-circle"></i> Click on "Register" on the login page</li>
                            <li><i class="fas fa-check-circle"></i> Fill in your personal details</li>
                            <li><i class="fas fa-check-circle"></i> Enter your Student ID and email</li>
                            <li><i class="fas fa-check-circle"></i> Create a strong password</li>
                            <li><i class="fas fa-check-circle"></i> Submit and verify your email</li>
                        </ul>
                        <a href="auth/register.php" class="btn-read">Register Now <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="guide-card">
                    <div class="guide-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="guide-content">
                        <h3>Tracking Clearance Status</h3>
                        <p>Monitor your progress across all departments.</p>
                        <ul class="guide-steps">
                            <li><i class="fas fa-check-circle"></i> Login to your student dashboard</li>
                            <li><i class="fas fa-check-circle"></i> View overall progress bar</li>
                            <li><i class="fas fa-check-circle"></i> Check each department's status</li>
                            <li><i class="fas fa-check-circle"></i> Click "View Details" for more info</li>
                            <li><i class="fas fa-check-circle"></i> Track pending/completed items</li>
                        </ul>
                        <a href="student/dashboard.php" class="btn-read">Go to Dashboard <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="guide-card">
                    <div class="guide-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="guide-content">
                        <h3>Uploading Documents</h3>
                        <p>Submit required documents for clearance approval.</p>
                        <ul class="guide-steps">
                            <li><i class="fas fa-check-circle"></i> Go to "Upload Documents" page</li>
                            <li><i class="fas fa-check-circle"></i> Select the document requirement</li>
                            <li><i class="fas fa-check-circle"></i> Click "Choose File" to select document</li>
                            <li><i class="fas fa-check-circle"></i> Add comments if needed</li>
                            <li><i class="fas fa-check-circle"></i> Submit and wait for review</li>
                        </ul>
                        <a href="student/upload-document.php" class="btn-read">Upload Documents <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="guide-card">
                    <div class="guide-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div class="guide-content">
                        <h3>Downloading Clearance Certificate</h3>
                        <p>Get your official clearance certificate after completion.</p>
                        <ul class="guide-steps">
                            <li><i class="fas fa-check-circle"></i> Complete all department clearances</li>
                            <li><i class="fas fa-check-circle"></i> Certificate becomes available automatically</li>
                            <li><i class="fas fa-check-circle"></i> Click "Download Certificate" button</li>
                            <li><i class="fas fa-check-circle"></i> Save PDF to your device</li>
                            <li><i class="fas fa-check-circle"></i> Print for submission if needed</li>
                        </ul>
                        <a href="student/download-certificate.php" class="btn-read">Download Certificate <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="guide-card">
                    <div class="guide-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="guide-content">
                        <h3>Changing Your Password</h3>
                        <p>Keep your account secure by updating your password.</p>
                        <ul class="guide-steps">
                            <li><i class="fas fa-check-circle"></i> Go to "Change Password" page</li>
                            <li><i class="fas fa-check-circle"></i> Enter current password</li>
                            <li><i class="fas fa-check-circle"></i> Enter new password (min 8 characters)</li>
                            <li><i class="fas fa-check-circle"></i> Confirm new password</li>
                            <li><i class="fas fa-check-circle"></i> Click "Change Password" to save</li>
                        </ul>
                        <a href="student/change-password.php" class="btn-read">Change Password <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="guide-card">
                    <div class="guide-icon">
                        <i class="fas fa-life-ring"></i>
                    </div>
                    <div class="guide-content">
                        <h3>Need Help?</h3>
                        <p>Get support when you need assistance.</p>
                        <ul class="guide-steps">
                            <li><i class="fas fa-check-circle"></i> Visit the Help & Support page</li>
                            <li><i class="fas fa-check-circle"></i> Check FAQ for common questions</li>
                            <li><i class="fas fa-check-circle"></i> Submit a support ticket</li>
                            <li><i class="fas fa-check-circle"></i> Contact department directly</li>
                            <li><i class="fas fa-check-circle"></i> Email us for urgent matters</li>
                        </ul>
                        <a href="student/help-support.php" class="btn-read">Get Help <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            
            <!-- FAQ Section -->
            <div class="faq-section">
                <h2><i class="fas fa-question-circle me-2"></i> Frequently Asked Questions</h2>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        How do I know if my clearance is complete?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        You can check your clearance status on your student dashboard. When all departments show "Completed" and the progress bar reaches 100%, your clearance is complete. You will then be able to download your clearance certificate.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        What documents do I need to upload?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Required documents vary by department. Common documents include: fee payment receipts, library clearance forms, lab equipment returns, thesis copies, internship reports, and exit survey completion. Check each department's requirements for specific document needs.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        How long does clearance approval take?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Each department typically reviews clearances within 2-3 business days. You will receive email notifications when your status changes. For urgent matters, contact the department directly.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        What if my clearance gets rejected?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        If rejected, check the remarks provided by the department. Address the issues mentioned and resubmit if needed. Contact the department for clarification if required.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        Can I edit my profile information?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Yes, you can update your profile information by going to the "Profile" page. You can change your name, phone number, and email address. Your Student ID cannot be changed.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        I forgot my password. What should I do?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Click "Forgot Password" on the login page. Enter your email address to receive a password reset link. Follow the instructions in the email to create a new password.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        Who do I contact for technical issues?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        For technical issues, please contact our support team at support@clearance.edu or call +256 987 654 321. You can also submit a support ticket through the Help & Support page.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Graduation Clearance System. All rights reserved.</p>
        </div>
    </footer>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // FAQ Toggle
        function toggleFAQ(element) {
            const answer = element.nextElementSibling;
            const icon = element.querySelector('i');
            answer.classList.toggle('show');
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-up');
        }
    </script>
</body>
</html>