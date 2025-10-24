<?php namespace Models\Legal\Validators;

use Models\Legal\Brokers\CaseParticipantBroker;
use Zephyrus\Application\Form;
use Zephyrus\Application\Rule;
use Zephyrus\Exceptions\FormException;

class MemberValidator
{
    private const array ALLOWED_ROLES = [
        'OWNER',
        'EDITOR',
        'VIEWER'
    ];
    public static function assertInsert(string $caseId, Form $form): void
    {
        $form->field('address', [
            Rule::required(localize('cases.errors.address_required')),
            Rule::regex('0x[a-fA-F0-9]{40}', localize('cases.errors.address_invalid')),
            new Rule(function ($value) use ($caseId) {
                return new CaseParticipantBroker()->roleOf($caseId, $value) == null;
            }, localize('cases.errors.address_already_member'))
        ]);
        $form->field('role', [
            Rule::required(localize('cases.errors.role_required')),
            Rule::inArray(self::ALLOWED_ROLES, localize('cases.errors.role_invalid'))
        ]);
        $form->field('affirm', [
            Rule::required(localize('cases.errors.affirm_required'))
        ]);
        if (!$form->verify()) {
            throw new FormException($form);
        }
    }
}