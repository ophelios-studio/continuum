<?php namespace Controllers\Application;

use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;

class ProfileController extends AppController
{
    #[Get("/me")]
    public function index(): Response
    {
        return $this->render("application/profile");
    }
}
