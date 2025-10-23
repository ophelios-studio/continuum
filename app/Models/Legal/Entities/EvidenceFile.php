<?php namespace Models\Legal\Entities;

use stdClass;
use Zephyrus\Core\Entity\Entity;

final class EvidenceFile extends Entity
{
    public string $id;
    public string $evidence_id;
    public int $revision_id;
    public string $filename;
    public ?string $mime_type;
    public ?int $byte_size;
    public ?string $sha256_hex;
    public ?string $keccak256_hex;
    public ?string $storage_provider;
    public ?string $storage_cid;
    public ?string $storage_uri;
    public ?stdClass $lit_meta_json;
    public bool $encrypted;
    public string $created_at;
}
