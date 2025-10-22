<?php namespace Models\Legal\Validators;

use Zephyrus\Application\Form;
use Zephyrus\Application\Rule;
use Zephyrus\Exceptions\FormException;

class EvidenceValidator
{
    private const array ALLOWED_KINDS = [
        'DIGITAL_DUMP',
        'FILESET',
        'PHOTO',
        'VIDEO',
        'AUDIO',
        'PHYSICAL_ITEM',
        'OTHER'
    ];

    public static function assertInsert(Form $form): void
    {
        $form->field('title', [
            Rule::required(localize('evidences.errors.title_required')),
            Rule::maxLength(200, localize('evidence.errors.title_too_long'))
        ]);
        $form->field('kind', [
            Rule::required(localize('evidences.errors.kind_required')),
            Rule::inArray(self::ALLOWED_KINDS, localize('evidence.errors.kind_invalid'))
        ]);
        $form->field('jurisdiction', [
            Rule::required(localize('evidences.errors.jurisdiction_required')),
            Rule::regex('[A-Z]{2}-[A-Z0-9]{2,}', localize('evidences.errors.jurisdiction_invalid'))
        ]);
        $form->field('description', [
            Rule::maxLength(4000, localize('evidences.errors.description_too_long'))
        ]);
        $form->field('external_uri', [
            Rule::url(localize('evidences.errors.external_uri_invalid'))
        ])->optional();
        $form->field('physical_tag', [
            Rule::maxLength(120, localize('evidences.errors.physical_tag_too_long'))
        ])->optional();
        $form->field('serial', [
            Rule::maxLength(120, localize('evidences.errors.serial_too_long'))
        ])->optional();
        $form->field('affirm', [
            Rule::required(localize('evidences.errors.affirm_required'))
        ]);
        if (!$form->verify()) {
            throw new FormException($form);
        }
    }
}