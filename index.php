<?php
$page_title = 'Home - Your Pathway to Graduation';
require_once 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
  
    
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7" data-aos="fade-right">
                <h1>Welcome to the Graduation Clearance System</h1>
                <p class="lead">Streamline your graduation process with our comprehensive clearance system. Track your progress across all departments in real-time.</p>
                <div class="hero-buttons">
                    <a href="auth/login.php" class="btn btn-light btn-lg me-3">
                        <i class="fas fa-sign-in-alt"></i> Student Login
                    </a>
                    <a href="auth/register.php" class="btn btn-outline-light btn-lg me-3">
                        <i class="fas fa-user-plus"></i> Register Now
                    </a>
                    <a href="guides.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-book-open"></i> Guidelines
                    </a>
                </div>
                
                <div class="hero-stats">
                    <div class="row">
                        <div class="col-4">
                            <div class="stat-item">
                                <span class="stat-number">09</span>
                                <span class="stat-label">Departments</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-item">
                                <span class="stat-number">24/7</span>
                                <span class="stat-label">Support</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-item">
                                <span class="stat-number">100%</span>
                                <span class="stat-label">Digital</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-5" data-aos="fade-left">
                <div class="login-card">
                    <h4 class="text-center mb-4" style="color: var(--maroon);">
                        <i class="fas fa-lock"></i> Quick Login
                    </h4>
                    <form action="auth/login.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Student ID / Email</label>
                            <input type="text" class="form-control" name="username" required placeholder="Enter your student ID">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required placeholder="Enter your password">
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <button type="submit" class="btn btn-maroon">
                            <i class="fas fa-arrow-right"></i> Login to Dashboard
                        </button>
                        <div class="text-center mt-3">
                            <a href="auth/forgot-password.php" class="text-muted">Forgot Password?</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 style="color: var(--maroon);">Why Choose Our Clearance System?</h2>
            <p class="lead">Modern, efficient, and student-friendly clearance process</p>
        </div>
        
        <div class="row">
            <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h5 class="card-title">Fast Processing</h5>
                        <p class="card-text">Reduce clearance time from weeks to days with our automated system.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h5 class="card-title">Real-time Tracking</h5>
                        <p class="card-text">Monitor your clearance progress across all departments instantly.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="300">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h5 class="card-title">Secure & Reliable</h5>
                        <p class="card-text">Your data is protected with enterprise-grade security measures.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Departments Section -->
<section id="departments" class="py-5" style="background: var(--gray-light);">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 style="color: var(--maroon);">Clearance Departments</h2>
            <p class="lead">9 departments to complete before graduation</p>
        </div>
        
        <div class="row">
            <!-- Department 1: Computer Lab -->
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="department-card">
                    <div class="department-icon">
                        <i class="fas fa-laptop-code"></i>
                    </div>
                    <h6 class="department-name">Computer Lab</h6>
                    <small class="text-muted">Equipment return & lab fee clearance</small>
                    <div class="mt-2">
                        <span class="badge bg-warning text-dark">Pending</span>
                    </div>
                </div>
            </div>
            
            <!-- Department 2: Hostel -->
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="150">
                <div class="department-card">
                    <div class="department-icon">
                        <i class="fas fa-bed"></i>
                    </div>
                    <h6 class="department-name">Hostel</h6>
                    <small class="text-muted">Room vacating & property damages</small>
                    <div class="mt-2">
                        <span class="badge bg-warning text-dark">Pending</span>
                    </div>
                </div>
            </div>
            
            <!-- Department 3: Library -->
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                <div class="department-card">
                    <div class="department-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h6 class="department-name">Library</h6>
                    <small class="text-muted">Book returns & outstanding fines</small>
                    <div class="mt-2">
                        <span class="badge bg-warning text-dark">Pending</span>
                    </div>
                </div>
            </div>
            
            <!-- Department 4: Dean of Students -->
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="250">
                <div class="department-card">
                    <div class="department-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h6 class="department-name">Dean of Students</h6>
                    <small class="text-muted">Conduct & discipline clearance</small>
                    <div class="mt-2">
                        <span class="badge bg-warning text-dark">Pending</span>
                    </div>
                </div>
            </div>
            
            <!-- Department 5: Facilities Manager (Campus Internship Store) -->
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                <div class="department-card">
                    <div class="department-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h6 class="department-name">Facilities Manager</h6>
                    <small class="text-muted">Campus internship store clearance</small>
                    <div class="mt-2">
                        <span class="badge bg-warning text-dark">Pending</span>
                    </div>
                </div>
            </div>
            
            <!-- Department 6: Finance -->
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="350">
                <div class="department-card">
                    <div class="department-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <h6 class="department-name">Finance</h6>
                    <small class="text-muted">All fees cleared including Graduation Fee</small>
                    <div class="mt-2">
                        <span class="badge bg-warning text-dark">Pending</span>
                    </div>
                </div>
            </div>
            
            <!-- Department 7: Director Quality Assurance -->
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                <div class="department-card">
                    <div class="department-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h6 class="department-name">Director Quality Assurance</h6>
                    <small class="text-muted">Exit survey completion</small>
                    <div class="mt-2">
                        <span class="badge bg-warning text-dark">Pending</span>
                    </div>
                </div>
            </div>
            
            <!-- Department 8: Academic Head of Department -->
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="450">
                <div class="department-card">
                    <div class="department-icon">
                        <i class="fas fa-chalkboard-user"></i>
                    </div>
                    <h6 class="department-name">Academic Head of Department</h6>
                    <small class="text-muted">Verify academic records & refer to AR</small>
                    <div class="mt-2">
                        <span class="badge bg-warning text-dark">Pending</span>
                    </div>
                </div>
            </div>
            
            <!-- Department 9: Academic Registrar -->
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="500">
                <div class="department-card">
                    <div class="department-icon">
                        <i class="fas fa-scroll"></i>
                    </div>
                    <h6 class="department-name">Academic Registrar</h6>
                    <small class="text-muted">Verify grades for final graduation clearance</small>
                    <div class="mt-2">
                        <span class="badge bg-warning text-dark">Pending</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4" data-aos="fade-up">
            <a href="auth/register.php" class="btn btn-maroon btn-lg">
                Start Your Clearance Process <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section id="how-it-works" class="how-it-works">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 style="color: var(--maroon);">How It Works</h2>
            <p class="lead">Simple 4-step process to get your graduation clearance</p>
        </div>
        
        <div class="row">
            <div class="col-md-3 mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h5>Register Account</h5>
                    <p>Create your student account with your valid student ID and email.</p>
                </div>
            </div>
            
            <div class="col-md-3 mb-4" data-aos="fade-up" data-aos-delay="200">
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h5>Submit Requirements</h5>
                    <p>Upload required documents for each department clearance.</p>
                </div>
            </div>
            
            <div class="col-md-3 mb-4" data-aos="fade-up" data-aos-delay="300">
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h5>Track Progress</h5>
                    <p>Monitor your clearance status across all 9 departments in real-time.</p>
                </div>
            </div>
            
            <div class="col-md-3 mb-4" data-aos="fade-up" data-aos-delay="400">
                <div class="step-card">
                    <div class="step-number">4</div>
                    <h5>Get Cleared</h5>
                    <p>Download your clearance certificate and prepare for graduation!</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 style="color: var(--maroon);">What Students Say</h2>
            <p class="lead">Trusted by thousands of graduating students</p>
        </div>
        
        <div class="row">
            <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="testimonial-card">
                    <i class="fas fa-quote-left" style="color: var(--maroon); font-size: 2rem; opacity: 0.3;"></i>
                    <p class="testimonial-text">"This system made my graduation clearance so much easier. I could track everything online without running between departments!"</p>
                    <div class="testimonial-author">- Sarah K., Social Work</div>
                    <div class="mt-2">
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                <div class="testimonial-card">
                    <i class="fas fa-quote-left" style="color: var(--maroon); font-size: 2rem; opacity: 0.3;"></i>
                    <p class="testimonial-text">"Real-time updates and notifications helped me stay on top of all requirements. Highly recommended!"</p>
                    <div class="testimonial-author">- Nandawula Rebecca, Business Administration</div>
                    <div class="mt-2">
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="300">
                <div class="testimonial-card">
                    <i class="fas fa-quote-left" style="color: var(--maroon); font-size: 2rem; opacity: 0.3;"></i>
                    <p class="testimonial-text">"The digital certificate download feature is amazing. Got my clearance certificate instantly after all approvals!"</p>
                    <div class="testimonial-author">- Mutome Franco., IT</div>
                    <div class="mt-2">
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="login-section">
    <div class="container text-center" data-aos="fade-up">
        <h2 class="mb-3">Ready to Complete Your Graduation Clearance?</h2>
        <p class="mb-4">Join thousands of students who have successfully cleared through our system</p>
        <a href="auth/register.php" class="btn btn-light btn-lg">
            Get Started Now <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>