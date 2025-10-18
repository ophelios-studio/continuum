<?php namespace Models\Account\Services;

use Models\Account\Brokers\ActorBroker;
use Models\Account\Entities\Actor;
use Zephyrus\Application\Form;

readonly class ActorService
{
    public function __construct(
        private ActorBroker $broker
    ) {}

    public function findByAddress(string $address): ?Actor
    {
        return Actor::build($this->broker->findByAddress($address));
    }

    public function insert(Form $form): Actor
    {

    }
}
