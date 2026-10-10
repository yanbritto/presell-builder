<?php
declare(strict_types=1);
require __DIR__ . '/app/config.php';
adminRequired();
$slug = is_string($_GET['campaign'] ?? null) ? $_GET['campaign'] : '';
$campaign = getCampaignBySlug($slug);
if ($campaign === null || ($campaign['template'] ?? '') !== 'modal') { http_response_code(404); exit; }
$exportMode = true;
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9-]/', '', $slug) . '.html"');
require __DIR__ . '/app/modal.php';
