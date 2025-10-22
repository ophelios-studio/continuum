<?php namespace Models\Legal\Brokers;

use Models\Core\Broker;
use stdClass;

final class LegalCaseBroker extends Broker
{
    public function insert(stdClass $new): int
    {
        $ref = $this->generateRef();
        $sql = "INSERT INTO legal.case(ref_code, title, description, jurisdiction, status, visibility, sensitivity, created_by, organization_id)
        VALUES(:ref_code, :title, :description, :jurisdiction, :status, :visibility, :sensitivity, :created_by, :organization_id) RETURNING id";
        $params = [
            ':ref_code' => $ref,
            ':title' => $new->title,
            ':description' => $new->description ?? '',
            ':jurisdiction' => $new->jurisdiction,
            ':status' => $new->status ?? 'OPEN',
            ':visibility' => $new->visibility ?? 'PRIVATE',
            ':sensitivity' => $new->sensitivity ?? 'NORMAL',
            ':created_by' => strtolower($new->created_by),
            ':organization_id' => $new->organization_id ?? null,
        ];
        return $this->query($sql, $params)->id;
    }

    public function findById(string $id): ?stdClass
    {
        return $this->selectSingle('SELECT * FROM legal.case WHERE id = :id', [':id' => $id]);
    }

    public function findByRef(string $ref): ?stdClass
    {
        return $this->selectSingle('SELECT * FROM legal.case WHERE ref_code = :r', [':r' => $ref]);
    }

    public function listForWallet(string $wallet, ?int $orgId = null, ?string $status = null, ?string $search = null): array
    {
        $wallet = strtolower($wallet);
        $where = [];
        $params = [];

        $where[] = "(c.id IN (SELECT case_id FROM legal.case_participant WHERE address = :me)
                  OR (c.visibility = 'ORG' AND c.organization_id = :orgId)
                  OR (c.visibility = 'PUBLIC'))";
        $params[':me'] = $wallet;
        $params[':orgId'] = $orgId ?? -1;

        if ($status) {
            $where[] = 'c.status = :status';
            $params[':status'] = $status;
        }
        if ($search) {
            $where[] = '(c.title ILIKE :q OR c.ref_code ILIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }

        $sql = 'SELECT c.* FROM legal.case c WHERE ' . implode(' AND ', $where) . ' ORDER BY c.updated_at DESC';
        return $this->select($sql, $params);
    }

    private function generateRef(): string
    {
        $year = gmdate('Y');
        $suffix = strtoupper(substr(hash('xxh3', uniqid('', true)), 0, 6));
        return sprintf('C-%s-%s', $year, $suffix);
    }
}
