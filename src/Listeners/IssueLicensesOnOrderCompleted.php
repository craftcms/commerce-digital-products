<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Listeners;

use CraftCms\Commerce\Order\Events\OrderCompleted;
use CraftCms\DigitalProducts\Licenses;
use CraftCms\DigitalProducts\Settings;

/**
 * Issues licenses when an order is completed, unless they are issued when it is paid.
 */
readonly class IssueLicensesOnOrderCompleted
{
    public function __construct(
        private Settings $settings,
        private Licenses $licenses,
    ) {}

    public function handle(OrderCompleted $event): void
    {
        if ($this->settings->generateLicenseOnOrderPaid) {
            return;
        }

        $this->licenses->licenseOrder($event->order);
    }
}
