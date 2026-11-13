<?php

use CraftCms\Cms\GarbageCollection\Events\RunningGarbageCollection;
use CraftCms\Cms\GarbageCollection\GarbageCollection;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\User\Events\UserActivated;
use CraftCms\DigitalProducts\Database\Table;
use CraftCms\DigitalProducts\Elements\License;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\Fields\Products as ProductsField;
use CraftCms\DigitalProducts\Settings;
use Illuminate\Support\Facades\DB;

use function CraftCms\Cms\renderString;

it('exposes craft.digitalProducts to templates', function () {
    $productType = createProductType();
    createProduct($productType, ['title' => 'Twig Product']);

    $output = renderString(
        '{{ craft.digitalProducts.products().type("software").one().title }}|{{ craft.digitalProducts.getProductTypes()|length }}|{{ craft.digitalProducts.licenses().count() }}',
    );

    expect($output)->toBe('Twig Product|1|0');
});

it('registers the Products relation field for digital products', function () {
    $field = new ProductsField(['name' => 'Related', 'handle' => 'related']);

    expect(Fields::saveField($field))->toBeTrue()
        ->and(ProductsField::elementType())->toBe(Product::class)
        ->and(Fields::getFieldByHandle('related'))->toBeInstanceOf(ProductsField::class);
});

it('resaves digital products from the console', function () {
    createProduct(createProductType());

    $this->artisan('craft:resave:digital-products', ['--type' => 'software'])->assertSuccessful();
});

it('assigns licenses to a user when the user is activated', function () {
    app(Settings::class)->autoAssignUserOnPurchase = false;
    app(Settings::class)->autoAssignLicensesOnUserRegistration = true;

    // A license issued in the control panel to an email that has no account yet
    $license = new License;
    $license->productId = createProduct(createProductType())->id;
    $license->ownerEmail = 'later@example.test';
    Elements::saveElement($license) or throw new RuntimeException(json_encode($license->errors()->getMessages()));
    expect($license->userId)->toBeNull();

    $user = Users::ensureUserByEmail('later@example.test');
    event(new UserActivated($user));

    expect(License::find()->id($license->id)->one()->userId)->toBe($user->id);
});

it('garbage collects partial digital product elements', function () {
    $product = createProduct(createProductType());
    DB::table(Table::PRODUCTS)->where('id', $product->id)->delete();

    event(new RunningGarbageCollection(app(GarbageCollection::class)));

    expect(DB::table('elements')->where('id', $product->id)->exists())->toBeFalse();
});
