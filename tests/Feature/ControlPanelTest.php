<?php

use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\Models\User as UserModel;
use CraftCms\Cms\User\UserPermissions;
use CraftCms\DigitalProducts\Elements\License;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\Plugin;
use CraftCms\DigitalProducts\ProductTypes;
use CraftCms\DigitalProducts\Settings;

function actingAsAdmin(): void
{
    test()->actingAs(UserModel::find(1));
}

/**
 * @param  string[]  $permissions
 */
function actingAsUserWith(array $permissions): User
{
    $user = new User(['username' => 'editor', 'email' => 'editor@example.test']);
    Elements::saveElement($user) or throw new RuntimeException(json_encode($user->errors()->getMessages()));
    app(UserPermissions::class)->saveUserPermissions($user->id, ['accessCp', 'accessPlugin-digital-products', ...$permissions]);
    test()->actingAs(UserModel::find($user->id));

    return $user;
}

it('renders the control panel pages for an admin', function (string $uri) {
    $productType = createProductType();
    $product = createProduct($productType);
    $license = new License;
    $license->productId = $product->id;
    $license->ownerEmail = 'customer@example.test';
    Elements::saveElement($license);

    actingAsAdmin();

    $uri = strtr($uri, ['{typeId}' => $productType->id, '{productId}' => $product->id, '{licenseId}' => $license->id]);

    $this->get("/admin/$uri")->assertOk();
})->with([
    'products index' => 'digital-products/products',
    'products of a type' => 'digital-products/products/software',
    'product edit' => 'digital-products/products/software/{productId}',
    'license index' => 'digital-products/licenses',
    'license edit' => 'digital-products/licenses/{licenseId}',
    'general settings' => 'digital-products/settings',
    'license fields' => 'digital-products/settings/licenses',
    'product types' => 'digital-products/producttypes',
    'new product type' => 'digital-products/producttypes/new',
    'edit product type' => 'digital-products/producttypes/{typeId}',
]);

it('redirects the section root to products, or to licenses when there are no product types', function () {
    actingAsAdmin();

    $this->get('/admin/digital-products')->assertRedirectContains('digital-products/licenses');

    createProductType();

    $this->get('/admin/digital-products')->assertRedirectContains('digital-products/products');

    actingAsUserWith(['accessCp', 'digitalProducts-manageLicenses']);

    $this->get('/admin/digital-products')->assertRedirectContains('digital-products/licenses');
});

it('creates a product draft and a license from the control panel', function () {
    createProductType();
    actingAsAdmin();

    $this->get('/admin/digital-products/products/software/new')->assertRedirectContains('fresh=1');
    expect(Product::find()->drafts()->status(null)->count())->toBe(1);

    $this->get('/admin/digital-products/licenses/new')->assertRedirectContains('fresh=1');
    expect(License::find()->status(null)->count())->toBe(1);
});

it('saves a product type from the control panel', function () {
    actingAsAdmin();

    $this->postJson('/admin/actions/digital-products/product-types/save', [
        'name' => 'Courses',
        'handle' => 'courses',
        'skuFormat' => 'C-{slug}',
        'sites' => ['default' => ['enabled' => true, 'uriFormat' => 'courses/{slug}', 'template' => 'courses/_entry']],
    ])->assertOk();

    $productType = app(ProductTypes::class)->getProductTypeByHandle('courses');
    expect($productType)->not->toBeNull()
        ->and($productType->skuFormat)->toBe('C-{slug}')
        ->and(array_values($productType->getSiteSettings())[0]->uriFormat)->toBe('courses/{slug}');
});

it('saves the general settings', function () {
    actingAsAdmin();

    $this->postJson('/admin/actions/digital-products/settings/save-settings', [
        'settings' => ['licenseKeyLength' => 32, 'licenseKeyCharacters' => 'XYZ', 'requireLoggedInUser' => false],
    ])->assertOk();

    expect(app(ProjectConfig::class)->get('plugins.digital-products.settings.licenseKeyLength'))->toBe(32)
        ->and(app(Settings::class)->licenseKeyCharacters)->toBe('XYZ')
        ->and(app(Plugin::class)->getSettings()->requireLoggedInUser)->toBeFalse();
});

it('saves the license field layout to project config', function () {
    actingAsAdmin();

    $this->postJson('/admin/actions/digital-products/settings/save-license-field-layout', [
        'fieldLayout' => json_encode(['tabs' => [['name' => 'Content', 'elements' => []]]]),
    ])->assertOk();

    expect(app(ProjectConfig::class)->get('digital-products.licenseFieldLayouts'))->toBeArray()->toHaveCount(1);
});

it('denies users without the plugin permissions', function () {
    createProductType();
    actingAsUserWith([]);

    $this->get('/admin/digital-products/products')->assertForbidden();
    $this->get('/admin/digital-products/licenses')->assertForbidden();
    $this->get('/admin/digital-products/settings')->assertForbidden();
    $this->get('/admin/digital-products/producttypes')->assertForbidden();
});

it('limits product access to the permitted product types', function () {
    $software = createProductType('software');
    $ebooks = createProductType('ebooks');
    $book = createProduct($ebooks);

    actingAsUserWith(['digitalProducts-manageProducts', "digitalProducts-manageProductType:$software->uid"]);

    $this->get('/admin/digital-products/products')->assertOk();
    $this->get('/admin/digital-products/products/software/new')->assertRedirectContains('fresh=1');
    $this->get('/admin/digital-products/products/ebooks/new')->assertForbidden();
    $this->get("/admin/digital-products/products/ebooks/$book->id")->assertForbidden();
});
