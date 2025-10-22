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
            'files' => $evs->listFiles($id),
            'events' => $evs->listEvents($id),
            'evidence_registry_addr' => $registry,
            'chain_id' => $chainId,
            'explorer_tx_base' => $explorer
        ]);
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
