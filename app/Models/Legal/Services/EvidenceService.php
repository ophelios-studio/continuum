<?php namespace Models\Legal\Services;

use Models\Account\Entities\Actor;
use Models\Legal\Brokers\EvidenceBroker;
use Models\Legal\Brokers\EvidenceEventBroker;
use Models\Legal\Brokers\EvidenceFileBroker;
use Models\Legal\Brokers\CaseParticipantBroker;
use Models\Legal\Brokers\LegalCaseBroker;
use Models\Legal\Entities\Evidence;
use Models\Legal\Entities\EvidenceEvent;
use Models\Legal\Entities\EvidenceFile;
use Models\Legal\Validators\EvidenceValidator;
use Zephyrus\Application\Form;

final readonly class EvidenceService
{
    public function __construct(
        private EvidenceBroker $evidence = new EvidenceBroker(),
        private EvidenceFileBroker $files = new EvidenceFileBroker(),
        private EvidenceEventBroker $events = new EvidenceEventBroker(),
        private LegalCaseBroker $cases = new LegalCaseBroker(),
        private CaseParticipantBroker $participants = new CaseParticipantBroker()
    ) {}

    public function findById(string $id): ?Evidence
    {
        return Evidence::build($this->evidence->findById($id));
    }

    /**
     * @return array<Evidence>
     */
    public function listForCase(string $caseId, ?string $search = null, ?string $kind = null, ?string $status = null): array
    {
        return Evidence::buildArray($this->evidence->listForCase($caseId, $search, $kind, $status));
    }

    public function create(Form $form, Actor $creator, string $caseId): Evidence
    {
        $case = $this->cases->findById($caseId);
        if (!$case) {
            throw new \InvalidArgumentException('Case not found');
        }
        if (!$this->participants->isParticipant($caseId, $creator->address)) {
            throw new \RuntimeException('Not a participant of this case');
        }

        EvidenceValidator::assertInsert($form);
        $payload = $form->buildObject();
        $payload->case_id = $caseId;
        $id = $this->evidence->insert($payload, $creator);
        $ev = $this->findById($id);
        $this->events->append($id, $creator->address, 'EVIDENCE_CREATED', [
            'title' => $ev->title,
            'kind' => $ev->kind,
            'jurisdiction' => $ev->jurisdiction
        ]);
        return $ev;
    }

    public function attachFile(string $evidenceId, string $actorWallet, array $meta): EvidenceFile
    {
        $e = $this->findById($evidenceId);
        if (!$e) {
            throw new \InvalidArgumentException('Evidence not found');
        }
        $new = (object) [
            'evidence_id' => $evidenceId,
            'filename' => $meta['filename'] ?? 'file',
            'mime_type' => $meta['mime_type'] ?? null,
            'byte_size' => $meta['byte_size'] ?? null,
            'sha256_hex' => $meta['sha256_hex'] ?? null,
            'keccak256_hex' => $meta['keccak256_hex'] ?? null,
            'storage_provider' => $meta['storage_provider'] ?? null,
            'storage_cid' => $meta['storage_cid'] ?? null,
            'storage_uri' => $meta['storage_uri'] ?? null,
            'encrypted' => $meta['encrypted'] ?? false,
        ];
        $fileId = $this->files->insert($new);
        $this->events->append($evidenceId, $actorWallet, 'FILE_ATTACHED', [
            'file_id' => $fileId,
            'filename' => $new->filename
        ]);
        return EvidenceFile::build($this->files->findById($fileId));
    }

    public function removeFile(string $evidenceId, string $fileId, string $actorWallet): void
    {
        $this->files->deleteForEvidence($evidenceId, $fileId);
        $this->events->append($evidenceId, $actorWallet, 'FILE_REMOVED', [
            'file_id' => $fileId
        ]);
    }

    public function markReady(string $evidenceId, string $actorWallet): void
    {
        $this->evidence->setStatus($evidenceId, 'READY');
        $this->events->append($evidenceId, $actorWallet, 'EVIDENCE_READY');
    }

    public function persistAnchor(
        string $evidenceId,
        string $actorWallet,
        string $evidenceIdHex,
        string $contentHash,
        ?string $mediaUri,
        string $txHash
    ): void {
        $this->evidence->updateAnchorInfo($evidenceId, $evidenceIdHex, $contentHash, $mediaUri, $txHash);
        $this->events->append($evidenceId, $actorWallet, 'ANCHORED', [
            'evidence_id_hex' => strtolower($evidenceIdHex),
            'content_hash' => strtolower($contentHash),
            'media_uri' => $mediaUri,
            'tx' => strtolower($txHash)
        ]);
    }

    public function initiateTransfer(string $evidenceId, string $fromWallet, string $toWallet, ?string $purpose = null, ?string $expectedReturnIso = null, ?string $offchainContextHash = null): void
    {
        $this->evidence->setPendingCustodian($evidenceId, $toWallet);
        $data = [
            'from' => strtolower($fromWallet),
            'to' => strtolower($toWallet),
        ];
        if ($purpose) $data['purpose'] = $purpose;
        if ($expectedReturnIso) $data['expected_return_at'] = $expectedReturnIso;
        if ($offchainContextHash) $data['offchain_context_hash'] = $offchainContextHash;
        $this->events->append($evidenceId, $fromWallet, 'TRANSFER_INITIATED', $data);
    }

    public function acceptTransfer(string $evidenceId, string $toWallet): void
    {
        $this->evidence->acceptCustody($evidenceId, $toWallet);
        $this->events->append($evidenceId, $toWallet, 'TRANSFER_ACCEPTED', [
            'to' => strtolower($toWallet)
        ]);
    }

    public function returnCustody(string $evidenceId, string $fromWallet, string $toWallet, ?string $note = null): void
    {
        $this->evidence->returnCustody($evidenceId, $toWallet);
        $data = [
            'from' => strtolower($fromWallet),
            'to'   => strtolower($toWallet)
        ];
        if ($note) $data['note'] = $note;

        $this->events->append($evidenceId, $fromWallet, 'RETURNED', $data);
    }

    /**
     * @return array<EvidenceFile>
     */
    public function listFiles(string $evidenceId): array
    {
        return EvidenceFile::buildArray($this->files->listForEvidence($evidenceId));
    }

    /**
     * @return array<EvidenceEvent>
     */
    public function listEvents(string $evidenceId): array
    {
        return EvidenceEvent::buildArray($this->events->listForEvidence($evidenceId));
    }
}
