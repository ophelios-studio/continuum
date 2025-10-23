<?php namespace Models\Legal\Entities;

use Models\Legal\Brokers\EvidenceRevisionBroker;
use Zephyrus\Core\Entity\Entity;

final class Evidence extends Entity
{
    public string $id;
    public string $case_id;
    public string $title;
    public string $kind;
    public ?string $description;
    public string $jurisdiction;
    public ?string $external_uri;
    public ?string $physical_tag;
    public ?string $serial;
    public ?string $content_hash;
    public ?string $media_uri;
    public ?string $evidence_id_hex;
    public ?string $anchor_tx;
    public ?string $anchored_at;
    public string $submitter_address;
    public string $current_custodian;
    public ?string $pending_custodian;
    public string $status;
    public string $created_at;
    public string $updated_at;
    private ?EvidenceRevision $currentRevision;

    public function getCurrentRevision(): ?EvidenceRevision
    {
        if (is_null($this->currentRevision)) {
            $this->currentRevision = EvidenceRevision::build(new EvidenceRevisionBroker()->findCurrentForEvidence($this->id));
        }
        return $this->currentRevision;
    }
}
