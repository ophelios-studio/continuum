<?php namespace Controllers\Application;

use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;

class DashboardController extends AppController
{
    #[Get("/")]
    public function index(): Response
    {
        return $this->render("application/dashboard");
    }
}
