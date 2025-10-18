<?php namespace Models\Account;

use Models\Account\Brokers\ActorBroker;
use Zephyrus\Application\Rule;

class AccountRules
{
    public static function emailAvailable(?string $currentEmail = null): Rule
    {
        return new Rule(function ($value) use ($currentEmail) {
            if ($currentEmail == $value) {
                return true;
            }
            return !new ActorBroker()->emailExists($value);
        }, localize("accounts.errors.email_unavailable"));
    }
}
