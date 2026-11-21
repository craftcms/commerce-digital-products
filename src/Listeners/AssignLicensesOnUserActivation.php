<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Listeners;

use CraftCms\Cms\User\Events\UserActivated;
use CraftCms\DigitalProducts\Licenses;
use CraftCms\DigitalProducts\Settings;

/**
 * Assigns licenses bought with the user's email when the user is activated, if
 * `autoAssignLicensesOnUserRegistration` is on.
 */
readonly class AssignLicensesOnUserActivation
{
    public function __construct(
        private Settings $settings,
        private Licenses $licenses,
    ) {}

    public function handle(UserActivated $event): void
    {
        if (! $this->settings->autoAssignLicensesOnUserRegistration) {
            return;
        }

        $this->licenses->assignLicensesToUser($event->user);
    }
}
