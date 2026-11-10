<?php

namespace craft\digitalproducts\models;

use craft\commerce\base\Model;

/**
 * Settings model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Settings extends Model
{
    /**
     * @var bool Whether license should automatically be assigned to existing users if the emails match.
     */
    public bool $autoAssignUserOnPurchase = true;

    /**
     * @var bool Whether licenses should be automatically assigned to newly-registered users if the emails match.
     */
    public bool $autoAssignLicensesOnUserRegistration = true;

    /**
     * @var string The available characters that can be used in license key generation.
     */
    public string $licenseKeyCharacters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /**
     * @var int The length of generated license keys.
     */
    public int $licenseKeyLength = 24;

    /**
     * @var bool Whether a user must be logged in when completing an order with at least one digital product in the cart.
     */
    public bool $requireLoggedInUser = true;

    /**
     * @var bool Whether the license should be generated on order being paid in full as opposed to order being completed.
     */
    public bool $generateLicenseOnOrderPaid = true;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules[] = [['licenseKeyCharacters', 'licenseKeyLength'], 'required'];
        $rules[] = ['licenseKeyLength', 'number', 'min' => 1, 'max' => 255];

        return $rules;
    }
}
