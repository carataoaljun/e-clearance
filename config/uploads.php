<?php

return [
    /*
    | Legacy files should be migrated with system:migrate-private-uploads.
    | Keep public fallback disabled so uploaded documents cannot bypass the
    | application's authorization-controlled response handlers.
    */
    'allow_legacy_public_files' => (bool) env('UPLOAD_ALLOW_LEGACY_PUBLIC_FILES', false),
];
