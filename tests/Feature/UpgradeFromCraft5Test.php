<?php

use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Commerce\Database\Table as CommerceTable;
use CraftCms\DigitalProducts\Database\Table;
use CraftCms\DigitalProducts\Elements\License;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\FieldLayoutElements\ProductTitleField;
use CraftCms\DigitalProducts\Fields\Products as ProductsField;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const OLD_PRODUCT = 'craft\digitalproducts\elements\Product';
const OLD_LICENSE = 'craft\digitalproducts\elements\License';
const OLD_FIELD = 'craft\digitalproducts\fields\Products';
const OLD_TITLE_FIELD = 'craft\digitalproducts\fieldlayoutelements\ProductTitleField';

function runUpgradeMigration(): void
{
    $migration = require dirname(__DIR__, 2).'/database/migrations/2026_11_10_000000_upgrade_from_craft_5.php';
    $migration->up();
}

/**
 * Writes project config without running its handlers, as data left by Craft 5 would be.
 */
function setRawProjectConfig(string $path, mixed $value): void
{
    $projectConfig = app(ProjectConfig::class);
    $projectConfig->muteEvents = true;

    try {
        $projectConfig->set($path, $value);
    } finally {
        $projectConfig->muteEvents = false;
    }
}

it('moves 4.x SKU and price columns into Commerce and makes license columns nullable', function () {
    $product = createProduct(createProductType(), ['sku' => 'CRAFT6-SKU', 'price' => 10]);

    // Put the tables back the way Digital Products 4.x left them
    Schema::table(Table::PRODUCTS, function (Blueprint $table) {
        $table->string('sku')->nullable();
        $table->decimal('price', 14, 4)->nullable();
    });
    Schema::table(Table::LICENSES, function (Blueprint $table) {
        $table->integer('productId')->nullable(false)->change();
        $table->string('ownerEmail')->nullable(false)->change();
    });
    DB::table(Table::PRODUCTS)->where('id', $product->id)->update(['sku' => 'LEGACY-SKU', 'price' => 42.5]);

    runUpgradeMigration();

    expect(Schema::hasColumn(Table::PRODUCTS, 'sku'))->toBeFalse()
        ->and(Schema::hasColumn(Table::PRODUCTS, 'price'))->toBeFalse()
        ->and(DB::table(CommerceTable::PURCHASABLES)->where('id', $product->id)->value('sku'))->toBe('LEGACY-SKU')
        ->and((float) DB::table(CommerceTable::PURCHASABLES_STORES)->where('purchasableId', $product->id)->value('basePrice'))->toBe(42.5);

    $columns = collect(Schema::getColumns(Table::LICENSES))->keyBy('name');
    expect($columns['productId']['nullable'])->toBeTrue()
        ->and($columns['ownerEmail']['nullable'])->toBeTrue();
});

it('rewrites the stored Craft 5 class names', function () {
    $productType = createProductType();
    $product = createProduct($productType);
    $license = new License;
    $license->productId = $product->id;
    $license->ownerEmail = 'customer@example.test';
    Elements::saveElement($license) or throw new RuntimeException(json_encode($license->errors()->getMessages()));

    $field = new ProductsField(['name' => 'Related products', 'handle' => 'relatedProducts']);
    Fields::saveField($field) or throw new RuntimeException(json_encode($field->errors()->getMessages()));

    $layoutId = $productType->getFieldLayout()->id;

    // Simulate the class names a Craft 5 install stored
    DB::table('elements')->where('id', $product->id)->update(['type' => OLD_PRODUCT]);
    DB::table('elements')->where('id', $license->id)->update(['type' => OLD_LICENSE]);
    DB::table('fieldlayouts')->where('id', $layoutId)->update([
        'type' => OLD_PRODUCT,
        'config' => str_replace(json_encode(ProductTitleField::class), json_encode(OLD_TITLE_FIELD), DB::table('fieldlayouts')->where('id', $layoutId)->value('config')),
    ]);
    DB::table('fields')->where('id', $field->id)->update(['type' => OLD_FIELD]);
    setRawProjectConfig("fields.$field->uid.type", OLD_FIELD);
    setRawProjectConfig('elementSources.'.OLD_PRODUCT, [['type' => 'native', 'key' => '*']]);
    $layoutConfigPath = "digital-products.productTypes.$productType->uid.fieldLayouts";
    $layoutConfig = app(ProjectConfig::class)->get($layoutConfigPath);
    setRawProjectConfig($layoutConfigPath, json_decode(str_replace(
        json_encode(ProductTitleField::class),
        json_encode(OLD_TITLE_FIELD),
        json_encode($layoutConfig),
    ), true));

    expect(DB::table('fieldlayouts')->where('id', $layoutId)->value('config'))->toContain('craft\\\\digitalproducts\\\\fieldlayoutelements\\\\ProductTitleField');

    runUpgradeMigration();

    expect(DB::table('elements')->where('id', $product->id)->value('type'))->toBe(Product::class)
        ->and(DB::table('elements')->where('id', $license->id)->value('type'))->toBe(License::class)
        ->and(DB::table('fieldlayouts')->where('id', $layoutId)->value('type'))->toBe(Product::class)
        ->and(DB::table('fieldlayouts')->where('id', $layoutId)->value('config'))->toContain(json_encode(ProductTitleField::class))
        ->and(DB::table('fieldlayouts')->where('id', $layoutId)->value('config'))->not->toContain('digitalproducts')
        ->and(DB::table('fields')->where('id', $field->id)->value('type'))->toBe(ProductsField::class);

    $projectConfig = app(ProjectConfig::class);
    expect($projectConfig->get("fields.$field->uid.type"))->toBe(ProductsField::class)
        ->and($projectConfig->get('elementSources.'.OLD_PRODUCT))->toBeNull()
        ->and($projectConfig->get('elementSources.'.Product::class))->not->toBeNull()
        ->and(json_encode($projectConfig->get($layoutConfigPath)))->toContain(json_encode(ProductTitleField::class))
        ->and(json_encode($projectConfig->get($layoutConfigPath)))->not->toContain('digitalproducts');

    // The elements load as the new classes again
    expect(Product::find()->id($product->id)->one())->toBeInstanceOf(Product::class)
        ->and(License::find()->id($license->id)->one())->toBeInstanceOf(License::class);
});

it('adds the download field column for sites upgrading from before 5.1', function () {
    Schema::table(Table::PRODUCT_TYPES, fn (Blueprint $table) => $table->dropColumn('downloadFieldUid'));

    $migration = require dirname(__DIR__, 2).'/database/migrations/2026_11_12_000000_add_product_type_download_field.php';
    $migration->up();
    // Running it again on a 5.1 site, which already has the column, is a no-op
    $migration->up();

    expect(Schema::hasColumn(Table::PRODUCT_TYPES, 'downloadFieldUid'))->toBeTrue();
});
