<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Validation;

use CraftCms\Commerce\Purchasable\Validation\PurchasableRules;
use CraftCms\DigitalProducts\Elements\Product;
use Override;

/**
 * @property-read Product $subject
 */
class ProductRules extends PurchasableRules
{
    /**
     * Generates the SKU once the slug and title are prepared, so the SKU format can use them.
     */
    #[Override]
    public function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->subject->generateSku();
    }

    public function rules(): array
    {
        $rules = parent::rules();

        $rules['typeId'] = ['required', 'integer'];
        $rules['postDate'] = ['nullable', 'date'];
        $rules['expiryDate'] = ['nullable', 'date'];

        return $rules;
    }
}
