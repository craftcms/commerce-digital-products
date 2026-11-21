<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts;

use CraftCms\Cms\Plugin\PluginSettings;

/**
 * Digital Products settings, stored in project config at `plugins.digital-products.settings`.
 */
class Settings extends PluginSettings
{
    /**
     * Whether licenses should automatically be assigned to existing users if the emails match.
     */
    public bool $autoAssignUserOnPurchase = true;

    /**
     * Whether licenses should be automatically assigned to newly-registered users if the emails match.
     */
    public bool $autoAssignLicensesOnUserRegistration = true;

    /**
     * The available characters that can be used in license key generation.
     */
    public string $licenseKeyCharacters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /**
     * The length of generated license keys.
     */
    public int $licenseKeyLength = 24;

    /**
     * Whether a user must be logged in when completing an order with at least one digital product in the cart.
     */
    public bool $requireLoggedInUser = true;

    /**
     * Whether licenses are generated when an order is paid in full (`true`) or when it is completed (`false`).
     */
    public bool $generateLicenseOnOrderPaid = true;

    #[\Override]
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'autoAssignUserOnPurchase' => ['boolean'],
            'autoAssignLicensesOnUserRegistration' => ['boolean'],
            'licenseKeyCharacters' => ['required', 'string'],
            'licenseKeyLength' => ['required', 'integer', 'min:1', 'max:255'],
            'requireLoggedInUser' => ['boolean'],
            'generateLicenseOnOrderPaid' => ['boolean'],
        ]);
    }
}
