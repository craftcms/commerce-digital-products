<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Events;

/**
 * Dispatched after a product type has been saved. Replaces `ProductTypes::EVENT_AFTER_SAVE_PRODUCTTYPE`.
 */
class ProductTypeSaved extends ProductTypeEvent {}
