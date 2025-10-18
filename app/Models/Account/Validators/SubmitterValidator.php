<?php namespace Models\Account\Validators;

use Models\Account\AccountRules;
use Zephyrus\Application\Form;
use Zephyrus\Application\Rule;
use Zephyrus\Exceptions\FormException;

class SubmitterValidator
{
    public static function assertInsert(Form $form): void
    {
        $form->field('email', [
            Rule::required(localize("accounts.errors.email_required")),
            Rule::email(localize("accounts.errors.email_invalid")),
            AccountRules::emailAvailable()
        ]);
        $form->field('firstname', [
            Rule::required(localize("accounts.errors.firstname_required")),
            Rule::name(localize("accounts.errors.firstname_invalid"))
        ]);
        $form->field('lastname', [
            Rule::required(localize("accounts.errors.lastname_required")),
            Rule::name(localize("accounts.errors.lastname_invalid"))
        ]);
        $form->field('juridiction', [
            Rule::required(localize("accounts.errors.juridiction_required")),
        ]);
        $form->field('agree', [
            Rule::required(localize("accounts.errors.agree_required"))
        ]);
        if (!$form->verify()) {
            throw new FormException($form);
        }
    }
}
