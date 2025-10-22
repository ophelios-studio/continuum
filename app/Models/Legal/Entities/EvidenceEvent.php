<?php namespace Models\Legal\Entities;

use stdClass;
use Zephyrus\Core\Entity\Entity;

final class EvidenceEvent extends Entity
{
    public int $id;
    public int $evidence_id;
    public string $actor;
    public string $kind;
    public stdClass $data;
    public string $created_at;
}
