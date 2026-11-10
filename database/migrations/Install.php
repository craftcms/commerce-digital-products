<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Database\Migrations;

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Commerce\Database\Table as CommerceTable;
use CraftCms\DigitalProducts\Database\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the Digital Products tables on a fresh install, and drops them on uninstall.
 *
 * The schema matches Digital Products 5.x: SKUs and prices live in Commerce's purchasable tables, and a
 * license's product and owner email are optional.
 */
class Install extends Migration
{
    public function up(): void
    {
        Schema::create(Table::PRODUCT_TYPES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('fieldLayoutId')->nullable();
            $table->string('name');
            $table->string('handle');
            $table->string('skuFormat')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');

            $table->unique('handle');
            $table->index('fieldLayoutId');
        });

        Schema::create(Table::PRODUCT_TYPES_SITES, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('productTypeId');
            $table->integer('siteId');
            $table->text('uriFormat')->nullable();
            $table->string('template', 500)->nullable();
            $table->boolean('hasUrls')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');

            $table->unique(['productTypeId', 'siteId']);
            $table->index('siteId');
        });

        Schema::create(Table::PRODUCTS, function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('typeId');
            $table->integer('taxCategoryId');
            $table->dateTime('postDate')->nullable();
            $table->dateTime('expiryDate')->nullable();
            $table->boolean('promotable');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');

            $table->index('typeId');
            $table->index('taxCategoryId');
        });

        Schema::create(Table::LICENSES, function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('productId')->nullable();
            $table->integer('orderId')->nullable();
            $table->string('licenseKey');
            $table->string('ownerName')->nullable();
            $table->string('ownerEmail')->nullable();
            $table->integer('userId')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');

            $table->unique('licenseKey');
            $table->index('orderId');
            $table->index('productId');
        });

        Schema::table(Table::PRODUCT_TYPES, fn (Blueprint $table) => $table->foreign('fieldLayoutId')->references('id')->on(CraftTable::FIELDLAYOUTS)->nullOnDelete());
        Schema::table(Table::PRODUCT_TYPES_SITES, fn (Blueprint $table) => $table->foreign('siteId')->references('id')->on(CraftTable::SITES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::PRODUCT_TYPES_SITES, fn (Blueprint $table) => $table->foreign('productTypeId')->references('id')->on(Table::PRODUCT_TYPES)->cascadeOnDelete());
        Schema::table(Table::PRODUCTS, fn (Blueprint $table) => $table->foreign('id')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete());
        Schema::table(Table::PRODUCTS, fn (Blueprint $table) => $table->foreign('taxCategoryId')->references('id')->on(CommerceTable::TAXCATEGORIES)->restrictOnDelete());
        Schema::table(Table::PRODUCTS, fn (Blueprint $table) => $table->foreign('typeId')->references('id')->on(Table::PRODUCT_TYPES)->cascadeOnDelete());
        Schema::table(Table::LICENSES, fn (Blueprint $table) => $table->foreign('id')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete());
        Schema::table(Table::LICENSES, fn (Blueprint $table) => $table->foreign('orderId')->references('id')->on(CommerceTable::ORDERS)->nullOnDelete());
        Schema::table(Table::LICENSES, fn (Blueprint $table) => $table->foreign('productId')->references('id')->on(Table::PRODUCTS)->restrictOnDelete());
        Schema::table(Table::LICENSES, fn (Blueprint $table) => $table->foreign('userId')->references('id')->on(CraftTable::USERS)->nullOnDelete());
    }

    /**
     * Drops the tables and removes the `digital-products` project config.
     *
     * Never deletes elements: Commerce's migration command moves the data out and retypes the elements first,
     * so this also has to work when the tables are already empty.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([Table::LICENSES, Table::PRODUCTS, Table::PRODUCT_TYPES_SITES, Table::PRODUCT_TYPES] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();

        // Mute the project config handlers, which would otherwise delete the products of each removed product type.
        $projectConfig = app(ProjectConfig::class);
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        try {
            $projectConfig->remove('digital-products');
        } finally {
            $projectConfig->muteEvents = $muteEvents;
        }
    }
}
