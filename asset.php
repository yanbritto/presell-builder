<?php
declare(strict_types=1);
require __DIR__ . '/app/config.php';
$slug = is_string($_GET['campaign'] ?? null) ? $_GET['campaign'] : '';
$campaign = getCampaignBySlug($slug);
$file = $campaign['image_file'] ?? '';
if (!is_string($file) || !preg_match('/^[a-f0-9]{64}\.(png|jpg|webp|gif)$/', $file) || !is_file(appRoot() . '/storage/media/' . $file)) { http_response_code(404); exit; }
$mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif'][pathinfo($file, PATHINFO_EXTENSION)];
header('Content-Type: ' . $mime);
readfile(appRoot() . '/storage/media/' . $file);
