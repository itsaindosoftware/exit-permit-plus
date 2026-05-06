<?php

return [
    // Disk can be local/public/ftp/sftp depending on filesystems config.
    'source_disk' => env('ATTENDANCE_SOURCE_DISK', 'local'),

    // Folder path in the disk. Latest csv/xlsx file in this folder will be used when Sisca does not upload manually.
    'source_path' => env('ATTENDANCE_SOURCE_PATH', ''),

    // Only these departments are included in company attendance matching.
    'company_departments' => [
        'BIPO',
        'INTERNSHIP',
        'OUTSOURCE',
    ],
];
