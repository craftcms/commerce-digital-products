<?php

use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\User\Models\User;
use CraftCms\Commerce\Order\Events\OrderPaid;
use CraftCms\Commerce\Payment\Events\PaymentProcessing;
use CraftCms\Commerce\Payment\Forms\DummyPaymentForm;
use CraftCms\DigitalProducts\Elements\License;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\Events\LicenseKeyGenerating;
use CraftCms\DigitalProducts\Settings;
use Illuminate\Support\Facades\Event;

it('issues one license per unit when an order is completed', function () {
    $settings = app(Settings::class);
    $settings->generateLicenseOnOrderPaid = false;
    $settings->licenseKeyLength = 12;
    $settings->licenseKeyCharacters = 'ABC123';

    $product = createProduct(createProductType());
    $order = orderFor($product, qty: 3);

    expect($order->markAsComplete())->toBeTrue();

    $licenses = License::find()->orderId($order->id)->all();

    expect($licenses)->toHaveCount(3);

    foreach ($licenses as $license) {
        expect($license->productId)->toBe($product->id)
            ->and($license->ownerEmail)->toBe('customer@example.test')
            ->and($license->licenseKey)->toMatch('/^[ABC123]{12}$/');
    }

    expect(collect($licenses)->pluck('licenseKey')->unique())->toHaveCount(3);
});

it('waits for the order to be paid when generateLicenseOnOrderPaid is on', function () {
    app(Settings::class)->generateLicenseOnOrderPaid = true;

    $product = createProduct(createProductType());
    $order = orderFor($product, qty: 2);
    $order->markAsComplete();

    expect(License::find()->orderId($order->id)->count())->toBe(0);

    event(new OrderPaid($order));

    expect(License::find()->orderId($order->id)->count())->toBe(2);
});

it('lets a listener supply the license key', function () {
    app(Settings::class)->generateLicenseOnOrderPaid = false;

    Event::listen(function (LicenseKeyGenerating $event) {
        $event->licenseKey = 'CUSTOM-'.$event->license->getProduct()->getSku();
    });

    $product = createProduct(createProductType(), ['sku' => 'SKU1']);
    $order = orderFor($product);
    $order->markAsComplete();

    expect(License::find()->orderId($order->id)->one()->licenseKey)->toBe('CUSTOM-SKU1');
});

it('assigns licenses to an existing user with the same email on purchase', function () {
    app(Settings::class)->generateLicenseOnOrderPaid = false;
    app(Settings::class)->autoAssignUserOnPurchase = true;

    $user = Users::ensureUserByEmail('buyer@example.test');
    $order = orderFor(createProduct(createProductType()), email: 'buyer@example.test');
    $order->markAsComplete();

    $license = License::find()->orderId($order->id)->one();

    expect($license->userId)->toBe($user->id)
        ->and($license->getOwner()?->id)->toBe($user->id);
});

it('blocks payment for guests when the cart has a digital product', function () {
    app(Settings::class)->requireLoggedInUser = true;

    $order = orderFor(createProduct(createProductType()));

    event($event = new PaymentProcessing(order: $order, form: new DummyPaymentForm));
    expect($event->isValid)->toBeFalse();

    app(Settings::class)->requireLoggedInUser = false;

    event($event = new PaymentProcessing(order: $order, form: new DummyPaymentForm));
    expect($event->isValid)->toBeTrue();
});

it('allows payment for logged-in users', function () {
    app(Settings::class)->requireLoggedInUser = true;

    $this->actingAs(User::find(1));

    $order = orderFor(createProduct(createProductType()));

    event($event = new PaymentProcessing(order: $order, form: new DummyPaymentForm));
    expect($event->isValid)->toBeTrue();
});

it('eager loads product, order and owner on licenses, and existing licenses on products', function () {
    app(Settings::class)->generateLicenseOnOrderPaid = false;

    $user = Users::ensureUserByEmail('owner@example.test');
    $product = createProduct(createProductType());
    $order = orderFor($product, qty: 2, email: 'owner@example.test');
    $order->markAsComplete();

    $licenses = License::find()->with(['product', 'order', 'owner'])->all();

    expect($licenses)->toHaveCount(2);

    foreach ($licenses as $license) {
        expect($license->getProduct()?->id)->toBe($product->id)
            ->and($license->getOrder()?->id)->toBe($order->id)
            ->and($license->getOwner()?->id)->toBe($user->id);
    }

    // Existing licenses are the current user's licenses for each product.
    $products = Product::find()->with('existingLicenses')->all();
    expect($products[0]->getExistingLicenses())->toHaveCount(0);

    $this->actingAs(User::find($user->id));

    $products = Product::find()->with('existingLicenses')->all();
    expect($products[0]->getExistingLicenses())->toHaveCount(2);
});

it('creates a license in the control panel flow without a product, then generates its key', function () {
    $license = new License;
    $license->ruleset->useScenario(ElementRules::SCENARIO_ESSENTIALS);

    expect(Elements::saveElement($license))->toBeTrue()
        ->and($license->hasLicenseKey())->toBeFalse();

    $license->ruleset->useScenario(ElementRules::SCENARIO_DEFAULT);
    $license->productId = createProduct(createProductType())->id;
    $license->ownerEmail = 'manual@example.test';

    expect(Elements::saveElement($license))->toBeTrue()
        ->and($license->hasLicenseKey())->toBeTrue()
        ->and(strlen($license->licenseKey))->toBe(app(Settings::class)->licenseKeyLength);
});
