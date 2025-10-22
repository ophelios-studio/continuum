<?php namespace Models\Legal\Brokers;

use Models\Core\Broker;

final class CaseEventBroker extends Broker
{
    public function add(string $caseId, string $actor, string $kind, array $data = []): int
    {
        $sql = "INSERT INTO legal.case_event (case_id, actor, kind, data, created_at)
                VALUES (:case_id, :actor, :kind, :data, NOW())
                RETURNING id";
        return $this->query($sql, [
            'case_id' => $caseId,
            'actor' => strtolower($actor),
            'kind' => $kind,
            'data' => json_encode($data, JSON_UNESCAPED_SLASHES),
        ])->id;
    }

    public function list(string $caseId, int $limit = 100, int $offset = 0): array
    {
        $sql = 'SELECT * FROM legal.case_event WHERE case_id = :id ORDER BY created_at DESC, id DESC LIMIT :lim OFFSET :off';
        return $this->select($sql, ['id' => $caseId, 'lim' => $limit, 'off' => $offset]);
    }
}