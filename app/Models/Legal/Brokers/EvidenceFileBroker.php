<?php namespace Models\Legal\Brokers;

use Models\Core\Broker;
use stdClass;

final class EvidenceFileBroker extends Broker
{
    public function insert(stdClass $new): string
    {
        $sql = "INSERT INTO legal.evidence_file (evidence_id, filename, mime_type, byte_size, sha256_hex, keccak256_hex, 
                                 storage_provider, storage_cid, storage_uri, encrypted, lit_meta_json) 
                VALUES (:evidence_id, :filename, :mime_type, :byte_size, :sha256_hex, :keccak256_hex, 
                        :storage_provider, :storage_cid, :storage_uri, :encrypted, :lit_meta_json)
                RETURNING id";
        $params = [
            'evidence_id' => $new->evidence_id,
            'filename' => $new->filename,
            'mime_type' => $new->mime_type,
            'byte_size' => $new->byte_size,
            'sha256_hex' => $new->sha256_hex,
            'keccak256_hex' => $new->keccak256_hex,
            'storage_provider' => $new->storage_provider,
            'storage_cid' => $new->storage_cid,
            'storage_uri' => $new->storage_uri,
            'encrypted' => (bool) $new->encrypted,
            'lit_meta_json' => json_encode($new->lit_meta_json ?? null),
        ];
        return $this->query($sql, $params)->id;
    }

    public function findById(string $id): ?stdClass
    {
        return $this->selectSingle('SELECT * FROM legal.evidence_file WHERE id = :id', ['id' => $id]);
    }

    public function listForEvidence(string $evidenceId): array
    {
        return $this->select(
            'SELECT * FROM legal.evidence_file WHERE evidence_id = :eid ORDER BY created_at ASC',
            ['eid' => $evidenceId]
        );
    }

    public function deleteForEvidence(string $evidenceId, string $fileId): void
    {
        $this->query(
            'DELETE FROM legal.evidence_file WHERE id = :id AND evidence_id = :eid',
            ['id' => $fileId, 'eid' => $evidenceId]
        );
    }
}
