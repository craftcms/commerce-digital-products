<?php

namespace craft\digitalproducts\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\events\ProcessPaymentEvent;
use craft\digitalproducts\elements\License;
use craft\digitalproducts\elements\Product;
use craft\digitalproducts\Plugin as DigitalProducts;
use craft\events\UserEvent;
use yii\base\Component;
use yii\base\Event;

/**
 * Licenses service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 */
class Licenses extends Component
{
    /**
     * Returns true if a given license key is unique.
     *
     * @param string $licenseKey the license key
     * @return bool
     */
    public function isLicenseKeyUnique(string $licenseKey): bool
    {
        return !License::find()
            ->licenseKey($licenseKey)
            ->status(null)
            ->exists();
    }

    /**
     * Sort through the ordered items and generate Licenses for Digital Products.
     *
     * @param Event $event
     */
    public static function handleCompletedOrder(Event $event): void
    {
        /** @var Order $order */
        $order = $event->sender;
        $lineItems = $order->getLineItems();

        foreach ($lineItems as $lineItem) {
            // Could be deleted line item
            if ($itemId = $lineItem->purchasableId) {
                $element = Craft::$app->getElements()->getElementById($itemId);
                $quantity = $lineItem->qty;

                if ($element instanceof Product) {
                    /** @var Product $element */
                    for ($i = 0; $i < $quantity; $i++) {
                        DigitalProducts::getInstance()->getLicenses()->licenseProductByOrder($element, $order);
                    }
                }
            }
        }
    }

    /**
     * Prevent paying for orders with digital products in it if user not logged in when required by config.
     *
     * @param ProcessPaymentEvent $event
     */
    public static function maybePreventPayment(ProcessPaymentEvent $event): void
    {
        if (!DigitalProducts::getInstance()->getSettings()->requireLoggedInUser || !Craft::$app->getUser()->getIsGuest()) {
            return;
        }

        /** @var Order|null $order */
        $order = $event->order;

        if (!$order) {
            return;
        }

        $lineItems = $order->getLineItems();

        foreach ($lineItems as $lineItem) {
            $itemId = $lineItem->purchasableId;
            $element = Craft::$app->getElements()->getElementById($itemId);

            if ($element instanceof Product) {
                $event->isValid = false;

                return;
            }
        }
    }

    /**
     * Assign licenses to a just-activated user, if emails match and config allows it.
     *
     * @param UserEvent $event
     */
    public static function handleUserActivation(UserEvent $event): void
    {
        if (!DigitalProducts::getInstance()->getSettings()->autoAssignLicensesOnUserRegistration) {
            return;
        }

        $licenses = License::find()->ownerEmail($event->user->email)->all();

        /** @var License $license */
        foreach ($licenses as $license) {
            // Only licenses with unassigned users
            if (!$license->userId) {
                $license->userId = $event->user->id;
                Craft::$app->getElements()->saveElement($license);
            }
        }
    }

    /**
     * Generate a license for a Product per Order.
     *
     * @param Product $product
     * @param Order $order
     * @return bool
     */
    public function licenseProductByOrder(Product $product, Order $order): bool
    {
        $license = new License();
        $license->productId = $product->id;
        $customer = $order->getCustomer();

        if (!$customer) {
            return false;
        }

        $license->ownerEmail = $customer->email;
        $license->ownerName = $customer->getName();
        $license->userId = $customer->id;

        $license->orderId = $order->id;

        return Craft::$app->getElements()->saveElement($license);
    }

    /**
     * Generate a license key.
     *
     * @return string
     */
    public function generateLicenseKey(): string
    {
        $codeAlphabet = DigitalProducts::getInstance()->getSettings()->licenseKeyCharacters;
        $keyLength = DigitalProducts::getInstance()->getSettings()->licenseKeyLength;

        $licenseKey = '';

        for ($i = 0; $i < $keyLength; $i++) {
            $licenseKey .= $codeAlphabet[random_int(0, strlen($codeAlphabet) - 1)];
        }

        return $licenseKey;
    }
}
