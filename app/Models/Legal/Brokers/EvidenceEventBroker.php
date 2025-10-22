<?php namespace Models\Legal\Brokers;

use Models\Core\Broker;

final class EvidenceEventBroker extends Broker
{
    public function append(string $evidenceId, string $actorWallet, string $kind, array $data = []): string
    {
        $sql = "INSERT INTO legal.evidence_event (evidence_id, actor, kind, data)
                VALUES (:eid, :actor, :kind, :data) RETURNING id";

        return $this->query($sql, [
            'eid' => $evidenceId,
            'actor' => strtolower($actorWallet),
            'kind' => $kind,
            'data' => json_encode($data, JSON_UNESCAPED_SLASHES),
        ])->id;
    }

    public function listForEvidence(string $evidenceId): array
    {
        return $this->select(
            'SELECT * FROM legal.evidence_event WHERE evidence_id = :eid ORDER BY created_at DESC',
            ['eid' => $evidenceId]
        );
    }
}
