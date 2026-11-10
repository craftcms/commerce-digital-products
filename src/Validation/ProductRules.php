<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Validation;

use CraftCms\Commerce\Purchasable\Validation\PurchasableRules;

class ProductRules extends PurchasableRules
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['typeId'] = ['required', 'integer'];
        $rules['postDate'] = ['nullable', 'date'];
        $rules['expiryDate'] = ['nullable', 'date'];

        return $rules;
    }
}
