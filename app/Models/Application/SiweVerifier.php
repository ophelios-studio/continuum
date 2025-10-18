<?php namespace Models\Application;

use Elliptic\EC;
use kornrunner\Keccak;
use RuntimeException;

final readonly class SiweVerifier
{
    public const int DEFAULT_MAX_SKEW_SECONDS = 900; // 15 minutes

    public function __construct(
        private array $allowedDomains = [],
        private array $allowedOrigins = [],
        private array $allowedChainIds = [],
        private int $maxSkewSeconds = self::DEFAULT_MAX_SKEW_SECONDS
    ) {}

    public function verify(string $message, string $signature, string $expectedNonce): array
    {
        $fields = $this->parseSiweMessage($message);

        // Basic nonce / address presence
        if (!isset($fields['address']) || !isset($fields['nonce'])) {
            throw new RuntimeException('Malformed SIWE: address/nonce missing');
        }

        // Nonce match (server-stored)
        if (hash_equals($expectedNonce, $fields['nonce']) === false) {
            throw new RuntimeException('Invalid nonce');
        }

        if ($this->allowedDomains && !in_array($fields['domain'], $this->allowedDomains, true)) {
            throw new RuntimeException('Domain not allowed');
        }
        if ($this->allowedOrigins && !in_array($fields['uri'], $this->allowedOrigins, true)) {
            throw new RuntimeException('Origin not allowed');
        }
        if ($this->allowedChainIds && !in_array((int)$fields['chainId'], $this->allowedChainIds, true)) {
            throw new RuntimeException('Chain ID not allowed');
        }

        // Clock skew check on Issued At
        if (!empty($fields['issuedAt']) && $this->maxSkewSeconds > 0) {
            $iat = strtotime($fields['issuedAt']);
            if ($iat === false) {
                throw new RuntimeException('Invalid Issued At');
            }
            $now = time();
            if (abs($now - $iat) > $this->maxSkewSeconds) {
                throw new RuntimeException('SIWE message expired/stale');
            }
        }

        // Recover address from signature
        $recovered = $this->recoverAddressEIP191($message, $signature);
        if (strtolower($recovered) !== strtolower($fields['address'])) {
            throw new RuntimeException('Address mismatch');
        }

        return [
            'address' => strtolower($recovered),
            'fields'  => $fields,
        ];
    }

    /**
     * EIP-4361 canonical format.
     *
     * @param string $msg
     * @return array
     */
    private function parseSiweMessage(string $msg): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $msg);
        if (!$lines || count($lines) < 8) {
            throw new RuntimeException('Malformed SIWE message');
        }

        // Line 0: "<domain> wants you to sign in with your Ethereum account:"
        $first = $lines[0] ?? '';
        $domain = trim(str_replace(' wants you to sign in with your Ethereum account:', '', $first));

        // Line 1: "<address>"
        $address = trim($lines[1] ?? '');

        // Find keyed lines (order after the blank lines can vary with extra statement lines)
        $uri = $this->valueFromPrefixedLine($lines, 'URI: ');
        $version = $this->valueFromPrefixedLine($lines, 'Version: ');
        $chainId = $this->valueFromPrefixedLine($lines, 'Chain ID: ');
        $nonce = $this->valueFromPrefixedLine($lines, 'Nonce: ');
        $issuedAt = $this->valueFromPrefixedLine($lines, 'Issued At: ');
        if (!$domain || !$address || !$uri || !$version || !$chainId || !$nonce || !$issuedAt) {
            throw new RuntimeException('Missing SIWE fields');
        }
        return [
            'domain' => $domain,
            'address' => $address,
            'uri' => $uri,
            'version' => $version,
            'chainId' => (int) $chainId,
            'nonce' => $nonce,
            'issuedAt' => $issuedAt,
        ];
    }

    private function valueFromPrefixedLine(array $lines, string $prefix): ?string
    {
        foreach ($lines as $l) {
            if (str_starts_with($l, $prefix)) {
                return trim(substr($l, strlen($prefix)));
            }
        }
        return null;
    }

    /**
     * AI ASSISTANCE FOR SIGNATURE RECOVERY.
     *
     * EIP-191 personal_sign: keccak("\x19Ethereum Signed Message:\n" + len + message).
     *
     * @param string $message
     * @param string $signature
     * @return string
     */
    private function recoverAddressEIP191(string $message, string $signature): string
    {
        $prefix = "\x19Ethereum Signed Message:\n" . strlen($message);
        $msgHashHex = Keccak::hash($prefix . $message, 256);

        // Split signature
        $sig = ltrim($signature, '0x');
        if (strlen($sig) !== 130) {
            throw new RuntimeException('Bad signature length');
        }

        $r = '0x' . substr($sig, 0, 64);
        $s = '0x' . substr($sig, 64, 64);
        $v = hexdec(substr($sig, 128, 2));
        if ($v >= 27) {
            $v -= 27;
        } // normalize 27/28 -> 0/1

        $ec = new EC('secp256k1');
        $pubPoint = $ec->recoverPubKey(
            gmp_init($msgHashHex, 16), [
                'r' => gmp_init(substr($r, 2), 16),
                's' => gmp_init(substr($s, 2), 16),
            ],
            $v
        );

        $pubUncompressedHex = $pubPoint->encode('hex', false); // starts with 04
        return $this->pubkeyToAddress($pubUncompressedHex);
    }

    /**
     * AI ASSISTANCE FOR NORMALIZATION.
     *
     * @param string $pubkeyHex
     * @return string
     * @throws \Exception
     */
    private function pubkeyToAddress(string $pubkeyHex): string
    {
        // strip 0x, convert, drop 0x04
        $hex = ltrim($pubkeyHex, '0x');
        $hex = preg_replace('/[^0-9a-fA-F]/', '', $hex) ?? '';
        if ($hex === '') {
            throw new RuntimeException('Invalid public key');
        }
        if ((strlen($hex) % 2) === 1) {
            $hex = '0' . $hex;
        }
        $bin = hex2bin($hex);
        if ($bin === false || strlen($bin) < 65 || $bin[0] !== "\x04") {
            throw new RuntimeException('Invalid public key');
        }
        $body = substr($bin, 1); // remove 0x04
        $hash = Keccak::hash($body, 256);
        return '0x' . substr($hash, 24); // last 20 bytes
    }
}
