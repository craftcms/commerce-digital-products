<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Data\ProductTypeSite;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\ProductTypes;
use CraftCms\DigitalProducts\Tests\TestCase;

uses(TestCase::class)->in('.');

/**
 * Saves a product type through the service.
 */
function createProductType(string $handle = 'software', ?string $skuFormat = null): ProductType
{
    $productType = new ProductType([
        'name' => ucfirst($handle),
        'handle' => $handle,
        'skuFormat' => $skuFormat,
    ]);

    $siteId = Sites::getPrimarySite()->id;
    $productType->setSiteSettings([
        $siteId => new ProductTypeSite([
            'siteId' => $siteId,
            'hasUrls' => true,
            'uriFormat' => "$handle/{slug}",
            'template' => "$handle/_product",
        ]),
    ]);

    app(ProductTypes::class)->saveProductType($productType)
        or throw new RuntimeException(json_encode($productType->errors()->getMessages()));

    return $productType;
}

/**
 * Saves an enabled digital product.
 */
function createProduct(ProductType $productType, array $attributes = []): Product
{
    $product = new Product;
    $product->typeId = $productType->id;
    $product->siteId = Sites::getPrimarySite()->id;
    $product->title = $attributes['title'] ?? 'My Product';
    $product->setSku($attributes['sku'] ?? null);
    $product->setBasePrice($attributes['price'] ?? 25);
    $product->postDate = $attributes['postDate'] ?? new DateTime('-1 day');
    $product->expiryDate = $attributes['expiryDate'] ?? null;

    Elements::saveElement($product)
        or throw new RuntimeException(json_encode($product->errors()->getMessages()));

    return $product;
}

/**
 * An unsaved order for a customer with one line item for the product.
 */
function orderFor(Product $product, int $qty = 1, string $email = 'customer@example.test'): Order
{
    $order = new Order;
    $order->setCustomer(Users::ensureUserByEmail($email));

    $lineItem = app(LineItems::class)->create($order, [
        'purchasableId' => $product->id,
        'qty' => $qty,
    ]);
    $order->setLineItems([$lineItem]);

    return $order;
}
