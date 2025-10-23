<?php namespace Models\Legal\Services;

interface StorageDriver
{
    /**
     * @param string $path Local path to file
     * @param array $meta ['filename'=>..., 'mime'=>...]
     * @return array { provider: string, cid?: string, uri?: string, raw?: mixed }
     */
    public function storeFile(string $path, array $meta = []): array;
}
