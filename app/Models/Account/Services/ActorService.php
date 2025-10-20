<?php namespace Models\Account\Services;

use Models\Account\Brokers\ActorBroker;
use Models\Account\Entities\Actor;
use Models\Account\Validators\ActorValidator;
use Models\Core\Application;
use Pulsar\Mailer\Mailer;
use Zephyrus\Application\Form;
use Zephyrus\Core\Configuration;
use Zephyrus\Core\Session;

readonly class ActorService
{
    public function __construct(
        private ActorBroker $broker = new ActorBroker()
    ) {}

    public function findByAddress(string $address): ?Actor
    {
        return Actor::build($this->broker->findByAddress($address));
    }

    public function authenticateByActivationCode(string $code): ?Actor
    {
        return Actor::build($this->broker->findByActivationCode($code));
    }

    public function activate(Actor $actor): void
    {
        $this->broker->activate($actor);
    }

    public function insert(Form $form): Actor
    {
        ActorValidator::assertInsert($form);
        $new = $form->buildObject();
        $new->address = Session::get('wallet');
        $confirmationToken = $this->broker->insert($new);
        $actor = $this->findByAddress($new->address);
        $this->sendActivationEmail($actor, $confirmationToken);
        return $actor;
    }

    private function sendActivationEmail(Actor $actor, string $confirmationToken): void
    {
        $baseUrl = Application::getInstance()->getRequest()->getUrl()->getBaseUrl();
        $mailer = new Mailer();
        $mailer->setSubject(localize("accounts.emails.activation.subject", ['fullname' => $actor->getFullName()]));
        $mailer->setTemplate("emails/activation", [
            'url' => $baseUrl . "/signup-activation/" . $confirmationToken,
            'actor' => $actor,
            'contact_email' => Configuration::getApplication('contact_email')
        ]);
        $mailer->addInlineAttachment(ROOT_DIR . '/public/assets/images/logo-horizontal.png', "logo.png");
        $mailer->addRecipient($actor->email, $actor->getFullName());
        $mailer->send();
    }
}
