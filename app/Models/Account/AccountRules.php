<?php namespace Models\Account;

use Models\Account\Brokers\SubmitterBroker;
use Zephyrus\Application\Rule;

class AccountRules
{
    public static function emailAvailable(?string $currentEmail = null): Rule
    {
        return new Rule(function ($value) use ($currentEmail) {
            if ($currentEmail == $value) {
                return true;
            }
            return !new SubmitterBroker()->emailExists($value);
        }, localize("accounts.errors.email_unavailable"));
    }
}
