<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Listeners;

use CraftCms\Cms\Element\Jobs\ResaveElements;
use CraftCms\Cms\Site\Events\SiteSaved;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\ProductTypes;

/**
 * Gives a new site the old primary site's product type settings, and resaves the products.
 */
readonly class SiteSavedListener
{
    public function __construct(private ProductTypes $productTypes) {}

    public function handle(SiteSaved $event): void
    {
        $this->productTypes->afterSaveSiteHandler($event);

        if ($event->oldPrimarySiteId === null) {
            return;
        }

        dispatch(new ResaveElements(
            elementType: Product::class,
            criteria: [
                'siteId' => $event->oldPrimarySiteId,
                'status' => null,
            ],
        ));
    }
}
