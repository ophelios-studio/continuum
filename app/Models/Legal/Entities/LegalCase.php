<?php namespace Models\Legal\Entities;

use Zephyrus\Core\Entity\Entity;

final class LegalCase extends Entity
{
    public string $id;
    public string $ref_code;
    public string $title;
    public string $description;
    public string $jurisdiction;
    public string $status;       // OPEN|CLOSED|ARCHIVED
    public string $visibility;   // PRIVATE|ORG|PUBLIC
    public string $sensitivity;  // NORMAL|SEALED
    public string $created_by;   // wallet
    public ?int $organization_id;
    public string $created_at;
    public string $updated_at;
}
