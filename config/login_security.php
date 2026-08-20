<?php

return [
    'captcha_after' => (int) env('LOGIN_CAPTCHA_AFTER', 3),
    'lockout_after' => (int) env('LOGIN_LOCKOUT_AFTER', 5),
    'lockout_seconds' => (int) env('LOGIN_LOCKOUT_SECONDS', 15 * 60),
    'captcha_lifetime_seconds' => (int) env('LOGIN_CAPTCHA_LIFETIME_SECONDS', 5 * 60),

    // Emailed sign-in code, demanded by every portal the first time an account
    // signs in from a given browser.
    'otp_lifetime_seconds' => (int) env('LOGIN_OTP_LIFETIME_SECONDS', 10 * 60),
    'otp_max_attempts' => (int) env('LOGIN_OTP_MAX_ATTEMPTS', 5),

    // How long a browser stays verified for an account before it is asked again.
    'trusted_device_days' => (int) env('LOGIN_TRUSTED_DEVICE_DAYS', 30),
];
