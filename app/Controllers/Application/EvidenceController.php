<?php namespace Controllers\Application;

use Models\Account\Entities\Actor;
use Models\Legal\Services\EvidenceService;
use Models\Legal\Services\LegalCaseService;
use Zephyrus\Application\Flash;
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
