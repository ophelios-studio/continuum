<?php namespace Models\Legal\Services;

use RuntimeException;
use Zephyrus\Network\Response;
use Zephyrus\Security\Cryptography;

class LocalStorage implements StorageDriver
{
    public function storeFile(string $path, array $meta = []): array
    {
        $filename = Cryptography::randomString(32) . '.enc';
        move_uploaded_file($path, ROOT_DIR . '/storage/' . $filename);
        return ['provider' => 'local', 'cid' => $filename];
    }

    public function retrieveFile(string $cid, string $filename): Response
    {
        $path = ROOT_DIR . '/storage/' . $cid;
        if (!is_readable($path)) {
            throw new RuntimeException('File not available');
        }
        return Response::builder()->downloadInline($path, $filename);
    }
}
