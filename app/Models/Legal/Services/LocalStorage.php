<?php namespace Models\Legal\Services;

use Zephyrus\Security\Cryptography;

class LocalStorage implements StorageDriver
{
    public function storeFile(string $path, array $meta = []): array
    {
        $filename = Cryptography::randomString(32) . '.enc';
        move_uploaded_file($path, ROOT_DIR . '/storage/' . $filename);
        return ['provider' => 'local', 'cid' => $filename];
    }
}
