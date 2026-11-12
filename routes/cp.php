<?php

declare(strict_types=1);

use CraftCms\Cms\Http\Controllers\Elements\EditElementController;
use CraftCms\Cms\Http\Controllers\Elements\ElementTypeIndexController;
use CraftCms\Cms\Http\Middleware\RequireAdmin;
use CraftCms\DigitalProducts\Elements\License;
use CraftCms\DigitalProducts\Http\Controllers\CreateLicenseController;
use CraftCms\DigitalProducts\Http\Controllers\CreateProductController;
use CraftCms\DigitalProducts\Http\Controllers\ProductsController;
use CraftCms\DigitalProducts\Http\Controllers\Settings\GeneralSettingsController;
use CraftCms\DigitalProducts\Http\Controllers\Settings\LicenseFieldsController;
use CraftCms\DigitalProducts\Http\Controllers\Settings\ProductTypesController;
use CraftCms\DigitalProducts\ProductTypes;
use Illuminate\Support\Facades\Route;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\currentUser;

Route::get('digital-products', function () {
    $user = currentUser();

    // Products comes first in the subnav, which only lists it when the user can edit a product type
    return match (true) {
        $user?->can('digitalProducts-manageProducts') && app(ProductTypes::class)->getEditableProductTypes() !== [] => redirect()->to(cp_url('digital-products/products')),
        (bool) $user?->can('digitalProducts-manageLicenses') => redirect()->to(cp_url('digital-products/licenses')),
        (bool) $user?->can('digitalProducts-manageProducts') => redirect()->to(cp_url('digital-products/products')),
        default => abort(403),
    };
});

$idSlugParams = [
    'id' => '\d+',
    'slug' => '(?:-[^\/]*)',
];

// Product access is enforced per product type by ProductPolicy.
Route::middleware('can:digitalProducts-manageProducts')->group(function () use ($idSlugParams) {
    Route::get('digital-products/products/{productTypeHandle}/new', CreateProductController::class);
    Route::get('digital-products/products/{productTypeHandle}/{id}{slug?}', EditElementController::class)->where($idSlugParams);
    Route::get('digital-products/products/{productTypeHandle?}', [ProductsController::class, 'index']);
});

Route::middleware('can:digitalProducts-manageLicenses')->group(function () use ($idSlugParams) {
    Route::get('digital-products/licenses/new', CreateLicenseController::class)
        ->name('digital-products.licenses.new');
    Route::get('digital-products/licenses/{id}{slug?}', EditElementController::class)->where($idSlugParams);
    Route::get('digital-products/licenses', ElementTypeIndexController::class)
        ->defaults('elementType', License::class)
        ->defaults('newElementRoute', 'digital-products.licenses.new');
});

Route::middleware('can:digitalProducts-manageProductTypes')->group(function () {
    Route::get('digital-products/producttypes', [ProductTypesController::class, 'index']);
    Route::get('digital-products/producttypes/new', [ProductTypesController::class, 'create']);
    Route::get('digital-products/producttypes/{productTypeId}', [ProductTypesController::class, 'edit'])->whereNumber('productTypeId');
});

Route::middleware(RequireAdmin::class)->group(function () {
    Route::get('digital-products/settings', [GeneralSettingsController::class, 'edit']);
    Route::get('digital-products/settings/licenses', [LicenseFieldsController::class, 'edit']);
});
