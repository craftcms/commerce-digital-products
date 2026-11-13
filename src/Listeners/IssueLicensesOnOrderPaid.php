<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Listeners;

use CraftCms\Commerce\Order\Events\OrderPaid;
use CraftCms\DigitalProducts\Licenses;
use CraftCms\DigitalProducts\Settings;

/**
 * Issues licenses when an order is paid in full, if the `generateLicenseOnOrderPaid` setting is on.
 */
readonly class IssueLicensesOnOrderPaid
{
    public function __construct(
        private Settings $settings,
        private Licenses $licenses,
    ) {}

    public function handle(OrderPaid $event): void
    {
        if (! $this->settings->generateLicenseOnOrderPaid) {
            return;
        }

        $this->licenses->licenseOrder($event->order);
    }
}
