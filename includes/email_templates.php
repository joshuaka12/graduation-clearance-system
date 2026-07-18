<?php
// includes/email_templates.php
// Email Content Templates ONLY (No Header/Footer Wrappers)

// ============================================
// STUDENT EMAIL TEMPLATES
// ============================================

function emailClearanceSubmitted($student_name, $department_name) {
    return "
        <h3>✅ Clearance Application Submitted</h3>
        <p>Dear <strong>{$student_name}</strong>,</p>
        <p>Your graduation clearance application for the <strong>{$department_name}</strong> has been submitted successfully.</p>
        <p>Our team will review your request within 24-48 hours. You will receive an email notification once a decision is made.</p>
        <p>You can track your clearance progress by logging into your dashboard.</p>
        <a href='https://graduationclearancesystem.gt.tc/student/dashboard.php' class='button'>Track Progress</a>
        <p style='margin-top: 20px;'>Thank you for using our system.</p>
    ";
}

function emailClearanceApproved($student_name, $department_name, $item_name, $remarks = null) {
    $remarks_html = $remarks ? "<p><strong>Remarks:</strong> {$remarks}</p>" : "";
    return "
        <h3>✅ Clearance Approved</h3>
        <p>Dear <strong>{$student_name}</strong>,</p>
        <p>Great news! Your clearance request for <strong>{$item_name}</strong> has been <span class='status-approved'>APPROVED</span> by the <strong>{$department_name}</strong>.</p>
        {$remarks_html}
        <p>Your clearance progress has been updated. Continue completing other requirements to finalize your graduation clearance.</p>
        <a href='https://graduationclearancesystem.gt.tc/student/clearance-status.php' class='button'>View Status</a>
    ";
}

function emailClearanceRejected($student_name, $department_name, $item_name, $reason) {
    return "
        <h3>❌ Clearance Rejected</h3>
        <p>Dear <strong>{$student_name}</strong>,</p>
        <p>Your clearance request for <strong>{$item_name}</strong> has been <span class='status-rejected'>REJECTED</span> by the <strong>{$department_name}</strong>.</p>
        <div style='background: #fef2f2; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ef4444;'>
            <strong>Reason for Rejection:</strong><br>
            {$reason}
        </div>
        <p>Please address the issues mentioned above and resubmit your clearance request.</p>
        <a href='https://graduationclearancesystem.gt.tc/student/clearance-status.php' class='button'>Resubmit Request</a>
    ";
}

function emailNewMessage($student_name, $department_name, $message_preview) {
    return "
        <h3>💬 New Message Received</h3>
        <p>Dear <strong>{$student_name}</strong>,</p>
        <p>You have received a new message from the <strong>{$department_name}</strong> regarding your clearance.</p>
        <div style='background: #f8f9fc; padding: 15px; border-radius: 8px; margin: 15px 0;'>
            <strong>Message Preview:</strong><br>
            \"{$message_preview}\"
        </div>
        <a href='https://graduationclearancesystem.gt.tc/student/message.php' class='button'>Reply Now</a>
    ";
}

function emailFinalClearanceCompleted($student_name, $student_id, $certificate_number) {
    return "
        <h3>🎉 Congratulations! You're Fully Cleared!</h3>
        <p>Dear <strong>{$student_name}</strong>,</p>
        <p>We are pleased to inform you that you have successfully completed all graduation clearance requirements!</p>
        <div style='background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0;'>
            <strong>🎓 Congratulations!</strong><br>
            Certificate Number: <strong>{$certificate_number}</strong>
        </div>
        <p>Your clearance certificate is now available for download. Please keep it for your records.</p>
        <a href='https://graduationclearancesystem.gt.tc/student/download-certificate.php' class='button'>Download Certificate</a>
    ";
}

function emailReminder($student_name, $deadline_date, $remaining_items) {
    return "
        <h3>⏰ Clearance Deadline Reminder</h3>
        <p>Dear <strong>{$student_name}</strong>,</p>
        <p>This is a friendly reminder that the graduation clearance deadline is approaching.</p>
        <div style='background: #fef3c7; padding: 15px; border-radius: 8px; margin: 15px 0;'>
            <strong>Deadline:</strong> {$deadline_date}<br>
            <strong>Remaining Requirements:</strong> {$remaining_items}
        </div>
        <p>Please complete all requirements before the deadline to avoid delays in your graduation process.</p>
        <a href='https://graduationclearancesystem.gt.tc/student/clearance-status.php' class='button'>View Requirements</a>
    ";
}


// ============================================
// DEPARTMENT HEAD EMAIL TEMPLATES
// ============================================

function emailNewClearanceRequest($dept_head_name, $student_name, $student_id, $department_name, $item_name) {
    return "
        <h3>📋 New Clearance Request</h3>
        <p>Dear <strong>{$dept_head_name}</strong>,</p>
        <p>A new clearance request has been submitted for your review.</p>
        <div style='background: #f8f9fc; padding: 15px; border-radius: 8px; margin: 15px 0;'>
            <strong>Student Name:</strong> {$student_name}<br>
            <strong>Student ID:</strong> {$student_id}<br>
            <strong>Department:</strong> {$department_name}<br>
            <strong>Item:</strong> {$item_name}
        </div>
        <a href='https://graduationclearancesystem.gt.tc/department-head/students.php' class='button'>Review Request</a>
    ";
}

function emailNewStudentMessage($dept_head_name, $student_name, $student_id, $message_preview) {
    return "
        <h3>💬 New Message from Student</h3>
        <p>Dear <strong>{$dept_head_name}</strong>,</p>
        <p>You have received a new message from a student regarding clearance.</p>
        <div style='background: #f8f9fc; padding: 15px; border-radius: 8px; margin: 15px 0;'>
            <strong>From:</strong> {$student_name} ({$student_id})<br>
            <strong>Message Preview:</strong><br>
            \"{$message_preview}\"
        </div>
        <a href='https://graduationclearancesystem.gt.tc/department-head/message.php' class='button'>Reply Now</a>
    ";
}


// ============================================
// ADMIN EMAIL TEMPLATES
// ============================================

function emailNewUserCreated($admin_name, $user_name, $role, $email) {
    return "
        <h3>👤 New User Created</h3>
        <p>Dear <strong>{$admin_name}</strong>,</p>
        <p>A new user account has been created in the system.</p>
        <div style='background: #f8f9fc; padding: 15px; border-radius: 8px; margin: 15px 0;'>
            <strong>Name:</strong> {$user_name}<br>
            <strong>Role:</strong> {$role}<br>
            <strong>Email:</strong> {$email}
        </div>
        <a href='https://graduationclearancesystem.gt.tc/admin/users/index.php' class='button'>View Users</a>
    ";
}

function emailSystemError($admin_name, $error_type, $error_details, $timestamp) {
    return "
        <h3>⚠️ System Error Alert</h3>
        <p>Dear <strong>{$admin_name}</strong>,</p>
        <p>The following system error has been detected:</p>
        <div style='background: #fef2f2; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ef4444;'>
            <strong>Error Type:</strong> {$error_type}<br>
            <strong>Time:</strong> {$timestamp}<br>
            <strong>Details:</strong><br>
            <code style='font-family: monospace; font-size: 12px;'>{$error_details}</code>
        </div>
        <p>Please investigate and resolve this issue as soon as possible.</p>
        <a href='https://graduationclearancesystem.gt.tc/admin/logs.php' class='button'>View System Logs</a>
    ";
}

function emailClearanceStatistics($admin_name, $stats) {
    return "
        <h3>📊 Clearance Statistics Report</h3>
        <p>Dear <strong>{$admin_name}</strong>,</p>
        <p>Here is your weekly clearance statistics report:</p>
        <div style='background: #f8f9fc; padding: 15px; border-radius: 8px; margin: 15px 0;'>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr><td style='padding: 5px 0;'><strong>Total Students:</strong></td><td style='padding: 5px 0;'>{$stats['total_students']}</td></tr>
                <tr><td style='padding: 5px 0;'><strong>Cleared Students:</strong></td><td style='padding: 5px 0;'>{$stats['cleared_students']}</td></tr>
                <tr><td style='padding: 5px 0;'><strong>Pending Clearances:</strong></td><td style='padding: 5px 0;'>{$stats['pending_clearances']}</td></tr>
                <tr><td style='padding: 5px 0;'><strong>Departments Active:</strong></td><td style='padding: 5px 0;'>{$stats['active_departments']}</td></tr>
                <tr><td style='padding: 5px 0;'><strong>Approval Rate:</strong></td><td style='padding: 5px 0;'>{$stats['approval_rate']}%</td></tr>
            </table>
        </div>
        <a href='https://graduationclearancesystem.gt.tc/admin/reports/index.php' class='button'>View Full Report</a>
    ";
}

function emailFailedLoginAttempt($admin_name, $email, $ip_address, $attempts, $timestamp) {
    return "
        <h3>🔐 Failed Login Attempt Alert</h3>
        <p>Dear <strong>{$admin_name}</strong>,</p>
        <p>Multiple failed login attempts have been detected for an account.</p>
        <div style='background: #fef3c7; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #f59e0b;'>
            <strong>Email:</strong> {$email}<br>
            <strong>IP Address:</strong> {$ip_address}<br>
            <strong>Failed Attempts:</strong> {$attempts}<br>
            <strong>Time:</strong> {$timestamp}
        </div>
        <p>If this wasn't you, please review your account security immediately.</p>
        <a href='https://graduationclearancesystem.gt.tc/admin/security.php' class='button'>Security Settings</a>
    ";
}
?>