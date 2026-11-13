<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Validation;

use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\DigitalProducts\Elements\License;
use Illuminate\Validation\Rule;

/**
 * @property License $subject
 */
class LicenseRules extends ElementRules
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['productId'] = ['nullable', 'integer', Rule::when($this->inScenarios(self::SCENARIO_LIVE), ['required'])];
        $rules['orderId'] = ['nullable', 'integer'];
        $rules['userId'] = [
            'nullable',
            'integer',
            Rule::when(
                fn () => $this->inScenarios(self::SCENARIO_LIVE) && empty($this->subject->ownerEmail),
                ['required'],
            ),
        ];
        $rules['ownerEmail'] = ['nullable', 'email', 'max:255'];
        $rules['ownerName'] = ['nullable', 'string', 'max:255'];
        $rules['licenseKey'] = ['nullable', 'string', 'max:255'];

        return $rules;
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'userId.required' => \CraftCms\Cms\t('A license must have either an email or an owner assigned to it.', category: 'digital-products'),
        ];
    }
}
