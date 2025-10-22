<?php namespace Models\Legal\Validators;

use Zephyrus\Application\Form;
use Zephyrus\Application\Rule;
use Zephyrus\Exceptions\FormException;

class LegalCaseValidator
{
    public static function assertInsert(Form $form): void
    {
        $form->field('title', [
            Rule::required(localize("cases.errors.title_required")),
        ]);
        $form->field('jurisdiction', [
            Rule::required(localize("cases.errors.jurisdiction_required")),
        ]);
        if (!$form->verify()) {
            throw new FormException($form);
        }
    }
}
