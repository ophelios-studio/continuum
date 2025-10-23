<?php namespace Models\Legal\Services;

use Models\Account\Entities\Actor;
use Models\Legal\Brokers\EvidenceBroker;
use Models\Legal\Brokers\EvidenceEventBroker;
use Models\Legal\Brokers\EvidenceFileBroker;
use Models\Legal\Brokers\CaseParticipantBroker;
use Models\Legal\Brokers\LegalCaseBroker;
use Models\Legal\ContentHash;
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

    public function anchor(string $evidenceId, string $actorWallet, string $txHash, string $contentHash): void
    {
        $e = $this->findById($evidenceId);
        if (!$e) throw new \InvalidArgumentException('Evidence not found');
        if ($e->status !== 'DRAFT') {
            throw new \RuntimeException('Evidence must be DRAFT before anchoring');
        }
        $this->evidence->updateAnchorInfo(
            $evidenceId,
            $e->evidence_id_hex,
            $contentHash,
            $e->media_uri,
            $txHash
        );
        $this->events->append($evidenceId, $actorWallet, 'EVIDENCE_ANCHORED', [
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

    public function computeContentHash(string $evidenceId): string
    {
        $e = $this->findById($evidenceId);
        if (!$e) {
            throw new \InvalidArgumentException('Evidence not found');
        }
        $files = $this->listFiles($evidenceId);
        $manifest = $this->buildEvidenceManifest($e, $files);
        return ContentHash::compute($manifest);
    }

    private function buildEvidenceManifest(Evidence $e, array $files): array
    {
        return [
            'version' => 1,
            'case_id' => $e->case_id,
            'evidence_id' => $e->id,
            'evidence_id_hex' => strtolower($e->evidence_id_hex ?? ''),
            'title' => $e->title,
            'kind' => $e->kind,
            'jurisdiction' => $e->jurisdiction,
            'description' => ($e->description ?? ''),
            'submitter_address' => strtolower($e->submitter_address ?? ''),
            'current_custodian' => strtolower($e->current_custodian ?? ''),
            'files' => array_map(function (EvidenceFile $f) {
                return [
                    'filename' => (string)$f->filename,
                    'mime' => (string)($f->mime_type ?? ''),
                    'size' => (int)($f->byte_size ?? 0),
                    'sha256' => strtolower($f->sha256_hex ?? ''),
                    'keccak256' => strtolower($f->keccak256_hex ?? ''),
                    'provider' => (string)($f->storage_provider ?? ''),
                    'cid' => (string)($f->storage_cid ?? ''),
                    'uri' => (string)($f->storage_uri ?? ''),
                    'encrypted' => (bool)($f->encrypted ?? false),
                ];
            }, $files),
        ];
    }
}