<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Events;

use CraftCms\DigitalProducts\Data\ProductType;

abstract class ProductTypeEvent
{
    public function __construct(
        public ProductType $productType,
        public bool $isNew = false,
    ) {}
}
