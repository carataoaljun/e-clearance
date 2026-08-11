<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:24px;background:#eef6ff;font-family:Arial,sans-serif;color:#102a56">
    <div style="max-width:560px;margin:0 auto;padding:30px;border:1px solid #d5e6fa;border-radius:18px;background:#ffffff">
        <h1 style="margin:0 0 14px;font-size:24px">Main Admin sign-in verification</h1>
        <p>Hello {{ $adminName }},</p>
        <p>Enter this one-time code to finish signing in to the MCC Clearance System:</p>
        <p style="margin:24px 0;font-size:34px;font-weight:700;letter-spacing:8px;color:#075bea">{{ $code }}</p>
        <p>This code expires in {{ $expiresInMinutes }} minutes and can be used only once.</p>
        <p style="color:#5a6f90">If you did not try to sign in, do not share this code and contact your system administrator.</p>
    </div>
</body>
</html>
