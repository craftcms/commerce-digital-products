<?php

declare(strict_types=1);

use CraftCms\Cms\Http\Middleware\RequireAdmin;
use CraftCms\DigitalProducts\Http\Controllers\CreateLicenseController;
use CraftCms\DigitalProducts\Http\Controllers\CreateProductController;
use CraftCms\DigitalProducts\Http\Controllers\DownloadController;
use CraftCms\DigitalProducts\Http\Controllers\Settings\GeneralSettingsController;
use CraftCms\DigitalProducts\Http\Controllers\Settings\LicenseFieldsController;
use CraftCms\DigitalProducts\Http\Controllers\Settings\ProductTypesController;
use Illuminate\Support\Facades\Route;

// Routes here are prefixed with `actions/digital-products/`.

// Anonymous by design: the license key is the credential.
Route::match(['get', 'post'], 'download/download', DownloadController::class);

Route::middleware('auth')->group(function () {
    Route::middleware('can:digitalProducts-manageProducts')
        ->post('products/create/{productTypeHandle}', CreateProductController::class);

    Route::middleware('can:digitalProducts-manageLicenses')
        ->post('licenses/create', CreateLicenseController::class);

    Route::middleware('can:digitalProducts-manageProductTypes')->group(function () {
        Route::post('product-types/save', [ProductTypesController::class, 'store']);
        Route::match(['post', 'delete'], 'product-types/delete', [ProductTypesController::class, 'destroy']);
    });

    Route::middleware(RequireAdmin::class)->group(function () {
        Route::post('settings/save-settings', [GeneralSettingsController::class, 'store']);
        Route::post('settings/save-license-field-layout', [LicenseFieldsController::class, 'store']);
    });
});
