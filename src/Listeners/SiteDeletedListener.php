<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Listeners;

use CraftCms\Cms\Site\Events\SiteDeleted;
use CraftCms\DigitalProducts\ProductTypes;

/**
 * Removes a deleted site from the product types' site settings.
 */
readonly class SiteDeletedListener
{
    public function __construct(private ProductTypes $productTypes) {}

    public function handle(SiteDeleted $event): void
    {
        $this->productTypes->pruneDeletedSite($event);
    }
}
