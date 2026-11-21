<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Events;

/**
 * Dispatched before a product type is saved. Replaces `ProductTypes::EVENT_BEFORE_SAVE_PRODUCTTYPE`.
 *
 * Listeners may modify `$productType` before it is validated and written to project config.
 */
class ProductTypeSaving extends ProductTypeEvent {}
