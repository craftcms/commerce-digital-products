<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use CraftCms\DigitalProducts\Database\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the Download Field setting to product types, for sites upgrading from before Digital Products 5.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn(Table::PRODUCT_TYPES, 'downloadFieldUid')) {
            Schema::table(Table::PRODUCT_TYPES, function (Blueprint $table) {
                $table->char('downloadFieldUid', 36)->nullable()->after('skuFormat');
            });
        }
    }
};
