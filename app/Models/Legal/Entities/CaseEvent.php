<?php namespace Models\Legal\Entities;

use Zephyrus\Core\Entity\Entity;

final class CaseEvent extends Entity
{
    public int $id;
    public int $case_id;
    public string $actor; // wallet
    public string $kind;  // CASE_CREATED | CASE_UPDATED | MEMBER_ADDED | STATUS_CHANGED | NOTE
    public array $data = [];
    public string $created_at;
}