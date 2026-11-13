<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Console\Commands;

use CraftCms\Cms\Element\Commands\Resave\ResaveCommand;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\ProductTypes;
use Override;

/**
 * Re-saves digital products (replaces `craft resave/digital-products`).
 */
class ResaveDigitalProductsCommand extends ResaveCommand
{
    #[Override]
    protected $signature = 'craft:resave:digital-products'
        .self::DEFAULT_OPTIONS
        .'
        {--type= : The product type handle(s) of the digital products to resave, comma-separated.}
    ';

    #[Override]
    protected $description = 'Re-saves Commerce digital products.';

    #[Override]
    protected $aliases = ['resave/digital-products'];

    public function handle(ProductTypes $productTypes): int
    {
        if (! $this->validateResaveOptions()) {
            return self::FAILURE;
        }

        $criteria = [];

        if ($this->option('type')) {
            $criteria['type'] = explode(',', (string) $this->option('type'));
        }

        if (! empty($this->resolvedWithFields)) {
            $handles = array_values(array_map(
                fn (ProductType $productType) => $productType->handle,
                array_filter(
                    $productTypes->getAllProductTypes(),
                    fn (ProductType $productType) => $this->hasTheFields($productType->getFieldLayout()),
                ),
            ));

            $criteria['type'] = isset($criteria['type']) ? array_values(array_intersect($criteria['type'], $handles)) : $handles;

            if ($criteria['type'] === []) {
                $this->components->warn('No product types satisfy `--with-fields`.');

                return self::FAILURE;
            }
        }

        return $this->resaveElements(Product::class, $criteria);
    }
}
