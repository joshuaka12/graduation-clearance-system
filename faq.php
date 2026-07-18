<?php
$page_title = 'Frequently Asked Questions';
require_once 'includes/header.php';
?>

<style>
:root {
    --primary: #800020;
    --primary-dark: #5a0016;
    --primary-light: #9e0028;
    --primary-soft: rgba(128, 0, 32, 0.08);
    --gray-50: #fafbfc;
    --gray-100: #f8f9fc;
    --gray-200: #e8ecef;
    --gray-300: #dce1e8;
    --gray-600: #6c7683;
    --gray-700: #4a5360;
    --gray-800: #2d3440;
    --gray-900: #1a1e24;
}

/* FAQ Page Specific Styles */
.faq-page {
    padding: 60px 0;
    min-height: calc(100vh - 70px);
    background: var(--gray-50);
}

.hero-section {
    text-align: center;
    margin-bottom: 50px;
}

/* Maroon Box Container */
.hero-box {
    background: var(--primary);
    border-radius: 24px;
    padding: 40px 30px;
    max-width: 700px;
    margin: 0 auto;
    box-shadow: 0 15px 35px rgba(128, 0, 32, 0.15);
}

.hero-section h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 15px;
}

.hero-section h1 i {
    color: white;
    margin-right: 10px;
}

.hero-section p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.1rem;
    max-width: 500px;
    margin: 0 auto;
}

/* Search Box */
.search-box {
    max-width: 500px;
    margin: 0 auto 40px;
}

.search-input {
    width: 100%;
    padding: 14px 20px;
    border: 1px solid var(--gray-200);
    border-radius: 60px;
    font-size: 1rem;
    transition: all 0.3s;
    background: white;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-soft);
}

/* FAQ Categories */
.faq-categories {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-bottom: 40px;
    flex-wrap: wrap;
}

.category-btn {
    padding: 8px 20px;
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--gray-700);
    cursor: pointer;
    transition: all 0.3s;
}

.category-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.category-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* FAQ Accordion */
.faq-section {
    max-width: 800px;
    margin: 0 auto;
}

.faq-category {
    margin-bottom: 40px;
}

.category-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary);
    display: inline-block;
}

.faq-item {
    background: white;
    border-radius: 16px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    transition: all 0.3s;
}

.faq-item:hover {
    border-color: var(--primary-soft);
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.faq-question {
    padding: 18px 22px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    color: var(--gray-800);
    transition: all 0.3s;
}

.faq-question:hover {
    color: var(--primary);
}

.faq-question i {
    color: var(--primary);
    font-size: 1rem;
    transition: transform 0.3s;
}

.faq-answer {
    padding: 0 22px 18px 22px;
    display: none;
    color: var(--gray-600);
    line-height: 1.6;
    border-top: 1px solid var(--gray-200);
    background: #fefefe;
}

.faq-answer.show {
    display: block;
}

.faq-answer ul, .faq-answer ol {
    padding-left: 20px;
    margin-top: 10px;
}

.faq-answer li {
    margin-bottom: 5px;
}

/* No Results */
.no-results {
    text-align: center;
    padding: 50px;
    background: white;
    border-radius: 20px;
    border: 1px solid var(--gray-200);
}

.no-results i {
    font-size: 3rem;
    color: var(--gray-300);
    margin-bottom: 15px;
}

.no-results h4 {
    margin-bottom: 10px;
    color: var(--gray-800);
}

.no-results p {
    color: var(--gray-600);
}

/* Contact Support */
.contact-support {
    text-align: center;
    margin-top: 50px;
    padding: 35px;
    background: white;
    border-radius: 20px;
    border: 1px solid var(--gray-200);
}

.contact-support h3 {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 10px;
}

.contact-support p {
    color: var(--gray-600);
    margin-bottom: 20px;
}

.btn-contact {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 28px;
    background: var(--primary);
    color: white;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-contact:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(128,0,32,0.2);
    color: white;
}

/* Responsive */
@media (max-width: 768px) {
    .faq-page { padding: 40px 0; }
    .hero-section h1 { font-size: 1.8rem; }
    .hero-box { padding: 30px 20px; margin: 0 15px; }
    .faq-question { padding: 15px 18px; font-size: 0.9rem; }
    .faq-answer { padding: 0 18px 15px 18px; }
    .category-btn { padding: 6px 16px; font-size: 0.75rem; }
}

@media (max-width: 576px) {
    .hero-section h1 { font-size: 1.5rem; }
    .hero-box { padding: 25px 15px; }
    .category-title { font-size: 1.1rem; }
    .faq-question { font-size: 0.85rem; }
}
</style>

<!-- Fix navigation for hash links -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        
        if (href && href.startsWith('#')) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = href.substring(1);
                
                if (window.location.pathname === '/' || 
                    window.location.pathname === '/index.php' ||
                    window.location.pathname.endsWith('index.php')) {
                    
                    const targetElement = document.getElementById(targetId);
                    if (targetElement) {
                        targetElement.scrollIntoView({ behavior: 'smooth' });
                    }
                } else {
                    window.location.href = `index.php${href}`;
                }
            });
        }
    });
});
</script>

<div class="faq-page">
    <div class="container">
        <div class="hero-section">
            <div class="hero-box">
                <h1>
                    <i class="fas fa-question-circle"></i> Frequently Asked Questions
                </h1>
                <p>
                    Find answers to common questions about the graduation clearance process
                </p>
            </div>
        </div>
        
        <!-- Search Box -->
        <div class="search-box">
            <input type="text" id="searchInput" class="search-input" placeholder="🔍 Search FAQs...">
        </div>
        
        <!-- Categories -->
        <div class="faq-categories">
            <button class="category-btn active" data-category="all">All Questions</button>
            <button class="category-btn" data-category="general">General</button>
            <button class="category-btn" data-category="account">Account</button>
            <button class="category-btn" data-category="clearance">Clearance Process</button>
            <button class="category-btn" data-category="documents">Documents</button>
            <button class="category-btn" data-category="technical">Technical</button>
        </div>
        
        <!-- FAQ Content -->
        <div class="faq-section" id="faqContainer">
            <!-- General Category -->
            <div class="faq-category" data-category="general">
                <div class="category-title">General Questions</div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        What is the Graduation Clearance System?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        The Graduation Clearance System is an online platform that streamlines the graduation clearance process. It allows students to track their clearance status across multiple departments, upload required documents, and download their clearance certificate once all requirements are met.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        How many departments do I need clearance from?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        There are 12 departments that students need clearance from, including: Computer Lab, Student Guild Council, Hostel, Library, Dean of Students, Facilities, Finance, Quality Assurance, Academic HOD, Academic Registrar, Sports Department, and Alumni Association.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        How long does the clearance process take?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        The clearance process typically takes 2-3 business days per department, depending on the department's review speed. We recommend starting your clearance process at least 2-3 weeks before your graduation date to ensure timely completion.
                    </div>
                </div>
            </div>
            
            <!-- Account Category -->
            <div class="faq-category" data-category="account">
                <div class="category-title">Account Questions</div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        How do I create an account?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Click on the "Register" button on the login page. Fill in your personal details including your full name, student ID, email address, phone number, and create a password. Once registered, you can log in to start your clearance process.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        I forgot my password. What should I do?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Click on the "Forgot Password" link on the login page. Enter your registered email address and you will receive a password reset link. Follow the instructions in the email to create a new password.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        Can I change my personal information?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Yes, you can update your profile information by going to the "Profile" page after logging in. You can change your name, phone number, and email address. Your Student ID cannot be changed as it is your unique identifier.
                    </div>
                </div>
            </div>
            
            <!-- Clearance Process Category -->
            <div class="faq-category" data-category="clearance">
                <div class="category-title">Clearance Process</div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        How do I check my clearance status?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        After logging in, go to the "Clearance Status" page. You will see a list of all departments with their current status (Pending, Approved, or Rejected). You can click "View Details" on any department to see individual requirements.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        What does "Pending" status mean?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        "Pending" means that the department has not yet reviewed your clearance request. Once they review and approve it, the status will change to "Approved". If there are issues, it may change to "Rejected" with remarks.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        What if my clearance gets rejected?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        If your clearance is rejected, check the remarks provided by the department. Address the issues mentioned (e.g., upload missing documents, clear outstanding fees) and resubmit. Contact the department directly if you need clarification.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        How do I download my clearance certificate?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Once all departments have approved your clearance, a "Download Certificate" button will appear on your dashboard. Click it to download your official graduation clearance certificate as a PDF file.
                    </div>
                </div>
            </div>
            
            <!-- Documents Category -->
            <div class="faq-category" data-category="documents">
                <div class="category-title">Document Questions</div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        What documents do I need to upload?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Required documents vary by department. Common documents include:
                        <ul>
                            <li>Lab equipment return receipts</li>
                            <li>Library clearance forms</li>
                            <li>Fee payment receipts</li>
                            <li>Hostel vacating forms</li>
                            <li>Thesis/dissertation copies</li>
                            <li>Internship completion certificates</li>
                            <li>Exit survey completion proof</li>
                        </ul>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        What file formats are accepted?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        The system accepts the following file formats: PDF, JPG, JPEG, PNG, DOC, and DOCX. Maximum file size is 5MB per document.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        Can I re-upload a document?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Yes, you can re-upload documents at any time. Go to the "Upload Documents" page and click the "Re-upload" button next to the document you want to replace. The old document will be automatically replaced.
                    </div>
                </div>
            </div>
            
            <!-- Technical Category -->
            <div class="faq-category" data-category="technical">
                <div class="category-title">Technical Support</div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        I'm having trouble logging in. What should I do?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        First, ensure you are using the correct email/student ID and password. If you've forgotten your password, use the "Forgot Password" feature. If the problem persists, clear your browser cache or try a different browser. Contact support if issues continue.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        The page is not loading properly.
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Try refreshing the page or clearing your browser cache. Ensure you have a stable internet connection. If the issue persists, try using a different browser (Chrome, Firefox, or Edge recommended) or contact technical support.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        How do I contact technical support?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        You can contact our support team via:
                        <ul>
                            <li>Email: joshuakaramuzi@gmail.com</li>
                            <li>Phone: +256 754 135 798</li>
                            <li>Visit the "Contact" page to submit a message</li>
                        </ul>
                        Our support hours are Monday-Friday, 9AM - 5PM.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        Is my data secure?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Yes, we take data security seriously. All personal information is encrypted, and we use industry-standard security measures to protect your data. Passwords are hashed, and all communications are secured via HTTPS.
                    </div>
                </div>
            </div>
        </div>
        
        <!-- No Results Message -->
        <div id="noResults" class="no-results" style="display: none;">
            <i class="fas fa-search"></i>
            <h4>No results found</h4>
            <p>Try searching with different keywords or browse the categories above</p>
        </div>
        
        <!-- Contact Support -->
        <div class="contact-support">
            <h3>Still have questions?</h3>
            <p>Can't find the answer you're looking for? Please contact our support team.</p>
            <a href="contact.php" class="btn-contact">
                <i class="fas fa-headset"></i> Contact Support
            </a>
        </div>
    </div>
</div>

<script>
// Toggle FAQ Answer
function toggleFAQ(element) {
    const answer = element.nextElementSibling;
    const icon = element.querySelector('i');
    const isOpen = answer.classList.contains('show');
    
    // Close all other answers
    document.querySelectorAll('.faq-answer').forEach(item => {
        if (item !== answer) {
            item.classList.remove('show');
            const otherIcon = item.previousElementSibling.querySelector('i');
            if (otherIcon) {
                otherIcon.classList.remove('fa-chevron-up');
                otherIcon.classList.add('fa-chevron-down');
            }
        }
    });
    
    // Toggle current
    answer.classList.toggle('show');
    if (isOpen) {
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    } else {
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    }
}

// Category Filter
document.querySelectorAll('.category-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        const category = this.dataset.category;
        
        if (category === 'all') {
            document.querySelectorAll('.faq-category').forEach(cat => cat.style.display = 'block');
        } else {
            document.querySelectorAll('.faq-category').forEach(cat => cat.style.display = 'none');
            document.querySelector(`.faq-category[data-category="${category}"]`).style.display = 'block';
        }
        
        // Clear search input when changing category
        document.getElementById('searchInput').value = '';
        document.getElementById('noResults').style.display = 'none';
    });
});

// Search Functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    let hasResults = false;
    
    if (searchTerm === '') {
        // Show all based on selected category
        const activeCategory = document.querySelector('.category-btn.active').dataset.category;
        if (activeCategory === 'all') {
            document.querySelectorAll('.faq-category').forEach(cat => cat.style.display = 'block');
        } else {
            document.querySelectorAll('.faq-category').forEach(cat => cat.style.display = 'none');
            document.querySelector(`.faq-category[data-category="${activeCategory}"]`).style.display = 'block';
        }
        document.querySelectorAll('.faq-item').forEach(item => item.style.display = 'block');
        document.getElementById('noResults').style.display = 'none';
        return;
    }
    
    // Search through questions and answers
    document.querySelectorAll('.faq-item').forEach(item => {
        const question = item.querySelector('.faq-question').textContent.toLowerCase();
        const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
        
        if (question.includes(searchTerm) || answer.includes(searchTerm)) {
            item.style.display = 'block';
            hasResults = true;
        } else {
            item.style.display = 'none';
        }
    });
    
    // Show/hide categories based on visible items
    document.querySelectorAll('.faq-category').forEach(category => {
        const visibleItems = category.querySelectorAll('.faq-item:not([style*="display: none"])').length;
        if (visibleItems === 0) {
            category.style.display = 'none';
        } else {
            category.style.display = 'block';
        }
    });
    
    // Show no results message
    document.getElementById('noResults').style.display = hasResults ? 'none' : 'block';
});
</script>

<?php require_once 'includes/footer.php'; ?>