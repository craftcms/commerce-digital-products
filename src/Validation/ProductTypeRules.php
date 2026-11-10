<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Validation;

use CraftCms\Cms\Validation\Rules\HandleRule;
use CraftCms\Cms\Validation\Ruleset;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Database\Table;
use Illuminate\Validation\Rule;

/**
 * @extends Ruleset<ProductType>
 */
class ProductTypeRules extends Ruleset
{
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer'],
            'fieldLayoutId' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'handle' => [
                'required',
                'string',
                'max:255',
                new HandleRule(['id', 'dateCreated', 'dateUpdated', 'uid', 'title']),
                Rule::unique(Table::PRODUCT_TYPES, 'handle')->ignore($this->subject->id),
            ],
            'skuFormat' => ['nullable', 'string', 'max:255'],
        ];
    }
}
