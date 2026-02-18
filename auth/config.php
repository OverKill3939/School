<?php
declare(strict_types=1);

return [
    'db' => [
        'driver' => getenv('DB_DRIVER') ?: 'sqlite',
        'sqlite_path' => getenv('DB_SQLITE_PATH') ?: (__DIR__ . '/../data/school_auth.sqlite'),
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'school_auth',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'session_name' => 'school_session',
        'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Tehran',
        'base_url' => getenv('APP_BASE_URL') ?: '',
    ],
];
return [
    'db' => [ /* دیتابیس اصلی */ ],
    'attendance_db' => [
        'driver'      => 'sqlite',
        'sqlite_path' => __DIR__ . '/../data/attendance.sqlite',
    ],
];