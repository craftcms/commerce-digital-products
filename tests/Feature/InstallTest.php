<?php

use CraftCms\Cms\Plugin\Plugins;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\DigitalProducts\Database\Table;
use CraftCms\DigitalProducts\Elements\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates the 5.x schema on install', function () {
    foreach ([Table::PRODUCTS, Table::LICENSES, Table::PRODUCT_TYPES, Table::PRODUCT_TYPES_SITES] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    expect(Schema::hasColumn(Table::PRODUCTS, 'sku'))->toBeFalse()
        ->and(Schema::hasColumn(Table::PRODUCTS, 'price'))->toBeFalse();

    $columns = collect(Schema::getColumns(Table::LICENSES))->keyBy('name');
    expect($columns['productId']['nullable'])->toBeTrue()
        ->and($columns['ownerEmail']['nullable'])->toBeTrue();
});

it('drops its tables and project config on uninstall without deleting elements', function () {
    $product = createProduct(createProductType());

    expect(app(ProjectConfig::class)->get('digital-products'))->not->toBeNull();

    // Commerce's migration command empties the tables before the plugin is uninstalled.
    DB::table(Table::PRODUCTS)->delete();

    expect(app(Plugins::class)->uninstallPlugin('digital-products'))->toBeTrue();

    foreach ([Table::PRODUCTS, Table::LICENSES, Table::PRODUCT_TYPES, Table::PRODUCT_TYPES_SITES] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }

    expect(app(ProjectConfig::class)->get('digital-products'))->toBeNull()
        ->and(DB::table('elements')->where('id', $product->id)->exists())->toBeTrue();
});

it('uninstalls with data in the tables without deleting elements', function () {
    $product = createProduct(createProductType());

    expect(app(Plugins::class)->uninstallPlugin('digital-products'))->toBeTrue()
        ->and(Schema::hasTable(Table::PRODUCTS))->toBeFalse()
        ->and(DB::table('elements')->where('id', $product->id)->where('type', Product::class)->exists())->toBeTrue();
});
