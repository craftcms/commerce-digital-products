<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Events;

/**
 * Dispatched before a product type is saved See UPGRADE.md for the 5.x event it replaces.
 *
 * Listeners may modify `$productType` before it is validated and written to project config.
 */
class ProductTypeSaving extends ProductTypeEvent {}
