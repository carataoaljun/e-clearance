<?php

return [
    'apk_path' => env('STUDENT_APP_APK_PATH')
        ?: base_path('mobile/student-android/app/build/outputs/apk/debug/app-debug.apk'),

    'download_name' => env('STUDENT_APP_DOWNLOAD_NAME', 'MCC-e-Clearance-Student.apk'),
];
