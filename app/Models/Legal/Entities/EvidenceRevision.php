<?php namespace Models\Legal\Entities;

use Zephyrus\Core\Entity\Entity;

final class EvidenceRevision extends Entity
{
    public int $id;
    public string $evidence_id;
    public int $rev_no;
    public string $content_hash;
    public ?string $media_uri;
    public ?string $anchor_tx;
    public ?string $anchored_at;
    public string $created_by;
    public string $created_at;
}
