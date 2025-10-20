<?php namespace Controllers\Public;

use Controllers\Controller;
use Models\Account\Services\ActorService;
use Models\Core\Application;
use Zephyrus\Application\Flash;
use Zephyrus\Core\Session;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Post;
use Zephyrus\Utilities\MaskFormat;

class SignupController extends Controller
{
    #[Get("/signup")]
    public function signupForm(): Response
    {
        if (!is_null(Session::get('actor'))) {
            Flash::error(localize("accounts.errors.already_signed_up"));
            return $this->redirect("/");
        }
        if (is_null(Session::get('wallet'))) {
            return $this->redirect("/login");
        }
        return $this->render("public/signup", [
            'wallet' => Session::get('wallet'),
            'ens_avatar' => Session::get('ens_avatar'),
            'ens_name' => Session::get('ens_name')
        ]);
    }

    #[Post("/signup")]
    public function signup(): Response
    {
        if (!is_null(Session::get('actor'))) {
            Flash::error(localize("accounts.errors.already_signed_up"));
            return $this->redirect("/");
        }
        if (is_null(Session::get('wallet'))) {
            return $this->redirect("/login");
        }
        $actor = new ActorService()->insert($this->buildForm());
        Application::getInstance()->getSession()->restart();
        Flash::success(localize("accounts.success.created", [
            'email' => MaskFormat::email($actor->email),
            'wallet' => format('wallet', $actor->address)
        ]));
        return $this->redirect("/login");
    }

    #[Get("/signup-activation/{code}")]
    public function signupActivation(string $code): Response
    {
        $service = new ActorService();
        $actor = $service->authenticateByActivationCode($code);
        if (is_null($actor)) {
            Application::getInstance()->getSession()->restart();
            Flash::error(localize("accounts.errors.activation_invalid"));
            return $this->redirect("/login");
        }
        $service->activate($actor);
        Application::getInstance()->getSession()->restart();
        Flash::success(localize("accounts.success.activation", ['wallet' => format('wallet', $actor->address)]));
        return $this->redirect("/login");
    }
}
