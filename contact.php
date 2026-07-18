<?php
session_start();
require_once 'config/db.php';
require_once 'config/functions.php';

$page_title = 'Contact Us';

require_once 'includes/header.php';
?>

<style>
:root {
    --primary: #800020;
    --primary-dark: #5a0016;
    --primary-light: #9e0028;
    --primary-soft: rgba(128,0,32,0.08);
    --gray-50: #fafbfc;
    --gray-100: #f8f9fc;
    --gray-200: #e4e7ef;
    --gray-300: #dee2e8;
    --gray-500: #9aa4b2;
    --gray-600: #6c7683;
    --gray-700: #4a5360;
    --gray-800: #2d3047;
    --gray-900: #1a1e24;
}

/* Contact Page */
.contact-page {
    padding: 50px 0 70px;
    min-height: calc(100vh - 70px);
    background: var(--gray-50);
}

/* Header */
.page-header {
    text-align: center;
    margin-bottom: 45px;
}

.page-header h1 {
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--gray-900);
    margin-bottom: 8px;
}

.page-header h1 i {
    color: var(--primary);
    margin-right: 10px;
}

.page-header p {
    color: var(--gray-600);
    font-size: 1rem;
    max-width: 500px;
    margin: 0 auto;
}

/* Contact Cards - Simple Grid */
.contact-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 25px;
    max-width: 960px;
    margin: 0 auto 40px;
}

.contact-card {
    background: white;
    border-radius: 16px;
    padding: 30px 25px;
    text-align: center;
    border: 1px solid var(--gray-200);
    transition: all 0.3s;
}

.contact-card:hover {
    border-color: var(--primary);
    box-shadow: 0 8px 25px rgba(128,0,32,0.08);
    transform: translateY(-3px);
}

.contact-card .icon {
    width: 56px;
    height: 56px;
    background: var(--primary-soft);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 14px;
    color: var(--primary);
    font-size: 1.3rem;
}

.contact-card h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 6px;
}

.contact-card .info {
    color: var(--gray-600);
    font-size: 0.9rem;
    line-height: 1.6;
}

.contact-card .info a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.contact-card .info a:hover {
    text-decoration: underline;
}

/* Divider */
.divider {
    display: flex;
    align-items: center;
    gap: 20px;
    max-width: 400px;
    margin: 0 auto 35px;
}

.divider hr {
    flex: 1;
    border: none;
    border-top: 1px solid var(--gray-200);
}

.divider span {
    color: var(--gray-500);
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
    white-space: nowrap;
}

/* Location Row - Simple */
.location-row {
    display: flex;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap;
    background: white;
    border-radius: 16px;
    padding: 20px 30px;
    border: 1px solid var(--gray-200);
    max-width: 700px;
    margin: 0 auto 30px;
}

.location-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--gray-700);
    font-size: 0.9rem;
}

.location-item i {
    color: var(--primary);
    font-size: 1rem;
    width: 20px;
    text-align: center;
}

.location-item a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.location-item a:hover {
    text-decoration: underline;
}

/* Social Links - Simple */
.social-section {
    text-align: center;
    padding-top: 5px;
}

.social-section .label {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-bottom: 12px;
    font-weight: 500;
    letter-spacing: 0.5px;
}

.social-links {
    display: flex;
    justify-content: center;
    gap: 12px;
}

.social-link {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-600);
    background: white;
    border: 1px solid var(--gray-200);
    transition: all 0.3s;
    text-decoration: none;
    font-size: 1rem;
}

.social-link:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 768px) {
    .contact-page { padding: 30px 0 50px; }
    
    .page-header h1 { font-size: 1.7rem; }
    .page-header p { font-size: 0.9rem; }
    
    .contact-grid {
        grid-template-columns: 1fr;
        max-width: 450px;
        gap: 15px;
    }
    
    .contact-card {
        padding: 22px 20px;
    }
    
    .location-row {
        flex-direction: column;
        align-items: center;
        padding: 15px 20px;
        gap: 12px;
    }
    
    .social-links {
        gap: 10px;
    }
    .social-link {
        width: 38px;
        height: 38px;
        font-size: 0.9rem;
    }
}

@media (max-width: 576px) {
    .page-header h1 { font-size: 1.4rem; }
    .contact-card .icon { width: 48px; height: 48px; font-size: 1.1rem; }
    .contact-card { padding: 18px 15px; }
    .location-item { font-size: 0.8rem; }
}
</style>

<div class="contact-page">
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-phone-alt"></i> Contact Us</h1>
            <p>Have questions? Reach out to us and we'll get back to you promptly.</p>
        </div>

        <!-- Contact Cards -->
        <div class="contact-grid">
            <!-- Phone -->
            <div class="contact-card">
                <div class="icon"><i class="fas fa-phone"></i></div>
                <h4>Call Us</h4>
                <div class="info">
                    <a href="tel:+256754135798">+256 754 135 798</a><br>
                    <a href="tel:+256751877049">+256 751 877 049</a>
                </div>
            </div>

            <!-- Email -->
            <div class="contact-card">
                <div class="icon"><i class="fas fa-envelope"></i></div>
                <h4>Email Us</h4>
                <div class="info">
                    <a href="mailto:joshuakaramuzi@gmail.com">joshuakaramuzi@gmail.com</a>
                </div>
            </div>

            <!-- Location -->
            <div class="contact-card">
                <div class="icon"><i class="fas fa-map-marker-alt"></i></div>
                <h4>Visit Us</h4>
                <div class="info">
                    Buloba, Along Mitiyana Road<br>
                    Kampala, Uganda
                </div>
            </div>
        </div>

        <!-- Divider -->
        <div class="divider">
            <hr>
            <span>Get in Touch</span>
            <hr>
        </div>

        <!-- Location Row -->
        <div class="location-row">
            <div class="location-item">
                <i class="fas fa-location-dot"></i>
                <span>Buloba, Mitiyana Road, Kampala</span>
            </div>
            <div class="location-item">
                <i class="fas fa-clock"></i>
                <span>Mon–Fri, 9AM – 5PM</span>
            </div>
            <div class="location-item">
                <i class="fas fa-envelope"></i>
                <a href="mailto:joshuakaramuzi@gmail.com">Email Us</a>
            </div>
        </div>

            </div>
</div>

<?php require_once 'includes/footer.php'; ?>