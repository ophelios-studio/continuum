<?php namespace Models\Legal\Entities;

use Zephyrus\Core\Entity\Entity;

final class CaseParticipant extends Entity
{
    public int $id;
    public string $case_id;
    public string $address; // wallet
    public string $role; // OWNER|EDITOR|VIEWER
    public ?int $org_id;
    public string $invited_at;
    public ?string $accepted_at;
}
