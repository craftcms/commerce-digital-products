<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Validation;

use CraftCms\Cms\Validation\Rules\HandleRule;
use CraftCms\Cms\Validation\Ruleset;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Database\Table;
use Illuminate\Database\Query\Builder;
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
                // Read the ID when the rule runs: the validator is cached, and a new type gets its ID on save.
                Rule::unique(Table::PRODUCT_TYPES, 'handle')->where(fn (Builder $query) => $this->subject->id
                    ? $query->where('id', '!=', $this->subject->id)
                    : $query),
            ],
            'skuFormat' => ['nullable', 'string', 'max:255'],
        ];
    }
}
