<?php namespace Models\Legal\Brokers;

use Models\Account\Entities\Actor;
use Models\Core\Broker;
use stdClass;

final class EvidenceBroker extends Broker
{
    public function insert(stdClass $new, Actor $submitter): string
    {
        $evidenceIdHex = $this->generateEvidenceIdHex();
        $sql = "INSERT INTO legal.evidence(
                    case_id, title, kind, description, jurisdiction, external_uri, physical_tag, serial,
                    content_hash, media_uri, evidence_id_hex, anchor_tx, anchored_at,
                    submitter_address, current_custodian, pending_custodian, status
                )  VALUES(:case_id, :title, :kind, :description, :jurisdiction, :external_uri, :physical_tag, :serial, 
                       NULL, NULL, :evidence_id_hex, NULL, NULL, :submitter_address, :current_custodian, NULL, :status)
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
            'evidence_id_hex' => strtolower($evidenceIdHex),
            'submitter_address' => strtolower($submitter->address),
            'current_custodian' => strtolower($submitter->address),
            'status' => $new->status ?? 'DRAFT',
        ];
        $id = $this->query($sql, $params)->id;
        new EvidenceRevisionBroker()->insert($id, $submitter->address);
        return $id;
    }

    public function findById(string $id): ?stdClass
    {
        return $this->selectSingle('SELECT * FROM legal.evidence WHERE id = :id', ['id' => $id]);
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

    private function generateEvidenceIdHex(): string
    {
        return '0x' . bin2hex(random_bytes(32));
    }
}
