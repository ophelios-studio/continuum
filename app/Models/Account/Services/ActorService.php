<?php namespace Models\Account\Services;

use Models\Account\Brokers\ActorBroker;
use Models\Account\Entities\Actor;
use Models\Account\Validators\ActorValidator;
use Zephyrus\Application\Form;
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

    public function insert(Form $form): Actor
    {
        ActorValidator::assertInsert($form);
        $new = $form->buildObject();
        $new->address = Session::get('wallet');
        $verificationToken = $this->broker->insert($new);
        // TODO: send email verification
        return $this->findByAddress($new->address);
    }
}
