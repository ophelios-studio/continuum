<?php namespace Controllers\Application;

use InvalidArgumentException;
use Models\Account\Entities\Actor;
use Models\Legal\Entities\Evidence;
use Models\Legal\Entities\LegalCase;
use Models\Legal\Services\EvidenceFileService;
use Models\Legal\Services\EvidenceService;
use Models\Legal\Services\LegalCaseService;
use Throwable;
use Zephyrus\Application\Flash;
use Zephyrus\Core\Configuration;
use Zephyrus\Core\Session;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Post;
use Zephyrus\Network\Router\Root;
use Zephyrus\Utilities\Uploader\FileUpload;

#[Root("/cases/{caseId}/evidences/{evidenceId}")]
class EvidenceFileController extends AppController
{
    private Actor $actor;
    private LegalCaseService $cases;
    private LegalCase $case;
    private EvidenceService $evidences;
    private Evidence $evidence;

    public function before(): ?Response
    {
        $response = parent::before();
        if ($response) {
            return $response;
        }

        $this->actor = Actor::build(Session::get('actor'));
        $this->cases = new LegalCaseService();
        $caseId = $this->request->getArgument('caseId');
        $this->case = $this->cases->findById($caseId);
        if (is_null($this->case)) {
            Flash::error("The specified case was not found or you don't have access to it.");
            return $this->redirect("/cases");
        }

        $this->evidences = new EvidenceService();
        $evidenceId = $this->request->getArgument('evidenceId');
        $this->evidence = $this->evidences->findById($evidenceId);
        if (is_null($this->evidence)) {
            Flash::error("Evidence not found.");
            return $this->redirect('/cases/' . $caseId);
        }

        $isParticipant = $this->cases->isParticipant($this->case->id, $this->actor->address);
        $orgId = $this->actor->organization_id;
        $discoverable = ($this->case->visibility === 'PUBLIC')
            || ($this->case->visibility === 'ORG' && $orgId && $orgId === $this->case->organization_id);
        if (!$isParticipant && !$discoverable) {
            Flash::error("You do not have access to this evidence.");
            return $this->redirect('/cases');
        }

        return null;
    }

    #[Get("/files/{fileId}/meta")]
    public function meta(string $caseId, string $evidenceId, string $fileId): Response
    {
        $file = new EvidenceFileService()->findById($fileId);
        if (!$file || $file->evidence_id !== $evidenceId) {
            return $this->jsonError(404, 'File not found');
        }

        $lit = $file->lit_meta_json;
        if (!isset($lit->dataToEncryptHash)) {
            return $this->jsonError(400, 'Missing Lit v7 metadata');
        }

        return $this->json([
            'filename' => $file->filename,
            'mime_type' => $file->mime_type ?? 'application/octet-stream',
            'byte_size' => $file->byte_size ?? 0,
            'storage' => [
                'provider' => $file->storage_provider,
                'cid' => $file->storage_cid,
                'uri' => $file->storage_uri,
            ],
            'lit' => [
                'dataToEncryptHash' => $lit->dataToEncryptHash,
                'evmContractConditions' => $lit->evmContractConditions ?? null,
                'accessControlConditions' => $lit->accessControlConditions ?? null,
                'unifiedAccessControlConditions' => $lit->unifiedAccessControlConditions ?? null,
                'chain' => $lit->chain ?? 'sepolia',
            ]
        ]);
    }

    #[Get("/files/{fileId}/download")]
    public function downloadFile(string $caseId, string $evidenceId, string $fileId): Response
    {
        $file = new EvidenceFileService()->findById($fileId);
        if (!$file || $file->evidence_id !== $evidenceId) {
            return $this->jsonError(404, 'File not found');
        }
        $path = ROOT_DIR . '/storage/' . $file->storage_cid;
        if (!is_readable($path)) {
            return $this->jsonError(404, 'Cipher not available');
        }
        return $this->downloadInline($path, $file->filename);
    }

    #[Get("/files/new")]
    public function form(string $caseId, string $evidenceId): Response
    {
        return $this->render("application/evidences/upload", [
            'case' => $this->case,
            'evidence' => $this->evidence,
            'revision_id' => 1,
            'evidence_registry_addr' => Configuration::read('services')["web3"]["evidence_registry_addr"],
            'evidence_id_hex' => $this->evidence->evidence_id_hex,
            'chain_id' => Configuration::read('services')["web3"]["chain_id"],
        ]);
    }

    #[Post("/files")]
    public function upload(string $caseId, string $evidenceId): Response
    {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return $this->jsonError(400, 'Missing file or upload error');
        }
        $tmpPath = $_FILES['file']['tmp_name'] ?? null;
        $origName = $_FILES['file']['name'] ?? 'file.enc';
        $mime = $_FILES['file']['type'] ?? 'application/octet-stream';
        $size = (int)($_FILES['file']['size'] ?? 0);

        if (!$tmpPath || !is_uploaded_file($tmpPath)) {
            return $this->jsonError(400, 'Invalid upload stream');
        }

        $upload = $this->decodeJsonField($this->request->getParameter('upload'), 'upload', true);
        $lit = $this->decodeJsonField($this->request->getParameter('lit'),    'lit',    true);
        if (empty($lit['dataToEncryptHash'])) {
            return $this->jsonError(400, 'lit.dataToEncryptHash is required (v7)');
        }
        if (empty($lit['evmContractConditions']) && empty($lit['accessControlConditions']) && empty($lit['unifiedAccessControlConditions'])) {
            return $this->jsonError(400, 'Missing Lit access control conditions (v7)');
        }
        $lit = [
            'evmContractConditions' => $lit['evmContractConditions'],
            'accessControlConditions' => $lit['accessControlConditions'],
            'unifiedAccessControlConditions' => $lit['unifiedAccessControlConditions'],
            'dataToEncryptHash' => $lit['dataToEncryptHash'],
            'chain' => $lit['chain'] ?? 'sepolia'
        ];

        $maxBytes = FileUpload::getMaxUploadSize() * 1024 * 1024;
        if ($size > $maxBytes) {
            return $this->jsonError(413, 'File too large');
        }

        $safeName = trim(basename($upload['filename'] ?? $origName));
        if ($safeName === '') $safeName = 'file.enc';

        $uploadInfo = [
            'tmp_path' => $tmpPath,
            'filename' => $safeName,
            'mime'     => $upload['mime'] ?? $mime,
            'size'     => $size, // authoritative
        ];

        try {
            $service = new EvidenceFileService();
            $file = $service->addEncryptedFile($evidenceId, $this->actor, $uploadInfo, $lit);
            $out = [
                'id'         => $file->id,
                'filename'   => $file->filename,
                'mime_type'  => $file->mime_type,
                'byte_size'  => $file->byte_size,
                'sha256'     => $file->sha256_hex,
                'keccak256'  => $file->keccak256_hex,
                'storage'    => [
                    'provider' => $file->storage_provider,
                    'cid'      => $file->storage_cid,
                    'uri'      => $file->storage_uri,
                ],
                'encrypted'  => $file->encrypted,
                'created_at' => $file->created_at
            ];
            Flash::success("File uploaded successfully.");
            return $this->json($out);
        } catch (Throwable $e) {
            return $this->jsonError(400, $e->getMessage());
        }
    }

    private function decodeJsonField(mixed $value, string $label, bool $required): ?array
    {
        if ($value === null || $value === '') {
            if ($required) {
                throw new InvalidArgumentException("$label is required");
            }
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException("$label must be valid JSON");
        }
        return $decoded;
    }
}