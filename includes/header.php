<?php
// includes/header.php
require_once __DIR__ . '/../config/constants.php';

$page_title = $page_title ?? 'Home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo SITE_NAME; ?> | <?php echo $page_title; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --maroon: #800020;
            --maroon-dark: #5a0016;
            --maroon-light: #9e0028;
            --maroon-soft: rgba(128, 0, 32, 0.08);
            --gray-light: #f8f9fc;
            --gray: #6c7683;
            --gray-dark: #2d3440;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            padding-top: 76px;
        }
        
        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, #800020 0%, #5a0016 100%);
            padding: 1rem 0;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.3rem;
            color: white !important;
        }
        
        .navbar-brand i {
            margin-right: 10px;
        }
        
        .navbar-toggler {
            background: rgba(255, 255, 255, 0.15);
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
        }
        
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255,255,255,0.9)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white !important;
        }
        
        .dropdown-menu {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .dropdown-item {
            padding: 8px 20px;
            font-size: 0.85rem;
        }
        
        .dropdown-item:hover {
            background: var(--maroon-soft);
            color: var(--maroon);
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #fff 0%, #fef5f5 100%);
            padding: 80px 0;
        }
        
        .hero-section h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-dark);
            margin-bottom: 20px;
        }
        
        .hero-section .lead {
            font-size: 1.1rem;
            color: var(--gray);
            margin-bottom: 30px;
        }
        
        .hero-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 40px;
        }
        
        .hero-buttons .btn {
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 500;
        }
        
        .btn-light {
            background: var(--maroon);
            color: white;
            border: none;
        }
        
        .btn-light:hover {
            background: var(--maroon-dark);
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-outline-light {
            background: transparent;
            border: 1px solid var(--maroon);
            color: var(--maroon);
        }
        
        .btn-outline-light:hover {
            background: var(--maroon);
            color: white;
        }
        
        /* Hero Stats */
        .hero-stats {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border: 1px solid #e8ecef;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--maroon);
            display: block;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        /* Login Card */
        .login-card {
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 20px 35px rgba(0,0,0,0.05);
            border: 1px solid #e8ecef;
        }
        
        .login-card h4 {
            color: var(--maroon);
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 25px;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 1px solid #e8ecef;
            border-radius: 12px;
            padding: 12px 15px;
            font-size: 0.9rem;
        }
        
        .form-control:focus {
            border-color: var(--maroon);
            box-shadow: 0 0 0 3px var(--maroon-soft);
            outline: none;
        }
        
        .btn-maroon {
            background: var(--maroon);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-maroon:hover {
            background: var(--maroon-dark);
            transform: translateY(-2px);
        }
        
        /* Feature Cards */
        .card {
            border-radius: 20px;
            border: 1px solid #e8ecef;
            transition: all 0.3s;
            height: 100%;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(128,0,32,0.1);
        }
        
        .card-icon {
            width: 70px;
            height: 70px;
            background: var(--maroon-soft);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .card-icon i {
            font-size: 2rem;
            color: var(--maroon);
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--gray-dark);
        }
        
        .card-text {
            color: var(--gray);
            line-height: 1.5;
        }
        
        /* Department Cards */
        .department-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid #e8ecef;
            height: 100%;
        }
        
        .department-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(128,0,32,0.08);
        }
        
        .department-icon {
            width: 55px;
            height: 55px;
            background: var(--maroon-soft);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }
        
        .department-icon i {
            font-size: 1.5rem;
            color: var(--maroon);
        }
        
        .department-name {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--gray-dark);
        }
        
        /* Step Cards */
        .step-card {
            background: white;
            border-radius: 20px;
            padding: 30px 25px;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid #e8ecef;
            position: relative;
            height: 100%;
        }
        
        .step-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(128,0,32,0.08);
        }
        
        .step-number {
            position: absolute;
            top: -15px;
            left: 20px;
            width: 40px;
            height: 40px;
            background: var(--maroon);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .step-card h5 {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 20px 0 10px;
            color: var(--gray-dark);
        }
        
        .step-card p {
            font-size: 0.85rem;
            color: var(--gray);
            margin: 0;
        }
        
        /* Testimonial Cards */
        .testimonial-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            transition: all 0.3s;
            border: 1px solid #e8ecef;
            height: 100%;
        }
        
        .testimonial-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(128,0,32,0.08);
        }
        
        .testimonial-text {
            font-size: 0.9rem;
            color: #4a5360;
            line-height: 1.6;
            margin: 15px 0;
            font-style: italic;
        }
        
        .testimonial-author {
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--gray-dark);
        }
        
        /* CTA Section */
        .login-section {
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            padding: 60px 0;
            color: white;
        }
        
        .login-section h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .login-section .btn-light {
            background: white;
            color: var(--maroon);
            padding: 12px 35px;
            border-radius: 50px;
            font-weight: 600;
            border: none;
        }
        
        .login-section .btn-light:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        
        .how-it-works {
            padding: 60px 0;
            background: white;
        }
        
        @media (max-width: 768px) {
            body {
                padding-top: 68px;
            }
            
            .hero-section {
                padding: 60px 0;
            }
            
            .hero-section h1 {
                font-size: 1.8rem;
                text-align: center;
            }
            
            .hero-section .lead {
                text-align: center;
            }
            
            .hero-buttons {
                justify-content: center;
            }
            
            .hero-stats {
                margin-top: 30px;
            }
            
            .login-card {
                margin-top: 40px;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .navbar-collapse {
                background: white;
                border-radius: 20px;
                margin-top: 15px;
                padding: 20px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            }
            
            .nav-link {
                color: var(--gray-dark) !important;
                padding: 12px 16px !important;
            }
            
            .nav-link:hover {
                background: var(--maroon-soft);
                color: var(--maroon) !important;
            }
            
            .login-section h2 {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .hero-section h1 {
                font-size: 1.5rem;
            }
            
            .hero-buttons .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-graduation-cap"></i> <?php echo SITE_NAME; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#departments">Departments</a></li>
                <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                <li class="nav-item"><a class="nav-link" href="faq.php">FAQ</a></li>
                <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Account</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="auth/login.php">Login</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="auth/register.php">Student Registration</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>