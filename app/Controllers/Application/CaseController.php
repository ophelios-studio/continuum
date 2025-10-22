<?php namespace Controllers\Application;

use Models\Account\Entities\Actor;
use Models\Legal\Services\LegalCaseService;
use Zephyrus\Application\Flash;
use Zephyrus\Core\Session;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Post;
use Zephyrus\Network\Router\Root;

#[Root("/cases")]
class CaseController extends AppController
{
    #[Get("/")]
    public function index(): Response
    {
        $actor = Actor::build(Session::get('actor'));
        $status = $this->request->getParameter('status'); // OPEN|CLOSED|ARCHIVED
        $search = $this->request->getParameter('q');
        $cases = new LegalCaseService()->findAllForWallet($actor->address, $actor->organization_id, $status, $search);
        return $this->render('application/cases/list', [
            'cases' => $cases,
            'filter_status' => $status,
            'filter_q' => $search
        ]);
    }

    #[Get("/new")]
    public function createForm(): Response
    {
        $actor = Actor::build(Session::get('actor'));
        return $this->render("application/cases/new", [
            'default_jurisdiction' => $actor->jurisdiction
        ]);
    }

    #[Get('/{id}')]
    public function show(string $id): Response
    {
        $actor = Actor::build(Session::get('actor'));
        $service = new LegalCaseService();
        $case = $service->findById($id);
        if (!$case) {
            return $this->redirect("/cases");
        }

        $isParticipant = $service->isParticipant($id, $actor->address);
        $orgId = $actor->organization_id;
        $discoverable =
            ($case->visibility === 'PUBLIC') ||
            ($case->visibility === 'ORG' && $orgId && $orgId === $case->organization_id);

        if (!$isParticipant && !$discoverable) {
            Flash::error("You do not have access to this case.");
            return $this->redirect("/cases");
        }

        return $this->render('application/cases/details', [
            'case' => $case,
            'participants' => $service->findAllParticipants($id),
            'events' => $service->findAllEvents($id)
        ]);
    }

    #[Post("/")]
    public function create(): Response
    {
        $actor = Actor::build(Session::get('actor'));
        $service = new LegalCaseService();
        $service->createCase($this->buildForm(), $actor);
        Flash::success("Case successfully created 🎉. You can now start working on it.");
        return $this->redirect("/cases");
    }
}
