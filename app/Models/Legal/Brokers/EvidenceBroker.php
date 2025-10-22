<?php namespace Models\Legal\Brokers;

use Models\Account\Entities\Actor;
use Models\Core\Broker;
use stdClass;

final class EvidenceBroker extends Broker
{
    public function insert(stdClass $new, Actor $submitter): string
    {
        $sql = "INSERT INTO legal.evidence(case_id, title, kind, description, jurisdiction, external_uri, physical_tag, serial,
             content_hash, media_uri, evidence_id_hex, anchor_tx, anchored_at,
             submitter_address, current_custodian, pending_custodian, status)
            VALUES(:case_id, :title, :kind, :description, :jurisdiction, :external_uri, :physical_tag, :serial,
                 :content_hash, :media_uri, :evidence_id_hex, :anchor_tx, :anchored_at,
                 :submitter_address, :current_custodian, :pending_custodian, :status)
            RETURNING id";
        $params = [
            'case_id' => $new->case_id,
            'title' => $new->title,
            'kind' => $new->kind,
            'description' => $new->description ?? '',
            'jurisdiction' => $new->jurisdiction,
            'external_uri' => $new->external_uri ?? null,
            'physical_tag' => $new->physical_tag ?? null,
            'serial' => $new->serial ?? null,
            'content_hash' => $new->content_hash ?? null,
            'media_uri' => $new->media_uri ?? null,
            'evidence_id_hex' => $new->evidence_id_hex ?? null,
            'anchor_tx' => null,
            'anchored_at' => null,
            'submitter_address' => $submitter->address,
            'current_custodian' => $submitter->address,
            'pending_custodian' => null,
            'status' => $new->status ?? 'DRAFT',
        ];

        return $this->query($sql, $params)->id;
    }

    public function findById(string $id): ?stdClass
    {
        return $this->selectSingle('SELECT * FROM legal.evidence WHERE id = :id', ['id' => $id]);
    }

    public function findByEvidenceIdHex(string $evidenceIdHex): ?stdClass
    {
        return $this->selectSingle('SELECT * FROM legal.evidence WHERE evidence_id_hex = :h', ['h' => strtolower($evidenceIdHex)]);
    }

    public function listForCase(string $caseId, ?string $search = null, ?string $kind = null, ?string $status = null): array
    {
        $where  = ['e.case_id = :cid'];
        $params = ['cid' => $caseId];

        if ($search) {
            $where[] = '(e.title ILIKE :q OR e.serial ILIKE :q OR e.physical_tag ILIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        if ($kind) {
            $where[] = 'e.kind = :kind';
            $params['kind'] = $kind;
        }
        if ($status) {
            $where[] = 'e.status = :status';
            $params['status'] = $status;
        }

        $sql = 'SELECT e.* FROM legal.evidence e WHERE ' . implode(' AND ', $where) . ' ORDER BY e.updated_at DESC';
        return $this->select($sql, $params);
    }

    public function updateAnchorInfo(string $id, string $evidenceIdHex, string $contentHash, ?string $mediaUri, string $txHash): void
    {
        $sql = "UPDATE legal.evidence
                SET evidence_id_hex = :hex,
                    content_hash = :ch,
                    media_uri = :uri,
                    anchor_tx = :tx,
                    anchored_at = COALESCE(:anchored_at, NOW()),
                    status = 'ANCHORED',
                    updated_at = NOW(),
                    anchored_at = NOW()
                WHERE id = :id";
        $this->query($sql, [
            'id' => $id,
            'hex' => strtolower($evidenceIdHex),
            'ch' => strtolower($contentHash),
            'uri' => $mediaUri,
            'tx' => strtolower($txHash)
        ]);
    }

    public function setPendingCustodian(string $id, string $toAddress): void
    {
        $sql = "UPDATE legal.evidence
                SET pending_custodian = :to, updated_at = NOW()
                WHERE id = :id";
        $this->query($sql, ['id' => $id, 'to' => strtolower($toAddress)]);
    }

    public function acceptCustody(string $id, string $toAddress): void
    {
        $sql = "UPDATE legal.evidence
                SET current_custodian = :to,
                    pending_custodian = NULL,
                    updated_at = NOW()
                WHERE id = :id";
        $this->query($sql, ['id' => $id, 'to' => strtolower($toAddress)]);
    }

    public function returnCustody(string $id, string $toAddress): void
    {
        $sql = "UPDATE legal.evidence
                SET current_custodian = :to,
                    pending_custodian = NULL,
                    updated_at = NOW()
                WHERE id = :id";
        $this->query($sql, ['id' => $id, 'to' => strtolower($toAddress)]);
    }

    public function setStatus(string $id, string $status): void
    {
        $this->query("UPDATE legal.evidence SET status = :s, updated_at = NOW() WHERE id = :id", [
            'id' => $id,
            's'  => $status
        ]);
    }
}
