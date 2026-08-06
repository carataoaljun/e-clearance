<?php

return [
    'apk_path' => env('STUDENT_APP_APK_PATH')
        ?: public_path('downloads/MCC-e-Clearance-Student.apk'),

    'download_name' => env('STUDENT_APP_DOWNLOAD_NAME', 'MCC-e-Clearance-Student.apk'),
];
