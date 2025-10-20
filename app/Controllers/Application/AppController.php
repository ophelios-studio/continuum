<?php namespace Controllers\Application;

use Controllers\Controller;
use Zephyrus\Core\Session;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Authorize;

#[Authorize("authenticated")]
abstract class AppController extends Controller
{
    public function before(): ?Response
    {
        if (is_null(Session::get('actor'))) {
            return $this->redirect("/signup");
        }
        return parent::before();
    }

    public function render(string $page, array $args = []): Response
    {
        $args = array_merge($args, [
            'wallet' => Session::get('wallet'),
            'ens_avatar' => Session::get('ens_avatar'),
            'ens_name' => Session::get('ens_name')
        ]);
        return parent::render($page, $args);
    }
}
