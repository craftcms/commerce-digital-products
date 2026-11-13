<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Listeners;

use CraftCms\Commerce\Payment\Events\PaymentProcessing;
use CraftCms\DigitalProducts\Licenses;
use CraftCms\DigitalProducts\Settings;

use function CraftCms\Cms\currentUser;

/**
 * Blocks payment for guests when `requireLoggedInUser` is on and the cart has a digital product.
 */
readonly class PreventGuestPayment
{
    public function __construct(
        private Settings $settings,
        private Licenses $licenses,
    ) {}

    public function handle(PaymentProcessing $event): void
    {
        if (! $this->settings->requireLoggedInUser || currentUser() !== null) {
            return;
        }

        if ($this->licenses->orderHasDigitalProducts($event->order)) {
            $event->isValid = false;
        }
    }
}
