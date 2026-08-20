<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:24px;background:#eef6ff;font-family:Arial,sans-serif;color:#102a56">
    <div style="max-width:560px;margin:0 auto;padding:30px;border:1px solid #d5e6fa;border-radius:18px;background:#ffffff">
        <h1 style="margin:0 0 14px;font-size:24px">New device sign-in</h1>
        <p>Hello {{ $accountName }},</p>
        <p>Someone signed in to the {{ $panelName }} portal of the MCC Clearance System from a device this account has not used before. Enter this one-time code to finish signing in:</p>
        <p style="margin:24px 0;font-size:34px;font-weight:700;letter-spacing:8px;color:#075bea">{{ $code }}</p>
        <table style="width:100%;margin:0 0 20px;border-collapse:collapse;font-size:14px">
            <tr><td style="padding:6px 0;color:#5a6f90">Device</td><td style="padding:6px 0;text-align:right;font-weight:600">{{ $deviceLabel }}</td></tr>
            <tr><td style="padding:6px 0;color:#5a6f90">IP address</td><td style="padding:6px 0;text-align:right;font-weight:600">{{ $ipAddress }}</td></tr>
        </table>
        <p>This code expires in {{ $expiresInMinutes }} minutes and can be used only once. After it is accepted, this device will not be asked again for {{ $trustedForDays }} days.</p>
        <p style="color:#5a6f90">If this was not you, do not share this code. Your password may be known to someone else &mdash; change it and contact your administrator.</p>
    </div>
</body>
</html>
