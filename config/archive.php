<?php

return [
    /*
    | Maximum uploaded ZIP size in kilobytes. The PHP/web-server upload limits
    | must also be at least this large.
    */
    'max_upload_kb' => (int) env('ARCHIVE_MAX_UPLOAD_KB', 512000),
];
