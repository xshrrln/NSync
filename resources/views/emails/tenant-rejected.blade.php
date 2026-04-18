<!DOCTYPE html>
<html>
<head>
    <title>Registration Application Decision - NSync</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #ef4444; color: white; padding: 30px; text-align: center; border-radius: 8px; }
        .header h1 { margin: 0; font-size: 28px; }
        .content { background-color: #f9fafb; padding: 30px; margin: 20px 0; border-radius: 8px; }
        .info-box { background-color: #fee2e2; border-left: 4px solid #ef4444; padding: 20px; margin: 20px 0; border-radius: 4px; }
        .footer { text-align: center; font-size: 12px; color: #999; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Application Status Update</h1>
        </div>
        
        <div class="content">
            <p>Hello,</p>
            
            <p>Thank you for your interest in NSync. We have reviewed your organization registration for <strong>{{ $tenant->name }}</strong>.</p>
            
            <div class="info-box">
                <p><strong>⚠️ Registration Rejected</strong></p>
                <p>Unfortunately, we are unable to approve your workspace registration at this time. This may be due to:
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Incomplete or inaccurate information</li>
                    <li>Policy compliance concerns</li>
                    <li>Service availability limitations</li>
                    <li>Other administrative reasons</li>
                </ul>
                </p>
            </div>

            <p><strong>Organization Details:</strong></p>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Organization: {{ $tenant->name }}</li>
                <li>Domain: {{ $tenant->domain }}.nsync.test</li>
                <li>Plan: {{ ucfirst($tenant->plan) }}</li>
            </ul>

            <p><strong>Next Steps:</strong></p>
            <ol style="margin: 10px 0; padding-left: 20px;">
                <li>Review the information you provided</li>
                <li>Contact our support team if you believe this was sent in error</li>
                <li>You are welcome to reapply with updated information</li>
            </ol>

            <p>If you have any questions or would like more information about why your application was rejected, please don't hesitate to contact our support team.</p>

            <p>Best regards,<br>The NSync Team</p>
        </div>
        
        <div class="footer">
            <p>&copy; 2026 NSync. All rights reserved.</p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
