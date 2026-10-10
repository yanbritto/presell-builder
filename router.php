<?php
declare(strict_types=1);
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('~(?:^|/)(?:\.|app(?:/|$)|storage(?:/|$)|tests(?:/|$))~i', $path)
    || in_array(strtolower($path), ['/readme.md', '/router.php'], true)
    || str_contains($path, "\\")) {
    http_response_code(403);
    exit('Forbidden');
}
return false;
