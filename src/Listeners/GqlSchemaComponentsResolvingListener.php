<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Listeners;

use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use CraftCms\DigitalProducts\ProductTypes;

use function CraftCms\Cms\t;

/**
 * Adds a “View digital product type” permission per product type to GraphQL schemas.
 */
readonly class GqlSchemaComponentsResolvingListener
{
    public function __construct(private ProductTypes $productTypes) {}

    public function handle(GqlSchemaComponentsResolving $event): void
    {
        $productTypes = $this->productTypes->getAllProductTypes();

        if ($productTypes === []) {
            return;
        }

        $permissions = [];

        foreach ($productTypes as $productType) {
            $permissions['digitalProductTypes.'.$productType->uid.':read'] = [
                'label' => t('View digital product type - {productType}', [
                    'productType' => t($productType->name, category: 'site'),
                ], category: 'digital-products'),
            ];
        }

        $event->queries[t('Digital Products', category: 'digital-products')] = $permissions;
    }
}
