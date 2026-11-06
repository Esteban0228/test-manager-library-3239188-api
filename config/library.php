<?php

return [
    'jwt_secret' => env('JWT_SECRET'),
    'jwt_ttl' => 28800, // 8 horas (CA-01.1)
    'admin_username' => env('ADMIN_USERNAME'),
    'admin_password' => env('ADMIN_PASSWORD'),
    'covers_dir' => env('COVERS_DIR') ?: storage_path('app/covers'),
];
