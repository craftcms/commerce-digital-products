<?php

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Database\Table as CommerceTable;
use CraftCms\DigitalProducts\Database\Table;
use CraftCms\DigitalProducts\Elements\License;
use CraftCms\DigitalProducts\Elements\Product;
use Illuminate\Support\Facades\DB;

it('saves a digital product as a Commerce purchasable', function () {
    $product = createProduct(createProductType(), ['title' => 'Plugin License', 'sku' => 'PLUGIN-1', 'price' => 99]);

    $row = DB::table(Table::PRODUCTS)->where('id', $product->id)->first();
    expect($row)->not->toBeNull()
        ->and(DB::table(CommerceTable::PURCHASABLES)->where('id', $product->id)->value('sku'))->toBe('PLUGIN-1')
        ->and((float) DB::table(CommerceTable::PURCHASABLES_STORES)->where('purchasableId', $product->id)->value('basePrice'))->toBe(99.0);

    $found = Product::find()->id($product->id)->one();
    expect($found)->toBeInstanceOf(Product::class)
        ->and($found->title)->toBe('Plugin License')
        ->and($found->getSku())->toBe('PLUGIN-1')
        ->and($found->getStatus())->toBe(Product::STATUS_LIVE)
        ->and($found->getUrl())->toEndWith('software/plugin-license')
        ->and(Product::find()->type('software')->count())->toBe(1);
});

it('generates the SKU from the product type SKU format', function () {
    $product = createProduct(createProductType('ebooks', 'EB-{slug}'), ['title' => 'My Book']);

    expect($product->getSku())->toBe('EB-my-book');
});

it('reports pending and expired statuses', function () {
    $productType = createProductType();
    $pending = createProduct($productType, ['title' => 'Pending', 'postDate' => new DateTime('+1 week')]);
    $expired = createProduct($productType, ['title' => 'Expired', 'expiryDate' => new DateTime('-1 hour')]);

    expect($pending->getStatus())->toBe(Product::STATUS_PENDING)
        ->and($expired->getStatus())->toBe(Product::STATUS_EXPIRED)
        ->and(Product::find()->status(Product::STATUS_LIVE)->count())->toBe(0)
        ->and(Product::find()->status(Product::STATUS_PENDING)->ids())->toBe([$pending->id]);
});

it('blocks deleting a product that has licenses', function () {
    $product = createProduct(createProductType());
    $license = new License;
    $license->productId = $product->id;
    $license->ownerEmail = 'someone@example.test';
    Elements::saveElement($license) or throw new RuntimeException(json_encode($license->errors()->getMessages()));

    expect(Elements::deleteElement($product))->toBeFalse()
        ->and(Product::find()->id($product->id)->exists())->toBeTrue();
});
