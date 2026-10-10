<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if (PHP_SAPI !== 'cli') {
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');
}

return [
    'app_name' => (string) env('APP_NAME', 'Presell Builder'),
    'base_url' => (string) env('APP_BASE_URL', 'http://localhost:8000'),
    'admin_user' => (string) env('APP_ADMIN_USER', 'admin'),
    'admin_password_hash' => securePasswordHash(),
    'postback_secret' => (string) env('POSTBACK_SECRET', ''),
];
