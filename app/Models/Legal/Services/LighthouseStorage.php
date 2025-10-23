<?php namespace Models\Legal\Services;

use Lighthouse\LighthouseService;
use Zephyrus\Core\Configuration;

class LighthouseStorage implements StorageDriver
{
    public function storeFile(string $path, array $meta = []): array
    {
        $apiKey = Configuration::read('services')['lighthouse']['api_key'];
        $service = new LighthouseService($apiKey);
        $cid = $service->uploadFile($path);
        return ['provider' => 'lighthouse', 'cid' => $cid, 'uri' => LighthouseService::getFileUrl($cid)];
    }
}
