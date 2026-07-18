<?php
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if confirmation was received
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // Log the logout activity if user was logged in
    if (isLoggedIn()) {
        logActivity($pdo, $_SESSION['user_id'], 'Logout', 'User logged out');
    }

    // Destroy all session data
    $_SESSION = array();

    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Destroy remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }

    // Finally, destroy the session
    session_destroy();

    // Redirect to login page with message
    session_start();
    $_SESSION['logout_message'] = "You have been successfully logged out.";
    header("Location: login.php");
    exit();
}

// If no confirmation, show confirmation dialog and redirect back with referral
$redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php';

// Get the current page's content by including it
// This allows the modal to overlay the actual page
$show_modal = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout Confirmation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           RESET - NO BACKGROUND COLOR, LET PAGE SHOW THROUGH
           ============================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            width: 100%;
            min-height: 100vh;
            background: transparent !important;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            /* NO BACKGROUND COLOR - LET PAGE CONTENT SHOW THROUGH */
        }
        
        /* ============================================================
           GLASS OVERLAY - SUBTLE DIM WITH BLUR
           ============================================================ */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            /* ⚠️ TRANSPARENT - ONLY BLUR AND DIM, NO SOLID COLOR */
            background: rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 9998;
            animation: overlayFadeIn 0.4s ease;
        }
        
        @keyframes overlayFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* ============================================================
           GLASSMORPHISM MODAL - FLOATING OVER THE PAGE
           ============================================================ */
        .modal-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            max-width: 440px;
            padding: 20px;
            z-index: 9999;
            animation: modalSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.92) translateY(20px);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1) translateY(0);
            }
        }
        
        /* ============================================================
           GLASSMORPHISM CARD - TRUE FROSTED GLASS
           ============================================================ */
        .glass-card {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: 24px;
            padding: 40px 36px 32px;
            text-align: center;
            box-shadow: 
                0 30px 80px rgba(0, 0, 0, 0.2),
                0 10px 30px rgba(0, 0, 0, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        /* Glass shimmer effect */
        .glass-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.2), transparent 60%);
            pointer-events: none;
            z-index: 0;
        }
        
        .glass-card > * {
            position: relative;
            z-index: 1;
        }
        
        /* ============================================================
           ICON - SAME AS SYSTEM
           ============================================================ */
        .icon-circle {
            width: 76px;
            height: 76px;
            background: rgba(128, 0, 32, 0.08);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            border: 1px solid rgba(128, 0, 32, 0.06);
            transition: all 0.3s ease;
        }
        
        .icon-circle i {
            font-size: 2.2rem;
            color: #800020;
        }
        
        /* ============================================================
           TYPOGRAPHY
           ============================================================ */
        .glass-card h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 10px;
            letter-spacing: -0.3px;
        }
        
        .glass-card .message {
            color: #5a6370;
            font-size: 0.95rem;
            margin-bottom: 28px;
            line-height: 1.7;
            max-width: 320px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* ============================================================
           BUTTONS - PROFESSIONAL MODERN
           ============================================================ */
        .button-group {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        
        .btn-cancel {
            padding: 12px 24px;
            background: rgba(240, 242, 245, 0.8);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            color: #4a5360;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 14px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex: 1;
        }
        
        .btn-cancel:hover {
            background: rgba(240, 242, 245, 1);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
        }
        
        .btn-logout {
            padding: 12px 24px;
            background: linear-gradient(135deg, #800020, #5a0016);
            color: white;
            border: none;
            border-radius: 14px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex: 1;
            box-shadow: 0 4px 16px rgba(128, 0, 32, 0.3);
        }
        
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(128, 0, 32, 0.4);
            background: linear-gradient(135deg, #6e001b, #4a0012);
        }
        
        .btn-logout:active {
            transform: scale(0.97);
        }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 520px) {
            .glass-card {
                padding: 30px 24px 24px;
                border-radius: 20px;
            }
            
            .button-group {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-cancel, .btn-logout {
                flex: none;
                width: 100%;
                padding: 14px 20px;
                justify-content: center;
            }
            
            .icon-circle {
                width: 64px;
                height: 64px;
            }
            
            .icon-circle i {
                font-size: 1.8rem;
            }
            
            .glass-card h2 {
                font-size: 1.25rem;
            }
            
            .glass-card .message {
                font-size: 0.85rem;
                padding: 0 4px;
            }
        }
        
        @media (max-width: 380px) {
            .glass-card {
                padding: 24px 18px 20px;
                border-radius: 16px;
            }
            
            .icon-circle {
                width: 54px;
                height: 54px;
            }
            
            .icon-circle i {
                font-size: 1.5rem;
            }
            
            .glass-card h2 {
                font-size: 1.1rem;
            }
            
            .glass-card .message {
                font-size: 0.8rem;
            }
            
            .btn-cancel, .btn-logout {
                font-size: 0.8rem;
                padding: 12px 16px;
            }
        }
    </style>
</head>
<body>
    <!-- ============================================================
         GLASS OVERLAY - SUBTLE DIM WITH BLUR ONLY
         (NO SOLID BACKGROUND - PAGE CONTENT SHOWS THROUGH)
         ============================================================ -->
    <div class="modal-overlay"></div>
    
    <!-- ============================================================
         GLASSMORPHISM MODAL - FLOATING ON TOP
         ============================================================ -->
    <div class="modal-container">
        <div class="glass-card">
            <!-- Icon -->
            <div class="icon-circle">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            
            <!-- Title -->
            <h2>Logout Confirmation</h2>
            
            <!-- Message -->
            <p class="message">
                Are you sure you want to log out of your account? You will need to log in again to access your dashboard.
            </p>
            
            <!-- Buttons -->
            <div class="button-group">
                <a href="<?php echo htmlspecialchars($redirect_url); ?>" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <a href="logout.php?confirm=yes" class="btn-logout">
                    <i class="fas fa-check"></i> Yes, Logout
                </a>
            </div>
        </div>
    </div>
</body>
</html>