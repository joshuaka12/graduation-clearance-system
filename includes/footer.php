<?php
// includes/footer.php
?>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4 mb-lg-0">
                <h5><i class="fas fa-graduation-cap"></i> <?php echo SITE_NAME; ?></h5>
                <p class="mt-3">Streamlining the graduation clearance process for students and administrators. Making graduation seamless and efficient.</p>
                <div class="social-icons mt-3">
                    <a href="https://www.youtube.com/@africarenewaluniversity" target="_blank" aria-label="YouTube">
                    <i class="fab fa-youtube"></i></a>
                    <a href="https://x.com/AfRUniversity" target="_blank" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="https://www.instagram.com/africarenewal_university/?hl=en" target="_blank" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="https://www.linkedin.com/company/africa-renewal-christian-college-arcc-/posts/?feedView=all" target="_blank" aria-label="LinkedIn"><i 
                    class="fab fa-linkedin-in"></i></a>
                    <a href="https://moodle.afru.ac.ug/login/index.php" target="_blank" aria-label="Learning Platform">
                    <i class="fas fa-university"></i>
                  </a>
                    <a href="https://afru.ac.ug/" target="_blank" aria-label="Africa Renewal University">
                <i class="fas fa-globe"></i>
                 </a>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-6 mb-4 mb-lg-0">
                <h5>Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="<?php echo BASE_URL; ?>index.php">Home</a></li>
                    <li><a href="<?php echo BASE_URL; ?>index.php#departments">Departments</a></li>
                    <li><a href="<?php echo BASE_URL; ?>index.php#how-it-works">How It Works</a></li>
                    <li><a href="<?php echo BASE_URL; ?>faq.php">FAQ</a></li>
                    
                </ul>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <h5>Support</h5>
                <ul class="footer-links">
                    <li><a href="<?php echo BASE_URL; ?>contact.php">Contact Us</a></li>
                </ul>
            </div>
            
            <div class="col-lg-3">
                <h5>Contact Info</h5>
                <ul class="footer-contact">
                    <li>
                        <i class="fas fa-envelope"></i>
                        <span>joshuakaramuzi@gmail.com</span>
                    </li>
                    <li>
                        <i class="fas fa-phone-alt"></i>
                        <span>+256 754 135 798</span>
                    </li>
                    <li>
                        <i class="fas fa-clock"></i>
                        <span>Mon - Fri, 9:00 AM - 5:00 PM</span>
                    </li>
                </ul>
            </div>
        </div>
        
        <hr class="mt-4 mb-3">
        
        <div class="row">
            <div class="col-md-6 text-center text-md-start">
                <p class="mb-0">&copy; <?php echo CURRENT_YEAR; ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="mb-0">Designed with <i class="fas fa-heart" style="color: <?php echo PRIMARY_COLOR; ?>;"></i> for Graduating Students</p>
            </div>
        </div>
    </div>
</footer>

<style>
    /* Footer Styles */
    .footer {
        background: #1a1a2e;
        color: #9aa4b2;
        padding: 60px 0 30px;
        margin-top: 60px;
        position: relative;
    }
    
    .footer h5 {
        color: white;
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 20px;
        letter-spacing: 0.5px;
        position: relative;
        display: inline-block;
    }
    
    .footer h5:after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 0;
        width: 35px;
        height: 2px;
        background: <?php echo PRIMARY_COLOR; ?>;
    }
    
    .footer h5 i {
        margin-right: 8px;
        color: <?php echo PRIMARY_COLOR; ?>;
    }
    
    .footer p {
        font-size: 0.85rem;
        line-height: 1.6;
    }
    
    .footer-links {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .footer-links li {
        margin-bottom: 10px;
    }
    
    .footer-links a {
        color: #9aa4b2;
        text-decoration: none;
        font-size: 0.85rem;
        transition: all 0.3s;
        display: inline-block;
    }
    
    .footer-links a:hover {
        color: <?php echo PRIMARY_COLOR; ?>;
        transform: translateX(5px);
    }
    
    /* Contact Info Styles */
    .footer-contact {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .footer-contact li {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 15px;
        font-size: 0.85rem;
        color: #9aa4b2;
    }
    
    .footer-contact li i {
        width: 32px;
        height: 32px;
        background: rgba(255,255,255,0.08);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        color: <?php echo PRIMARY_COLOR; ?>;
        transition: all 0.3s;
    }
    
    .footer-contact li:hover i {
        background: <?php echo PRIMARY_COLOR; ?>;
        color: white;
        transform: scale(1.05);
    }
    
    .footer-contact li span {
        flex: 1;
    }
    
    /* Social Icons */
    .social-icons {
        display: flex;
        gap: 12px;
    }
    
    .social-icons a {
        width: 36px;
        height: 36px;
        background: rgba(255,255,255,0.08);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        transition: all 0.3s;
        text-decoration: none;
        font-size: 1rem;
    }
    
    .social-icons a:hover {
        background: <?php echo PRIMARY_COLOR; ?>;
        transform: translateY(-3px);
    }
    
    hr {
        border-color: rgba(255,255,255,0.08);
        margin: 30px 0 20px;
    }
    
    /* Mobile Responsive */
    @media (max-width: 991px) {
        .footer {
            padding: 50px 0 25px;
        }
        
        .footer h5 {
            margin-top: 10px;
            margin-bottom: 20px;
        }
        
        .footer h5:after {
            left: 50%;
            transform: translateX(-50%);
        }
        
        .social-icons {
            justify-content: center;
            margin-top: 20px;
        }
        
        .footer-contact li {
            justify-content: center;
        }
    }
    
    @media (max-width: 768px) {
        .footer {
            padding: 40px 0 20px;
            text-align: center;
        }
        
        .footer .col-md-6 {
            text-align: center !important;
            margin-bottom: 15px;
        }
        
        .footer-links {
            margin-bottom: 20px;
        }
        
        .footer-links a:hover {
            transform: translateX(0);
        }
        
        .social-icons {
            justify-content: center;
        }
        
        .footer-contact li {
            justify-content: center;
        }
        
        hr {
            margin: 25px 0;
        }
    }
    
    @media (max-width: 576px) {
        .footer {
            padding: 30px 0 15px;
        }
        
        .footer h5 {
            font-size: 0.95rem;
        }
        
        .footer p, .footer-links a, .footer-contact li {
            font-size: 0.8rem;
        }
        
        .social-icons a {
            width: 32px;
            height: 32px;
            font-size: 0.9rem;
        }
        
        .footer-contact li i {
            width: 28px;
            height: 28px;
            font-size: 0.75rem;
        }
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script>
    // Initialize AOS
    AOS.init({
        duration: 1000,
        once: true
    });
    
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.getElementById('mainNav');
        if (navbar) {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        }
    });
    
    // Smooth scrolling for anchor links on the same page
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            
            // Skip if it's just "#" or empty
            if (href === '#' || href === '') return;
            
            // Check if it's a hash link on the same page
            if (href.startsWith('#') && !href.includes('.php')) {
                e.preventDefault();
                const targetId = href.substring(1);
                const target = document.getElementById(targetId);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });
    
    // Handle hash links from other pages (e.g., index.php#departments)
    if (window.location.hash && window.location.pathname.includes('index.php')) {
        const targetId = window.location.hash.substring(1);
        setTimeout(() => {
            const target = document.getElementById(targetId);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        }, 100);
    }
</script>

</body>
</html>