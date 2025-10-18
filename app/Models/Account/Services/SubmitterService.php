<?php namespace Models\Account\Services;

use Models\Account\Brokers\SubmitterBroker;
use Models\Account\Entities\Submitter;
use Zephyrus\Application\Form;

readonly class SubmitterService
{
    public function __construct(
        private SubmitterBroker $broker
    ) {}

    public function findByAddress(string $address): ?Submitter
    {
        return Submitter::build($this->broker->findByAddress($address));
    }

    public function insert(Form $form): Submitter
    {

    }
}
