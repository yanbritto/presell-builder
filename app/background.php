<?php
declare(strict_types=1);

function storePresellBackground(string $bytes): string
{
    if ($bytes === '' || strlen($bytes) > 5242880) {
        throw new InvalidArgumentException('Use uma captura PNG, JPG ou WebP de até 5 MB.');
    }
    $info = @getimagesizefromstring($bytes);
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    if (!is_array($info) || !isset($extensions[$info['mime']]) || $info[0] * $info[1] > 40000000) {
        throw new InvalidArgumentException('A captura precisa ser uma imagem PNG, JPG ou WebP válida, com até 40 megapixels.');
    }
    $directory = appRoot() . '/storage/media';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível criar a pasta de imagens.');
    }
    $file = hash('sha256', $bytes) . '.' . $extensions[$info['mime']];
    if (file_put_contents($directory . '/' . $file, $bytes, LOCK_EX) === false) {
        throw new RuntimeException('Não foi possível salvar a captura.');
    }
    return $file;
}
