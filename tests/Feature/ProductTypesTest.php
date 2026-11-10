<?php

use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\Events\ProductTypeSaved;
use CraftCms\DigitalProducts\Events\ProductTypeSaving;
use CraftCms\DigitalProducts\ProductTypes;
use Illuminate\Support\Facades\Event;

it('saves a product type through the service', function () {
    Event::fake([ProductTypeSaving::class, ProductTypeSaved::class]);

    $productType = createProductType('software', 'SW-{slug}');

    expect($productType->id)->toBeInt()
        ->and($productType->uid)->toBeString();

    $saved = app(ProductTypes::class)->getProductTypeByHandle('software');
    expect($saved->name)->toBe('Software')
        ->and($saved->skuFormat)->toBe('SW-{slug}')
        ->and($saved->getSiteSettings())->toHaveCount(1)
        ->and(app(ProjectConfig::class)->get("digital-products.productTypes.$productType->uid.handle"))->toBe('software');

    Event::assertDispatched(ProductTypeSaving::class, fn (ProductTypeSaving $event) => $event->isNew);
    Event::assertDispatched(ProductTypeSaved::class, fn (ProductTypeSaved $event) => $event->productType->id === $productType->id);
});

it('rejects a product type without a handle', function () {
    $productType = new ProductType(['name' => 'Nope']);

    expect(app(ProductTypes::class)->saveProductType($productType))->toBeFalse()
        ->and($productType->errors()->has('handle'))->toBeTrue();
});

it('deletes a product type and its products', function () {
    $productType = createProductType();
    $product = createProduct($productType);

    expect(app(ProductTypes::class)->deleteProductTypeById($productType->id))->toBeTrue()
        ->and(app(ProductTypes::class)->getProductTypeById($productType->id))->toBeNull()
        ->and(Product::find()->id($product->id)->status(null)->exists())->toBeFalse();
});
