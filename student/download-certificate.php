<?php
session_start();

// Simple authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/db.php';
require_once '../config/functions.php';

$student_id = $_SESSION['user_id'];

// Check if student is fully cleared
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT d.id) as total_depts,
        COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN d.id END) as cleared_depts
    FROM departments d
    JOIN clearance_items ci ON d.id = ci.department_id
    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
    WHERE d.is_active = 1
");
$stmt->execute([$student_id]);
$clearance_status = $stmt->fetch();

$is_fully_cleared = ($clearance_status['total_depts'] > 0 && $clearance_status['cleared_depts'] == $clearance_status['total_depts']);

// If not fully cleared, redirect back
if (!$is_fully_cleared) {
    $_SESSION['error'] = "You are not yet fully cleared for graduation. Please complete all clearance requirements.";
    header('Location: clearance-status.php');
    exit();
}

// Get student info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Get clearance details for each department
$stmt = $pdo->prepare("
    SELECT 
        d.id,
        d.department_name,
        d.department_code,
        MAX(sc.reviewed_at) as cleared_date,
        MAX(CASE WHEN sc.status = 'approved' THEN sc.reviewed_at END) as approval_date
    FROM departments d
    JOIN clearance_items ci ON d.id = ci.department_id
    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
    WHERE d.is_active = 1
    GROUP BY d.id, d.department_name, d.department_code
    HAVING MAX(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) = 1
    ORDER BY d.clearance_order ASC
");
$stmt->execute([$student_id]);
$department_clearance = $stmt->fetchAll();

// Check if certificate already exists
$stmt = $pdo->prepare("SELECT * FROM clearance_certificates WHERE student_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$student_id]);
$certificate = $stmt->fetch();

// If no certificate exists, generate one
if (!$certificate) {
    $certificate_number = 'GCS-' . date('Y') . '-' . str_pad($student_id, 5, '0', STR_PAD_LEFT);
    $verification_code = strtoupper(bin2hex(random_bytes(4)));
    
    $stmt = $pdo->prepare("
        INSERT INTO clearance_certificates (student_id, certificate_number, verification_code, issued_date, created_at) 
        VALUES (?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$student_id, $certificate_number, $verification_code]);
    
    // Get the newly created certificate
    $stmt = $pdo->prepare("SELECT * FROM clearance_certificates WHERE student_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$student_id]);
    $certificate = $stmt->fetch();
}

// Get profile picture path
$profile_pic = $student['profile_pic'] ?? 'default-avatar.png';
$profile_pic_exists = !empty($profile_pic) && $profile_pic != 'default-avatar.png' && file_exists('../assets/uploads/Students-profile/' . $profile_pic);

$page_title = 'Graduation Clearance Certificate';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           CERTIFICATE STYLES - OPTIMIZED FOR ONE A4 PAGE
           ============================================================ */
        
        /* Reset for print */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #e8ecef;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        /* Container - A4 size */
        .certificate-wrapper {
            max-width: 210mm;
            width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        
        /* ============================================================
           TOOLBAR - Hidden when printing
           ============================================================ */
        .toolbar {
            padding: 12px 24px;
            background: var(--primary, #800020);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            border-bottom: 3px solid #c9a84c;
        }
        
        .toolbar .title-section h4 {
            color: white;
            margin: 0;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .toolbar .title-section small {
            color: rgba(255,255,255,0.7);
            font-size: 0.7rem;
        }
        
        .toolbar-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-toolbar {
            padding: 8px 16px;
            border-radius: 50px;
            border: none;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            cursor: pointer;
        }
        
        .btn-toolbar:hover {
            transform: translateY(-2px);
        }
        
        .btn-print {
            background: white;
            color: #800020;
        }
        .btn-print:hover {
            background: #f0f0f0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-pdf {
            background: #c9a84c;
            color: white;
        }
        .btn-pdf:hover {
            background: #a8883c;
            box-shadow: 0 5px 15px rgba(201,168,76,0.4);
        }
        
        .btn-back {
            background: rgba(255,255,255,0.15);
            color: white;
        }
        .btn-back:hover {
            background: rgba(255,255,255,0.25);
        }
        
        /* ============================================================
           CERTIFICATE CONTENT - A4 PORTRAIT OPTIMIZED
           ============================================================ */
        .certificate-content {
            padding: 12px 20px 10px;
            background: white;
        }
        
        /* Double border */
        .certificate-border {
            border: 6px double #d4b86a;
            padding: 12px 18px 10px;
            position: relative;
            background: white;
        }
        
        .certificate-border::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            right: 2px;
            bottom: 2px;
            border: 1px solid #f0e6c8;
            pointer-events: none;
        }
        
        /* ============================================================
           HEADER - TIGHT SPACING
           ============================================================ */
        .cert-header {
            text-align: center;
            border-bottom: 2px solid #f0e6c8;
            padding-bottom: 6px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .cert-logo {
            width: 50px;
            height: 50px;
            flex-shrink: 0;
        }
        
        .cert-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .cert-header-text h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            font-weight: 800;
            color: #800020;
            letter-spacing: 2px;
            margin: 0;
            line-height: 1.2;
        }
        
        .cert-header-text .subtitle {
            font-size: 0.6rem;
            color: #6c7683;
            letter-spacing: 4px;
            text-transform: uppercase;
            margin: 0;
        }
        
        .cert-number {
            font-size: 0.6rem;
            color: #6c7683;
            margin-top: 0;
        }
        
        .cert-number strong {
            color: #800020;
        }
        
        /* ============================================================
           STUDENT INFO - WITH PHOTO
           ============================================================ */
        .student-info-row {
            display: flex;
            gap: 14px;
            margin: 4px 0 6px;
            padding: 6px 12px;
            background: #faf8f5;
            border-radius: 8px;
            border-left: 3px solid #c9a84c;
            align-items: center;
        }
        
        .student-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid #c9a84c;
            flex-shrink: 0;
            background: #f0f0f0;
        }
        
        .student-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .student-photo .initials {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #800020;
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .student-name-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: #800020;
            margin: 0;
            line-height: 1.2;
        }
        
        .student-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px 20px;
            flex: 1;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 1px 0;
            font-size: 0.65rem;
            border-bottom: 1px dashed #eee;
        }
        
        .info-item .label {
            color: #6c7683;
            font-weight: 500;
        }
        
        .info-item .value {
            color: #2d3440;
            font-weight: 600;
        }
        
        /* ============================================================
           CLEARANCE STATEMENT - COMPACT
           ============================================================ */
        .clearance-statement {
            text-align: center;
            padding: 4px 12px;
            margin: 4px 0 6px;
            background: linear-gradient(135deg, #fdf8f0, #faf5ea);
            border-radius: 6px;
            border: 1px solid #f0e6c8;
        }
        
        .clearance-statement p {
            font-size: 0.65rem;
            line-height: 1.4;
            color: #4a5360;
            margin: 0;
            font-style: italic;
        }
        
        .clearance-statement p::before {
            content: '"';
            font-size: 1rem;
            color: #c9a84c;
            font-family: 'Playfair Display', serif;
        }
        
        .clearance-statement p::after {
            content: '"';
            font-size: 1rem;
            color: #c9a84c;
            font-family: 'Playfair Display', serif;
        }
        
        /* ============================================================
           CLEARANCE TABLE - COMPACT
           ============================================================ */
        .clearance-table-wrap {
            margin: 4px 0 6px;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #e8ecef;
        }
        
        .clearance-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.6rem;
        }
        
        .clearance-table thead {
            background: #800020;
            color: white;
        }
        
        .clearance-table th {
            padding: 4px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 0.6rem;
        }
        
        .clearance-table td {
            padding: 3px 10px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.6rem;
        }
        
        .clearance-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .clearance-table tbody tr:nth-child(even) {
            background: #fafafc;
        }
        
        .status-cleared {
            color: #10b981;
            font-weight: 600;
        }
        
        .status-cleared i {
            margin-right: 3px;
        }
        
        /* ============================================================
           FOOTER - COMPACT
           ============================================================ */
        .cert-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 4px;
            padding-top: 6px;
            border-top: 2px solid #f0e6c8;
            flex-wrap: wrap;
            gap: 4px;
        }
        
        .signature-section {
            text-align: center;
        }
        
        .signature-line {
            width: 160px;
            border-bottom: 2px solid #2d3440;
            margin: 2px auto 4px;
        }
        
        .signature-section .title {
            font-size: 0.55rem;
            color: #6c7683;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }
        
        .signature-section .name {
            font-weight: 600;
            font-size: 0.65rem;
            color: #2d3440;
            margin: 0;
        }
        
        .stamp-section {
            text-align: center;
        }
        
        .stamp-placeholder {
            width: 55px;
            height: 55px;
            border: 2px dashed #c9a84c;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2px;
            color: #c9a84c;
            font-size: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stamp-section .title {
            font-size: 0.5rem;
            color: #6c7683;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }
        
        .verification-section {
            text-align: right;
            font-size: 0.55rem;
            color: #6c7683;
        }
        
        .verification-section .code {
            font-family: monospace;
            color: #800020;
            font-weight: 700;
            font-size: 0.7rem;
            letter-spacing: 2px;
        }
        
        .verification-section .label {
            font-size: 0.5rem;
        }
        
        /* ============================================================
           PRINT STYLES - ONE A4 PAGE
           ============================================================ */
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                display: block !important;
            }
            
            .certificate-wrapper {
                max-width: 100% !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .toolbar {
                display: none !important;
            }
            
            .certificate-content {
                padding: 8px 14px 6px !important;
            }
            
            .certificate-border {
                border: 4px double #d4b86a !important;
                padding: 8px 12px 6px !important;
            }
            
            .cert-header h1 {
                font-size: 1.1rem !important;
            }
            
            .student-name-title {
                font-size: 1rem !important;
            }
            
            .student-info-grid {
                grid-template-columns: 1fr 1fr !important;
            }
            
            .info-item {
                font-size: 0.55rem !important;
                padding: 1px 0 !important;
            }
            
            .clearance-table {
                font-size: 0.5rem !important;
            }
            
            .clearance-table th,
            .clearance-table td {
                padding: 2px 8px !important;
            }
            
            .clearance-statement p {
                font-size: 0.55rem !important;
                line-height: 1.3 !important;
            }
            
            .student-info-row {
                padding: 4px 10px !important;
                margin: 3px 0 4px !important;
            }
            
            .student-photo {
                width: 50px !important;
                height: 50px !important;
            }
            
            .cert-footer {
                margin-top: 3px !important;
                padding-top: 4px !important;
            }
            
            .signature-line {
                width: 120px !important;
            }
            
            .stamp-placeholder {
                width: 45px !important;
                height: 45px !important;
                font-size: 0.4rem !important;
            }
            
            /* Force one page */
            .certificate-border {
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
            }
            
            .certificate-content {
                page-break-after: avoid !important;
            }
            
            /* Hide any overflow */
            .certificate-wrapper {
                overflow: hidden !important;
                max-height: 297mm !important;
            }
        }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .certificate-content {
                padding: 8px 12px !important;
            }
            
            .certificate-border {
                padding: 8px !important;
            }
            
            .cert-header h1 {
                font-size: 1rem !important;
            }
            
            .student-info-grid {
                grid-template-columns: 1fr !important;
            }
            
            .student-info-row {
                flex-wrap: wrap;
                justify-content: center;
                text-align: center;
            }
            
            .cert-footer {
                flex-direction: column;
                align-items: center !important;
                text-align: center !important;
            }
            
            .verification-section {
                text-align: center !important;
            }
            
            .toolbar {
                flex-direction: column;
                align-items: stretch;
                padding: 10px 15px;
            }
            
            .toolbar .title-section {
                text-align: center;
            }
            
            .toolbar-actions {
                justify-content: center;
            }
            
            .btn-toolbar {
                flex: 1;
                justify-content: center;
                padding: 6px 12px;
                font-size: 0.7rem;
            }
        }
        
        @media (max-width: 576px) {
            .certificate-content {
                padding: 4px 6px !important;
            }
            
            .certificate-border {
                padding: 4px !important;
                border-width: 3px !important;
            }
            
            .cert-header h1 {
                font-size: 0.85rem !important;
            }
            
            .student-name-title {
                font-size: 0.9rem !important;
            }
            
            .student-photo {
                width: 40px !important;
                height: 40px !important;
            }
            
            .clearance-table {
                font-size: 0.5rem !important;
            }
            
            .clearance-table th,
            .clearance-table td {
                padding: 2px 6px !important;
            }
            
            .info-item {
                font-size: 0.5rem !important;
            }
            
            .student-info-grid {
                gap: 1px !important;
            }
            
            .signature-line {
                width: 80px !important;
            }
        }
    </style>
</head>
<body>
    <div class="certificate-wrapper" id="certificateWrapper">
        <!-- Toolbar -->
        <div class="toolbar" id="toolbar">
            <div class="title-section">
                <h4><i class="fas fa-certificate me-2"></i> Graduation Clearance Certificate</h4>
                <small>Valid proof of graduation clearance</small>
            </div>
            <div class="toolbar-actions">
                <!-- Back button now goes to clearance-status.php -->
                <a href="clearance-status.php" class="btn-toolbar btn-back">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <button class="btn-toolbar btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="btn-toolbar btn-pdf" onclick="downloadPDF()">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
            </div>
        </div>
        
        <!-- Certificate Content -->
        <div class="certificate-content" id="certificateContent">
            <div class="certificate-border">
                <!-- Header -->
                <div class="cert-header">
                    <div class="cert-logo">
                        <img src="../assets/uploads/Students-profile/logo.png" alt="University Logo" onerror="this.style.display='none'">
                    </div>
                    <div class="cert-header-text">
                        <h1>GRADUATION CLEARANCE CERTIFICATE</h1>
                        <div class="subtitle">Graduation Clearance System</div>
                        <div class="cert-number">
                            Certificate No: <strong><?php echo $certificate['certificate_number']; ?></strong>
                        </div>
                    </div>
                </div>
                
                <!-- Student Info with Photo -->
                <div class="student-info-row">
                    <div class="student-photo">
                        <?php if ($profile_pic_exists): ?>
                            <img src="../assets/uploads/Students-profile/<?php echo $profile_pic; ?>" alt="<?php echo htmlspecialchars($student['full_name']); ?>">
                        <?php else: ?>
                            <div class="initials"><?php echo strtoupper(substr($student['full_name'], 0, 1)); ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;">
                        <div class="student-name-title"><?php echo htmlspecialchars($student['full_name']); ?></div>
                        <div class="student-info-grid">
                            <div class="info-item">
                                <span class="label">Registration No.</span>
                                <span class="value"><?php echo htmlspecialchars($student['student_id']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Student No.</span>
                                <span class="value"><?php echo htmlspecialchars($student['student_id']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Academic Year</span>
                                <span class="value"><?php echo date('Y'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Graduation Year</span>
                                <span class="value"><?php echo date('Y'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Date of Clearance</span>
                                <span class="value"><?php echo date('F d, Y'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Verification Code</span>
                                <span class="value" style="font-family: monospace; letter-spacing: 2px;"><?php echo $certificate['verification_code']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Clearance Statement -->
                <div class="clearance-statement">
                    <p>
                        This certifies that the above-named student has successfully completed the graduation clearance process 
                        and has been cleared by all required university departments. The student is therefore eligible for graduation 
                        in accordance with the University's policies and regulations.
                    </p>
                </div>
                
                <!-- Clearance Table -->
                <div class="clearance-table-wrap">
                    <table class="clearance-table">
                        <thead>
                            <tr>
                                <th style="width:5%;">#</th>
                                <th style="width:55%;">Department</th>
                                <th style="width:20%;">Status</th>
                                <th style="width:20%;">Date Cleared</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $count = 1; foreach ($department_clearance as $dept): ?>
                            <tr>
                                <td><?php echo $count++; ?></td>
                                <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                <td>
                                    <span class="status-cleared">
                                        <i class="fas fa-check-circle"></i> Cleared
                                    </span>
                                </td>
                                <td><?php echo $dept['approval_date'] ? date('d/m/Y', strtotime($dept['approval_date'])) : '—'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Footer -->
                <div class="cert-footer">
                    <div class="signature-section">
                        <div class="name">Academic Registry Officer</div>
                        <div class="signature-line"></div>
                        <div class="title">Signature &amp; Date</div>
                    </div>
                    
                    <div class="stamp-section">
                        <div class="stamp-placeholder">
                            OFFICIAL<br>STAMP
                        </div>
                        <div class="title">University Stamp</div>
                    </div>
                    
                    <div class="verification-section">
                        <div>
                            <span class="label">Verification Code</span>
                            <div class="code"><?php echo $certificate['verification_code']; ?></div>
                        </div>
                        <div style="margin-top:2px; font-size:0.5rem;">
                            <i class="far fa-calendar-alt me-1"></i>
                            Issued: <?php echo date('d/m/Y H:i'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <script>
        function downloadPDF() {
            const element = document.getElementById('certificateContent');
            const btn = event.target;
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Generating...';
            btn.disabled = true;
            
            const opt = {
                margin:        [0.3, 0.3, 0.3, 0.3],
                filename:     'Graduation_Clearance_Certificate_<?php echo $student['student_id']; ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { 
                    scale: 2, 
                    useCORS: true, 
                    letterRendering: true,
                    width: 793,
                    height: 1123
                },
                jsPDF:        { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'portrait',
                    compress: true
                },
                pagebreak:    { mode: 'avoid-all' }
            };
            
            html2pdf().set(opt).from(element).save().then(function() {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }).catch(function(err) {
                console.error('PDF generation error:', err);
                btn.innerHTML = originalText;
                btn.disabled = false;
                alert('There was an error generating the PDF. Please try printing instead.');
            });
        }
        
        <?php if (isset($_GET['print'])): ?>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1500);
        };
        <?php endif; ?>
    </script>
</body>
</html>