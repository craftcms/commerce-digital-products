<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Twig;

use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Elements\Queries\LicenseQuery;
use CraftCms\DigitalProducts\Elements\Queries\ProductQuery;
use CraftCms\DigitalProducts\Plugin;
use CraftCms\DigitalProducts\ProductTypes;

/**
 * The `craft.digitalProducts` Twig variable.
 */
class DigitalProductsVariable
{
    /**
     * @return ProductType[]
     */
    public function getProductTypes(): array
    {
        return app(ProductTypes::class)->getAllProductTypes();
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function licenses(array $criteria = []): LicenseQuery
    {
        return new LicenseQuery($criteria);
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function products(array $criteria = []): ProductQuery
    {
        return new ProductQuery($criteria);
    }

    public function getPlugin(): Plugin
    {
        return app(Plugin::class);
    }
}
