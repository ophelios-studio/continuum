<?php namespace Controllers\Public;

use Controllers\Controller;
use Zephyrus\Application\Flash;
use Zephyrus\Core\Session;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;

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
}
