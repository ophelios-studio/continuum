<?php namespace Models\Legal\Services;

use kornrunner\Keccak;
use Models\Account\Entities\Actor;
use Models\Legal\Brokers\EvidenceEventBroker;
use Models\Legal\Brokers\EvidenceFileBroker;
use Models\Legal\Entities\EvidenceFile;

final readonly class EvidenceFileService
{
    public function __construct(
        private StorageDriver $storage = new LighthouseStorage(),
        private EvidenceFileBroker $files = new EvidenceFileBroker(),
        private EvidenceEventBroker $events = new EvidenceEventBroker()
    ) {}

    public function findById(string $id): ?EvidenceFile
    {
        return EvidenceFile::build($this->files->findById($id));
    }

    public function addEncryptedFile(string $evidenceId, Actor $actor, array $upload, array $lit): EvidenceFile
    {
        $tmp = $upload['tmp_path'];
        $filename = $upload['filename'];
        $mime = $upload['mime'] ?? 'application/octet-stream';
        $size = $upload['size'] ?? filesize($tmp);
        [$sha256, $keccak] = self::computeHashes($tmp);
        $store = $this->storage->storeFile($tmp, [
            'filename' => $filename,
            'mime' => $mime
        ]);
        $new = (object) [
            'evidence_id' => $evidenceId,
            'filename' => $filename,
            'mime_type' => $mime,
            'byte_size' => $size,
            'sha256_hex' => $sha256,
            'keccak256_hex' => $keccak,
            'storage_provider' => $store['provider'] ?? 'lighthouse',
            'storage_cid' => $store['cid'] ?? null,
            'storage_uri' => $store['uri'] ?? null,
            'encrypted' => true,
            'lit_meta_json' => $lit
        ];
        $fileId = $this->files->insert($new);

        $litEvent = [
            'encrypted_symmetric_key' => $lit['encryptedSymmetricKey'] ?? null,
        ];
        if (isset($lit['accessControlConditions'])) {
            $litEvent['access_control_conditions'] = $lit['accessControlConditions'];
        }
        if (isset($lit['evmContractConditions'])) {
            $litEvent['evm_contract_conditions'] = $lit['evmContractConditions'];
        }
        if (isset($lit['unifiedAccessControlConditions'])) {
            $litEvent['uacc'] = $lit['unifiedAccessControlConditions'];
        }
        if (isset($lit['hashAlgo'])) {
            $litEvent['client_hash_algo'] = $lit['hashAlgo'];
        }
        if (isset($store['cid'])) {
            $litEvent['cid'] = $store['cid'];
        }
        $this->events->append($evidenceId, $actor->address, 'FILE_ATTACHED', [
            'file_id' => $fileId,
            'filename' => $filename,
            'sha256' => $sha256,
            'keccak' => $keccak,
            'storage' => $store,
            'lit' => $litEvent
        ]);
        return EvidenceFile::build($this->files->findById($fileId));
    }

    public static function computeHashes(string $path): array
    {
        $sha = hash_file('sha256', $path);
        $keccak = '0x' . Keccak::hash(file_get_contents($path), 256);
        return [$sha, $keccak];
    }
}
