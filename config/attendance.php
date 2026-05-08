<?php

return [
    // Disk can be local/public/ftp/sftp depending on filesystems config.
    'source_disk' => env('ATTENDANCE_SOURCE_DISK', 'local'),

    // Folder path in the disk. Latest csv/xlsx file in this folder will be used when Sisca does not upload manually.
    'source_path' => env('ATTENDANCE_SOURCE_PATH', ''),

    // Connection/table used for requestor autocomplete in Exit Permit form.
    'requestor_source_connection' => env('ATTENDANCE_REQUESTOR_SOURCE_CONNECTION', env('DB_CONNECTION', 'mysql')),
    'requestor_source_table' => env('ATTENDANCE_REQUESTOR_SOURCE_TABLE', 'absensi_karyawan'),

    // Only these departments are included in company attendance matching.
    // Can be overridden from .env with comma-separated values.
    'company_departments' => array_values(array_filter(array_map(
        fn(string $department) => strtoupper(trim($department)),
        explode(',', (string) env('ATTENDANCE_COMPANY_DEPARTMENTS', 'BIPO,INTERNSHIP,OUTSOURCE,IT')),
    ))),
];
