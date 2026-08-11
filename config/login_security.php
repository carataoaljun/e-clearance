<?php

return [
    'captcha_after' => (int) env('LOGIN_CAPTCHA_AFTER', 3),
    'lockout_after' => (int) env('LOGIN_LOCKOUT_AFTER', 5),
    'lockout_seconds' => (int) env('LOGIN_LOCKOUT_SECONDS', 15 * 60),
    'captcha_lifetime_seconds' => (int) env('LOGIN_CAPTCHA_LIFETIME_SECONDS', 5 * 60),
    'admin_otp_lifetime_seconds' => (int) env('ADMIN_LOGIN_OTP_LIFETIME_SECONDS', 10 * 60),
    'admin_otp_max_attempts' => (int) env('ADMIN_LOGIN_OTP_MAX_ATTEMPTS', 5),
];
