<?php namespace Controllers\Public;

use Controllers\Controller;
use Zephyrus\Core\Session;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;

class LoginController extends Controller
{
    #[Get("/login")]
    public function index(): Response
    {
        return $this->render("public/login");
    }

    #[Get("/logout")]
    public function logout(): Response
    {
        Session::destroy();
        return $this->redirect("/login");
    }
}
