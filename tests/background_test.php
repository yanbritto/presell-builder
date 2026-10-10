<?php
declare(strict_types=1);
require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/background.php';
$checks = 0;
foreach (['', '<svg xmlns="http://www.w3.org/2000/svg"></svg>', '<html>not an image</html>', str_repeat('x', 5242881)] as $bytes) {
    try { storePresellBackground($bytes); throw new RuntimeException('Invalid image accepted'); }
    catch (InvalidArgumentException $error) { $checks++; }
}
$bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ9sAAAAASUVORK5CYII=');
$expected = hash('sha256', $bytes) . '.png';
$existed = is_file(appRoot() . '/storage/media/' . $expected);
try {
    $file = storePresellBackground($bytes);
    if ($file !== $expected || file_get_contents(appRoot() . '/storage/media/' . $file) !== $bytes) { throw new RuntimeException('Image persistence failed'); }
    $checks++;
} finally { if (!$existed) { @unlink(appRoot() . '/storage/media/' . $expected); } }
echo $checks . " background checks passed\n";
