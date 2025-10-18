<?php namespace Controllers\Application;

use Controllers\Controller;
use Zephyrus\Core\Session;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Authorize;

#[Authorize("authenticated")]
abstract class AppController extends Controller
{
    public function render(string $page, array $args = []): Response
    {
        $args = array_merge($args, [
            'wallet' => Session::get('wallet')
        ]);
        return parent::render($page, $args);
    }
}
