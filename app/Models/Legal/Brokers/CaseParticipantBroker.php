<?php namespace Models\Legal\Brokers;

use Models\Core\Broker;

final class CaseParticipantBroker extends Broker
{
    public function add(string $caseId, string $address, string $role, ?int $orgId = null, bool $accepted = true): string
    {
        $sql = "INSERT INTO legal.case_participant (case_id, address, role, org_id, invited_at, accepted_at)
                VALUES (:case_id, :address, :role, :org_id, NOW(), :accepted_at)
                RETURNING id";
        $params = [
            'case_id' => $caseId,
            'address' => strtolower($address),
            'role' => $role,
            'org_id' => $orgId,
            'accepted_at' => $accepted ? date('c') : null,
        ];
        return $this->query($sql, $params)->id;
    }

    public function list(string $caseId): array
    {
        return $this->select('SELECT * FROM legal.case_participant WHERE case_id = :id ORDER BY id', ['id' => $caseId]);
    }

    public function changeRole(string $caseId, string $address, string $role): void
    {
        $this->query(
            'UPDATE legal.case_participant SET role = :role WHERE case_id = :id AND address = :addr',
            ['role' => $role, 'id' => $caseId, 'addr' => strtolower($address)]
        );
    }

    public function remove(string $caseId, string $address): void
    {
        $this->query(
            'DELETE FROM legal.case_participant WHERE case_id = :id AND address = :addr',
            ['id' => $caseId, 'addr' => strtolower($address)]
        );
    }

    public function isParticipant(string $caseId, string $address): bool
    {
        $row = $this->selectSingle(
            'SELECT 1 AS ok FROM legal.case_participant WHERE case_id = :id AND address = :addr',
            ['id' => $caseId, 'addr' => strtolower($address)]
        );
        return !is_null($row);
    }

    public function roleOf(string $caseId, string $address): ?string
    {
        $row = $this->selectSingle(
            'SELECT role FROM legal.case_participant WHERE case_id = :id AND address = :addr',
            ['id' => $caseId, 'addr' => strtolower($address)]
        );
        return $row->role ?? null;
    }
}
