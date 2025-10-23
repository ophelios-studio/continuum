<?php namespace Models\Legal;

use kornrunner\Keccak;

final class ContentHash
{
    public static function compute(array $manifest): string
    {
        $canon = self::canonicalize($manifest);
        $json  = json_encode($canon, JSON_UNESCAPED_SLASHES);
        return '0x' . Keccak::hash($json, 256);
    }

    private static function canonicalize($value)
    {
        if (is_array($value)) {
            // if associative: sort keys
            if (array_keys($value) !== range(0, count($value) - 1)) {
                ksort($value);
                foreach ($value as $k => $v) $value[$k] = self::canonicalize($v);
                return $value;
            }
            // if list: for files we want stable order. Sort by filename, then sha256 if keys present
            if (!empty($value) && isset($value[0]['filename'])) {
                usort($value, function ($a, $b) {
                    $fa = strtolower($a['filename'] ?? '');
                    $fb = strtolower($b['filename'] ?? '');
                    if ($fa === $fb) {
                        return strcmp(strtolower($a['sha256'] ?? ''), strtolower($b['sha256'] ?? ''));
                    }
                    return strcmp($fa, $fb);
                });
            }
            return array_map([self::class, 'canonicalize'], $value);
        }
        return $value;
    }
}
