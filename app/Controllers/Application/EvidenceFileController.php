<?php namespace Controllers\Application;

use Models\Account\Entities\Actor;
use Models\Legal\Entities\Evidence;
use Models\Legal\Entities\LegalCase;
use Models\Legal\Services\EvidenceService;
use Models\Legal\Services\LegalCaseService;
use Zephyrus\Application\Flash;
use Zephyrus\Core\Configuration;
use Zephyrus\Core\Session;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Root;

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
}