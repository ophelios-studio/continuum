<?php namespace Models\Application;

readonly class AvatarDownloader
{
    public function __construct(private string $avatarUrl)
    {}

    public function download(string $filename): ?string
    {
        $headers = [
            'User-Agent: Continuum/1.0',
            'Accept: image/avif,image/webp,image/apng,image/*;q=0.8,*/*;q=0.5',
        ];
        $context = stream_context_create([
            'http' => [
                'header' => implode("\r\n", $headers) . "\r\n",
                'timeout' => 10,
                'follow_location' => 1,
                'ignore_errors' => true,
            ],
        ]);

        $avatarContent = @file_get_contents($this->avatarUrl, false, $context);
        if ($avatarContent === false) {
            return null;
        }

        // Determine extension from Content-Type header, fallback to URL path
        $ext = $this->extensionFromHeaders($http_response_header ?? [])
            ?? $this->extensionFromUrl($this->avatarUrl);
        if ($ext === null) {
            // Default to png if unknown
            $ext = 'png';
        }

        // Ensure filename has the extension (avoid double-appending)
        $target = $filename;
        if (!preg_match('/\.' . preg_quote($ext, '/') . '$/i', $target)) {
            // Strip any existing image extension before appending detected one
            $target = preg_replace('/\.(avif|webp|png|jpg|jpeg|gif|bmp|svg)$/i', '', $target) ?? $target;
            $target .= '.' . $ext;
        }

        $dir = ROOT_DIR . '/public/assets/images/avatars';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($dir . '/' . $target, $avatarContent);
        return $target;
    }

    private function extensionFromHeaders(array $headers): ?string
    {
        foreach ($headers as $h) {
            if (stripos($h, 'Content-Type:') === 0) {
                $type = trim(substr($h, strlen('Content-Type:')));
                // Remove charset or parameters
                $mime = strtolower(trim(strtok($type, ';')));
                return $this->mapMimeToExt($mime);
            }
        }
        return null;
    }

    private function extensionFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === '') {
            return null;
        }
        // Normalize common image extensions
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg', 'avif'], true)) {
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }
        return null;
    }

    private function mapMimeToExt(string $mime): ?string
    {
        return match ($mime) {
            'image/avif' => 'avif',
            'image/webp' => 'webp',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/svg+xml' => 'svg',
            default => null,
        };
    }
}