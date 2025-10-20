<?php namespace Controllers\Application;

use Controllers\Controller;
use Models\Account\Entities\Actor;
use Models\Core\Application;
use Zephyrus\Application\Flash;
use Zephyrus\Core\Session;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Authorize;
use Zephyrus\Utilities\MaskFormat;

#[Authorize("authenticated")]
abstract class AppController extends Controller
{
    public function before(): ?Response
    {
        if (is_null(Session::get('actor'))) {
            return $this->redirect("/signup");
        }
        $actor = Actor::build(Session::get('actor'));
        if ($actor->verification_token) {
            Application::getInstance()->getSession()->restart();
            Flash::error(localize("accounts.errors.not_verified", ['email' => MaskFormat::email($actor->email)]));
            return $this->redirect("/login");
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
