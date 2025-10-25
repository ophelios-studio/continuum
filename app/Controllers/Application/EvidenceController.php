<?php namespace Controllers\Application;

use Models\Account\Entities\Actor;
use Models\Legal\Services\EvidenceService;
use Models\Legal\Services\LegalCaseService;
use Zephyrus\Application\Flash;
use Zephyrus\Core\Configuration;
use Zephyrus\Core\Session;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Post;
use Zephyrus\Network\Router\Root;

#[Root("/cases/{caseId}/evidences")]
class EvidenceController extends AppController
{
    #[Get("/new")]
    public function createForm(string $caseId): Response
    {
        $actor = Actor::build(Session::get('actor'));
        $cases = new LegalCaseService();
        $case = $cases->findById($caseId);
        if (!$case) {
            Flash::error("The specified case was not found or you don't have access to it.");
            return $this->redirect("/cases");
        }

        // Access: participant OR discoverable (PUBLIC/ORG)
        $isParticipant = $cases->isParticipant($caseId, $actor->address);
        $orgId = $actor->organization_id;
        $discoverable =
            ($case->visibility === 'PUBLIC') ||
            ($case->visibility === 'ORG' && $orgId && $orgId === $case->organization_id);

        if (!$isParticipant && !$discoverable) {
            Flash::error("You do not have access to this case.");
            return $this->redirect("/cases");
        }
        $kinds = [
            'DIGITAL_DUMP' => 'Digital dump',
            'FILESET' => 'Fileset / Document bundle',
            'PHOTO' => 'Photo',
            'VIDEO' => 'Video',
            'AUDIO' => 'Audio',
            'PHYSICAL_ITEM' => 'Physical item',
            'OTHER' => 'Other',
        ];
        return $this->render("application/evidences/new", [
            'case' => $case,
            'default_jurisdiction' => $actor->jurisdiction,
            'kinds' => $kinds
        ]);
    }

    #[Get("/{id}/custody")]
    public function custodyForm(string $caseId, string $id): Response
    {
        $actor = Actor::build(Session::get('actor'));
        $cases = new LegalCaseService();
        $case = $cases->findById($caseId);
        if (!$case) {
            Flash::error("The specified case was not found or you don't have access to it.");
            return $this->redirect("/cases");
        }

        $isParticipant = $cases->isParticipant($caseId, $actor->address);
        $orgId = $actor->organization_id;
        $discoverable =
            ($case->visibility === 'PUBLIC') ||
            ($case->visibility === 'ORG' && $orgId && $orgId === $case->organization_id);

        if (!$isParticipant && !$discoverable) {
            Flash::error("You do not have access to this case.");
            return $this->redirect("/cases");
        }

        $evs = new EvidenceService();
        $evidence = $evs->findById($id);
        if (!$evidence) {
            Flash::error("Evidence not found.");
            return $this->redirect('/cases/' . $caseId);
        }

        return $this->render("application/evidences/custody", [
            'case' => $case,
            'evidence' => $evidence
        ]);
    }

    #[Get('/{id}')]
    public function show(string $caseId, string $id): Response
    {
        $actor = Actor::build(Session::get('actor'));
        $cases = new LegalCaseService();
        $case = $cases->findById($caseId);
        if (!$case) {
            Flash::error("The specified case was not found or you don't have access to it.");
            return $this->redirect("/cases");
        }

        $evs = new EvidenceService();
        $cases = new LegalCaseService();

        $evidence = $evs->findById($id);
        if (!$evidence) {
            Flash::error("Evidence not found.");
            return $this->redirect('/cases/' . $caseId);
        }

        $isParticipant = $cases->isParticipant($case->id, $actor->address);
        $orgId = $actor->organization_id;
        $discoverable = ($case->visibility === 'PUBLIC') ||
            ($case->visibility === 'ORG' && $orgId && $orgId === $case->organization_id);

        if (!$isParticipant && !$discoverable) {
            Flash::error("You do not have access to this evidence.");
            return $this->redirect('/cases');
        }

        $cfg = Configuration::read('services')['web3'] ?? [];
        $chainId = $cfg['chain_id'] ?? 11155111;
        $registry = $cfg['evidence_registry_addr'] ?? '';
        $explorer = $cfg['explorer_tx_base'] ?? 'https://sepolia.etherscan.io/tx/';

        return $this->render('application/evidences/details', [
            'evidence' => $evidence,
            'case' => $case,
            'files' => $evs->listFiles($id),
            'events' => $evs->listEvents($id),
            'evidence_registry_addr' => $registry,
            'chain_id' => $chainId,
            'explorer_tx_base' => $explorer
        ]);
    }

    #[Post('/{id}/anchor')]
    public function anchor(string $caseId, string $id): Response
    {
        $actor = Actor::build(Session::get('actor'));
        $cases = new LegalCaseService();
        $case = $cases->findById($caseId);
        if (!$case) {
            Flash::error("The specified case was not found or you don't have access to it.");
            return $this->redirect("/cases");
        }

        $service = new EvidenceService();
        $evidence = $service->findById((int)$id);
        if (!$evidence) {
            return $this->jsonError(404, 'Evidence not found');
        }

        $cases = new LegalCaseService();
        if (!$cases->isParticipant((string)$evidence->case_id, $actor->address)) {
            return $this->jsonError(403, 'Permission denied');
        }

        $payload = $this->request->getParameters();
        $txHash = strtolower(trim((string)($payload['txHash'] ?? '')));
        $evidenceIdHex = strtolower(trim((string)($payload['evidenceIdHex'] ?? '')));
        $contentHash = strtolower(trim((string)($payload['contentHashHex'] ?? '')));
        $mediaUri = trim((string)($payload['mediaUri'] ?? ''));

        if (!preg_match('/^0x[0-9a-f]{64}$/', $evidenceIdHex)) {
            return $this->jsonError(400, 'Invalid evidenceId (bytes32 hex required)');
        }
        if (!preg_match('/^0x[0-9a-f]{64}$/', $contentHash)) {
            return $this->jsonError(400, 'Invalid contentHash (bytes32 hex required)');
        }
        if (!preg_match('/^0x[0-9a-f]{64}$/', $txHash)) {
            return $this->jsonError(400, 'Invalid txHash');
        }

        if (!empty($evidence->anchor_tx)) {
            return $this->jsonError(409, 'Evidence is already anchored');
        }

        $service->persistAnchor(
            $id,
            $actor->address,
            $evidenceIdHex,
            $contentHash,
            $mediaUri !== '' ? $mediaUri : null,
            $txHash
        );

        Flash::success(localize('evidence.success.anchored', [
            'title' => $evidence->title,
            'tx' => $txHash
        ]));

        return $this->json(['ok' => true, 'tx' => $txHash]);
    }

    #[Post("/")]
    public function create(string $caseId): Response
    {
        $actor = Actor::build(Session::get('actor'));
        $cases = new LegalCaseService();
        $service = new EvidenceService();

        $case = $cases->findById($caseId);
        if (!$case) {
            Flash::error("The specified case was not found or you don't have access to it.");
            return $this->redirect("/cases");
        }
        if (!$cases->isParticipant($caseId, $actor->address)) {
            Flash::error("You are not a participant in this case.");
            return $this->redirect("/cases/$caseId");
        }

        $evidence = $service->create($this->buildForm(), $actor, $caseId);
        Flash::success(localize('evidences.success.created', [
            'title' => $evidence->title,
            'case_ref' => $case->ref_code
        ]));
        return $this->redirect("/cases/$caseId");
    }
}
