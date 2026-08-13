<?php

return [
    /*
     * Path to the Android APK served from the student sidebar.
     *
     * The default stays relative to public/ on purpose. php artisan config:cache
     * freezes this value into bootstrap/cache/config.php, which is never shipped
     * by a deploy, so an absolute default silently keeps pointing at whichever
     * location was current when the cache was built.
     *
     * STUDENT_APP_APK_PATH may still be an absolute path to a signed APK stored
     * outside the document root.
     */
    'apk_path' => env('STUDENT_APP_APK_PATH') ?: 'downloads/MCC-e-Clearance-Student.apk',

    'download_name' => env('STUDENT_APP_DOWNLOAD_NAME', 'MCC-e-Clearance-Student.apk'),
];
