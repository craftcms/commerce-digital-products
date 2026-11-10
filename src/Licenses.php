<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts;

use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\ProjectConfig\Events\ConfigEvent;
use CraftCms\Cms\ProjectConfig\ProjectConfigHelper;
use CraftCms\Cms\Support\Facades\ElementCaches;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\DigitalProducts\Elements\License;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\Events\LicenseKeyGenerating;
use Illuminate\Container\Attributes\Singleton;

/**
 * Issues and looks up licenses.
 */
#[Singleton]
class Licenses
{
    /**
     * Prefix of the placeholder key a license holds until it has a product.
     */
    public const string TEMP_KEY_PREFIX = '__temp_';

    /**
     * Project config path of the license field layout.
     */
    public const string CONFIG_FIELDLAYOUT_KEY = 'digital-products.licenseFieldLayouts';

    public function __construct(
        private readonly Settings $settings,
    ) {}

    /**
     * Applies a changed license field layout from project config.
     */
    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        $data = $event->newValue;

        ProjectConfigHelper::ensureAllFieldsProcessed();

        if (empty($data) || empty(reset($data))) {
            Fields::deleteLayoutsByType(License::class);

            return;
        }

        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = Fields::getLayoutByType(License::class)->id;
        $layout->type = License::class;
        $layout->uid = key($data);
        Fields::saveLayout($layout, false);

        ElementCaches::invalidateForElementType(License::class);
    }

    /**
     * Deletes the license field layout when it is removed from project config.
     */
    public function handleDeletedFieldLayout(): void
    {
        Fields::deleteLayoutsByType(License::class);
    }

    /**
     * Creates one license per purchased digital product unit in an order.
     *
     * @return License[] The licenses that were created.
     */
    public function licenseOrder(Order $order): array
    {
        $licenses = [];

        foreach ($order->getLineItems() as $lineItem) {
            // The purchasable could have been deleted
            if (! $lineItem->purchasableId) {
                continue;
            }

            $product = Product::find()->id($lineItem->purchasableId)->status(null)->one();

            if (! $product) {
                continue;
            }

            for ($i = 0; $i < $lineItem->qty; $i++) {
                if ($license = $this->licenseProductByOrder($product, $order)) {
                    $licenses[] = $license;
                }
            }
        }

        return $licenses;
    }

    /**
     * Creates a license for a product bought in an order.
     */
    public function licenseProductByOrder(Product $product, Order $order): ?License
    {
        $customer = $order->getCustomer();

        if (! $customer) {
            return null;
        }

        $license = new License;
        $license->productId = $product->id;
        $license->ownerEmail = $customer->email;
        $license->ownerName = $customer->getName();
        $license->userId = $customer->id;
        $license->orderId = $order->id;

        return Elements::saveElement($license) ? $license : null;
    }

    /**
     * Whether the order has a digital product in it.
     */
    public function orderHasDigitalProducts(Order $order): bool
    {
        $purchasableIds = array_filter(array_map(fn ($lineItem) => $lineItem->purchasableId, $order->getLineItems()));

        return $purchasableIds !== [] && Product::find()->id($purchasableIds)->status(null)->exists();
    }

    /**
     * Assigns unowned licenses with a matching email to a user.
     */
    public function assignLicensesToUser(User $user): void
    {
        if (! $user->email) {
            return;
        }

        $licenses = License::find()->ownerEmail($user->email)->ownerId(':empty:')->status(null)->all();

        foreach ($licenses as $license) {
            $license->userId = $user->id;
            Elements::saveElement($license);
        }
    }

    /**
     * Returns the key for a new license: the one already set on it, one supplied by a
     * {@see LicenseKeyGenerating} listener, or a new unique random key.
     */
    public function generateLicenseKeyFor(License $license): string
    {
        if ($license->hasLicenseKey()) {
            return $license->licenseKey;
        }

        event($event = new LicenseKeyGenerating($license));

        if ($event->licenseKey !== null && $event->licenseKey !== '') {
            return $event->licenseKey;
        }

        do {
            $licenseKey = $this->generateLicenseKey();
        } while (! $this->isLicenseKeyUnique($licenseKey));

        return $licenseKey;
    }

    /**
     * Generates a random license key from the configured characters and length.
     */
    public function generateLicenseKey(): string
    {
        $characters = $this->settings->licenseKeyCharacters;
        $max = mb_strlen($characters) - 1;
        $licenseKey = '';

        for ($i = 0; $i < $this->settings->licenseKeyLength; $i++) {
            $licenseKey .= mb_substr($characters, random_int(0, $max), 1);
        }

        return $licenseKey;
    }

    public function isLicenseKeyUnique(string $licenseKey): bool
    {
        return ! License::find()->licenseKey($licenseKey)->status(null)->trashed(null)->exists();
    }
}
