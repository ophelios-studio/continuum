<?php namespace Models\Legal\Services;

use Lighthouse\LighthouseService;
use Zephyrus\Core\Configuration;
use Zephyrus\Network\Response;

class LighthouseStorage implements StorageDriver
{
    private LighthouseService $service;

    public function __construct()
    {
        $apiKey = Configuration::read('services')['lighthouse']['api_key'];
        $this->service = new LighthouseService($apiKey);
    }

    public function storeFile(string $path, array $meta = []): array
    {
        $cid = $this->service->uploadFile($path);
        return ['provider' => 'lighthouse', 'cid' => $cid, 'uri' => LighthouseService::getFileUrl($cid)];
    }

    public function retrieveFile(string $cid, string $filename): Response
    {
        file_put_contents(ROOT_DIR . '/storage/' . $cid, LighthouseService::getFileUrl($cid));
        return Response::builder()->downloadInline(ROOT_DIR . '/storage/' . $cid, $filename);
    }
}
