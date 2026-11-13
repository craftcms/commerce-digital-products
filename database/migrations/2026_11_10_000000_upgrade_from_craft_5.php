<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Commerce\Database\Table as CommerceTable;
use CraftCms\DigitalProducts\Database\Table;
use CraftCms\DigitalProducts\Elements\License;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\FieldLayoutElements\ProductTitleField;
use CraftCms\DigitalProducts\Fields\Products;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrades a Digital Products 4.x or 5.x install (Craft 5) to the Craft 6 plugin.
 *
 * 1. Applies the 5.x schema changes if the site comes from 4.x: copies `sku` and `price` into Commerce's
 *    purchasable tables and drops them, and makes `licenses.productId` and `licenses.ownerEmail` nullable.
 * 2. Rewrites the stored `craft\digitalproducts\…` class names to their `CraftCms\DigitalProducts\…` names.
 */
return new class extends Migration
{
    /**
     * Digital Products 4.x/5.x class names mapped to their new names.
     *
     * @var array<string, class-string>
     */
    private const array CLASS_MAP = [
        'craft\digitalproducts\elements\Product' => Product::class,
        'craft\digitalproducts\elements\License' => License::class,
        'craft\digitalproducts\fields\Products' => Products::class,
        'craft\digitalproducts\fieldlayoutelements\ProductTitleField' => ProductTitleField::class,
    ];

    /**
     * Columns that hold a bare element, field or purchasable type.
     *
     * @var list<array{string, string}>
     */
    private const array TYPE_COLUMNS = [
        [CraftTable::ELEMENTS, 'type'],
        [CraftTable::FIELDLAYOUTS, 'type'],
        [CraftTable::FIELDS, 'type'],
        [CommerceTable::DISCOUNT_PURCHASABLES, 'purchasableType'],
        [CommerceTable::SALE_PURCHASABLES, 'purchasableType'],
    ];

    /**
     * JSON columns that can hold type names, such as field layout elements and condition configs.
     *
     * @var list<array{string, string}>
     */
    private const array JSON_COLUMNS = [
        [CraftTable::FIELDLAYOUTS, 'config'],
        [CraftTable::FIELDS, 'settings'],
    ];

    public function up(): void
    {
        $this->moveSkuAndPrice();
        $this->makeLicenseColumnsNullable();
        $this->renameClasses();
    }

    private function moveSkuAndPrice(): void
    {
        $columns = array_values(array_filter(['sku', 'price'], fn (string $column) => Schema::hasColumn(Table::PRODUCTS, $column)));

        if ($columns === []) {
            return;
        }

        foreach (DB::table(Table::PRODUCTS)->select(['id', ...$columns])->lazyById() as $product) {
            if (isset($product->price)) {
                DB::table(CommerceTable::PURCHASABLES_STORES)
                    ->where('purchasableId', $product->id)
                    ->update(['basePrice' => $product->price]);
            }

            if (isset($product->sku)) {
                DB::table(CommerceTable::PURCHASABLES)
                    ->where('id', $product->id)
                    ->update(['sku' => $product->sku]);
            }
        }

        if (in_array('sku', $columns, true)) {
            // SQLite can't drop a column that is still part of an index.
            foreach (Schema::getIndexes(Table::PRODUCTS) as $index) {
                if ($index['columns'] === ['sku']) {
                    Schema::table(Table::PRODUCTS, fn (Blueprint $table) => $table->dropIndex($index['name']));
                }
            }
        }

        Schema::table(Table::PRODUCTS, fn (Blueprint $table) => $table->dropColumn($columns));
    }

    private function makeLicenseColumnsNullable(): void
    {
        Schema::table(Table::LICENSES, function (Blueprint $table) {
            $table->integer('productId')->nullable()->change();
            $table->string('ownerEmail')->nullable()->change();
        });
    }

    private function renameClasses(): void
    {
        foreach (self::TYPE_COLUMNS as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            foreach (self::CLASS_MAP as $old => $new) {
                DB::table($table)->where($column, $old)->update([$column => $new]);
            }
        }

        foreach (self::JSON_COLUMNS as [$table, $column]) {
            foreach (DB::table($table)->whereNotNull($column)->select(['id', $column])->lazyById() as $row) {
                $updated = $this->replaceInString($row->$column);

                if ($updated !== $row->$column) {
                    DB::table($table)->where('id', $row->id)->update([$column => $updated]);
                }
            }
        }

        // Field types, field layout elements, element source settings and conditions in project config.
        $projectConfig = app(ProjectConfig::class);
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        try {
            foreach ($projectConfig->get() ?? [] as $key => $config) {
                $updated = $this->replaceInConfig($config);

                if ($updated !== $config) {
                    $projectConfig->set((string) $key, $updated);
                }
            }

            $projectConfig->saveModifiedConfigData();
        } finally {
            $projectConfig->muteEvents = $muteEvents;
        }
    }

    /**
     * Replaces old class names in array keys and string values.
     */
    private function replaceInConfig(mixed $config): mixed
    {
        if (is_string($config)) {
            return $this->replaceInString($config);
        }

        if (! is_array($config)) {
            return $config;
        }

        $updated = [];

        foreach ($config as $key => $value) {
            $updated[is_string($key) ? $this->replaceInString($key) : $key] = $this->replaceInConfig($value);
        }

        return $updated;
    }

    /**
     * Replaces old class names in a string, at any level of backslash escaping (plain, JSON, or JSON in JSON).
     */
    private function replaceInString(string $value): string
    {
        if (stripos($value, 'digitalproducts') === false) {
            return $value;
        }

        foreach (self::CLASS_MAP as $old => $new) {
            $oldSegments = explode('\\', $old);
            $newSegments = explode('\\', $new);
            $pattern = '/'.implode('(\\\\+)', array_map(fn (string $segment) => preg_quote($segment, '/'), $oldSegments)).'(?![A-Za-z0-9_])/';

            $value = (string) preg_replace_callback($pattern, function (array $match) use ($newSegments): string {
                $separator = $match[1];

                return implode($separator, $newSegments);
            }, $value);
        }

        return $value;
    }
};
