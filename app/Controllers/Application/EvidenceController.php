<?php namespace Controllers\Application;

use Models\Legal\Services\LegalCaseService;
use Zephyrus\Application\Flash;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Root;

#[Root("/cases/{case_id}/evidences")]
class EvidenceController extends AppController
{
    #[Get("/new")]
    public function createFrom(string $caseId): Response
    {
        $case = new LegalCaseService()->findById($caseId);
        if (!$case) {
            Flash::error("The specified case was not found or you don't have access to it.");
            return $this->redirect("/cases");
        }
        return $this->render("application/evidences/new", [
            'case' => $case
        ]);
    }
}
