<?php namespace Models\Legal\Brokers;

use Models\Core\Broker;
use stdClass;

final class EvidenceRevisionBroker extends Broker
{
    public function listForEvidence(string $evidenceId): array
    {
        return $this->select(
            "SELECT * FROM legal.evidence_revision WHERE evidence_id = :id ORDER BY rev_no ASC",
            ['id' => $evidenceId]
        );
    }

    public function findById(int $revisionId): ?stdClass
    {
        return $this->selectSingle(
            "SELECT * FROM legal.evidence_revision WHERE id = :id",
            ['id' => $revisionId]
        );
    }

    public function findCurrentForEvidence(string $evidenceId): ?stdClass
    {
        return $this->selectSingle(
            "SELECT * FROM legal.evidence_revision WHERE evidence_id = :id ORDER BY rev_no DESC LIMIT 1",
            ['id' => $evidenceId]
        );
    }

    public function updateRevisionContent(int $revisionId, string $contentHash, ?string $mediaUri = null): void
    {
        $this->query(
            "UPDATE legal.evidence_revision
             SET content_hash = :ch, media_uri = :uri, updated_at = NOW()
             WHERE id = :id",
            [
                'id' => $revisionId,
                'ch' => strtolower($contentHash),
                'uri'=> $mediaUri
            ]
        );
    }

    public function anchor(int $revisionId, string $txHash): void
    {
        $this->query(
            "UPDATE legal.evidence_revision
             SET anchor_tx = :tx, anchored_at = NOW(), updated_at = NOW()
             WHERE id = :id",
            ['id' => $revisionId, 'tx' => strtolower($txHash)]
        );
        $row = $this->selectSingle(
            "SELECT evidence_id, content_hash, media_uri FROM legal.evidence_revision WHERE id = :id",
            ['id' => $revisionId]
        );
        if ($row) {
            $this->query(
                "UPDATE legal.evidence
                 SET content_hash = :ch,
                     media_uri = :uri,
                     anchor_tx = :tx,
                     anchored_at = NOW(),
                     status = 'ANCHORED',
                     updated_at = NOW()
                 WHERE id = :eid",
                [
                    'eid' => $row->evidence_id,
                    'ch'  => strtolower($row->content_hash ?? ('0x' . str_repeat('0',64))),
                    'uri' => $row->media_uri,
                    'tx'  => strtolower($txHash)
                ]
            );
        }
    }

    public function insert(string $evidenceId, string $createdBy, ?string $contentHash = null, ?string $mediaUri = null): int
    {
        $content = $contentHash ?: '0x' . str_repeat('0', 64);
        $sql = "
            INSERT INTO legal.evidence_revision (evidence_id, rev_no, content_hash, media_uri, created_by)
            SELECT
                :evidence_id,
                COALESCE(MAX(rev_no) + 1, 1) AS next_rev,
                :content_hash,
                :media_uri,
                :created_by
            FROM legal.evidence_revision
            WHERE evidence_id = :evidence_id
            RETURNING id";
        $row = $this->query($sql, [
            'evidence_id' => $evidenceId,
            'content_hash' => strtolower($content),
            'media_uri' => $mediaUri,
            'created_by' => strtolower($createdBy),
        ]);
        return $row->id;
    }
}
