<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Patch Available</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="padding:20px 24px;border-bottom:1px solid #e2e8f0;background:#f1f5f9;">
                <p style="margin:0;font-size:12px;letter-spacing:0.1em;font-weight:700;color:#16a34a;text-transform:uppercase;">NSync Update Center</p>
                <h1 style="margin:8px 0 0 0;font-size:22px;line-height:30px;color:#0f172a;">A New Workspace Update Is Available</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <p style="margin:0 0 14px 0;font-size:15px;line-height:24px;">Hello {{ $tenant->tenant_admin ?: 'Tenant Admin' }},</p>
                <p style="margin:0 0 14px 0;font-size:15px;line-height:24px;">
                    A new patch was published for <strong>{{ $tenant->name }}</strong> and is now ready for review.
                </p>
                <div style="margin:18px 0;padding:14px 16px;border:1px solid #dbeafe;background:#eff6ff;border-radius:10px;">
                    <p style="margin:0 0 6px 0;font-size:14px;font-weight:700;color:#1e3a8a;">{{ $patch->title }}</p>
                    <p style="margin:0;font-size:13px;line-height:20px;color:#334155;">{{ $patch->description }}</p>
                </div>
                <p style="margin:0 0 20px 0;font-size:14px;line-height:22px;color:#475569;">
                    Only tenant admins can apply updates. Once applied, the patch affects the whole workspace, including all members.
                </p>
                <a href="{{ $updateCenterUrl }}" style="display:inline-block;padding:11px 18px;background:#16a34a;color:#ffffff;text-decoration:none;border-radius:10px;font-size:14px;font-weight:700;">
                    Open Update Center
                </a>
            </td>
        </tr>
    </table>
</body>
</html>

