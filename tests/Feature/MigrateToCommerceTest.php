<?php

use CraftCms\Cms\Field\Assets as AssetsField;
use CraftCms\Cms\Field\PlainText;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\FieldLayout\LayoutElements\CustomField;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\UserGroups;
use CraftCms\Cms\Support\Facades\UserPermissions;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\User\Data\UserGroup;
use CraftCms\Commerce\License\Elements\License as CommerceLicense;
use CraftCms\Commerce\License\Licenses as CommerceLicenses;
use CraftCms\Commerce\Plugin as Commerce;
use CraftCms\Commerce\Product\Elements\Product as CommerceProduct;
use CraftCms\Commerce\Product\Fields\Variants as VariantsField;
use CraftCms\Commerce\Product\ProductType\ProductTypes as CommerceProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\DigitalProducts\Console\Commands\MigrateToCommerceCommand;
use CraftCms\DigitalProducts\Elements\License;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\FieldLayoutElements\ProductTitleField;
use CraftCms\DigitalProducts\Fields\Products as ProductsField;
use CraftCms\DigitalProducts\Licenses;
use CraftCms\DigitalProducts\ProductTypes;
use CraftCms\DigitalProducts\Settings;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->files = new AssetsField(['name' => 'Files', 'handle' => 'files']);
    Fields::saveField($this->files) or throw new RuntimeException(json_encode($this->files->errors()->getMessages()));
    $edition = new PlainText(['name' => 'Edition', 'handle' => 'edition']);
    Fields::saveField($edition) or throw new RuntimeException(json_encode($edition->errors()->getMessages()));
    $seat = new PlainText(['name' => 'Seat', 'handle' => 'seat']);
    Fields::saveField($seat) or throw new RuntimeException(json_encode($seat->errors()->getMessages()));

    $this->productType = createProductType('ebooks');
    $this->productType->downloadFieldUid = $this->files->uid;
    $this->productType->setFieldLayout(FieldLayout::createFromConfig([
        'type' => Product::class,
        'tabs' => [[
            'name' => 'Content',
            'elements' => [
                ['type' => ProductTitleField::class],
                ['type' => CustomField::class, 'fieldUid' => $this->files->uid],
                ['type' => CustomField::class, 'fieldUid' => $edition->uid],
            ],
        ]],
    ]));
    app(ProductTypes::class)->saveProductType($this->productType) or throw new RuntimeException(json_encode($this->productType->errors()->getMessages()));

    $licenseLayout = FieldLayout::createFromConfig(['type' => License::class, 'tabs' => [[
        'name' => 'Content',
        'elements' => [['type' => CustomField::class, 'fieldUid' => $seat->uid]],
    ]]]);
    $licenseLayout->uid = 'b4c5d6e7-0000-4000-8000-000000000001';
    app(ProjectConfig::class)->set(Licenses::CONFIG_FIELDLAYOUT_KEY, [$licenseLayout->uid => $licenseLayout->getConfig()]);

    $this->product = Product::find()->id(createProduct($this->productType, ['title' => 'Field Guide', 'sku' => 'GUIDE'])->id)->one();
    $this->product->setFieldValue('edition', 'Second');
    Elements::saveElement($this->product) or throw new RuntimeException(json_encode($this->product->errors()->getMessages()));

    $this->license = new License;
    $this->license->productId = $this->product->id;
    $this->license->ownerEmail = 'customer@example.test';
    $this->license->setFieldValue('seat', '7');
    Elements::saveElement($this->license) or throw new RuntimeException(json_encode($this->license->errors()->getMessages()));

    $this->relationField = new ProductsField(['name' => 'Related', 'handle' => 'related']);
    Fields::saveField($this->relationField) or throw new RuntimeException('Could not save field');

    $this->group = new UserGroup(['name' => 'Editors', 'handle' => 'editors']);
    UserGroups::saveGroup($this->group) or throw new RuntimeException('Could not save group');
    UserPermissions::saveGroupPermissions($this->group->id, ['accessCp', 'digitalProducts-manageProducts', "digitalProducts-manageProductType:{$this->productType->uid}", 'digitalProducts-manageLicenses']);

    $this->user = Users::ensureUserByEmail('editor@example.test');
    UserPermissions::saveUserPermissions($this->user->id, ['digitalProducts-manageLicenses']);

    app(Settings::class)->licenseKeyLength = 30;
});

afterEach(function () {
    foreach (glob(database_path('migrations/*_'.MigrateToCommerceCommand::MIGRATION_NAME.'.php')) as $file) {
        unlink($file);
    }
});

it('moves digital product types, products and licenses into Commerce', function () {
    $this->artisan('digital-products:migrate-to-commerce', ['--force' => true])->assertSuccessful();

    expect(glob(database_path('migrations/*_'.MigrateToCommerceCommand::MIGRATION_NAME.'.php')))->toHaveCount(1);

    $productType = app(CommerceProductTypes::class)->getProductTypeByUid($this->productType->uid);
    expect($productType)->not->toBeNull()
        ->and($productType->handle)->toBe('ebooks')
        ->and($productType->hasLicenses)->toBeTrue()
        ->and($productType->maxVariants)->toBe(1)
        ->and($productType->getLicenseDownloadField()?->uid)->toBe($this->files->uid)
        ->and($productType->getSiteSettings()[$this->product->siteId]->uriFormat)->toBe('ebooks/{slug}');

    // The digital product is now a variant with the same ID and content, owned by a product with its title and URI
    $variant = Variant::find()->id($this->product->id)->status(null)->one();
    expect($variant)->not->toBeNull()
        ->and($variant->issuesLicense)->toBeTrue()
        ->and($variant->getSku())->toBe('GUIDE')
        ->and($variant->getFieldValue('edition'))->toBe('Second')
        ->and($variant->getOwner())->toBeInstanceOf(CommerceProduct::class)
        ->and($variant->getOwner()->title)->toBe('Field Guide')
        ->and($variant->getOwner()->uri)->toBe('ebooks/field-guide');

    $license = CommerceLicense::find()->id($this->license->id)->one();
    expect($license)->not->toBeNull()
        ->and($license->licenseKey)->toBe($this->license->licenseKey)
        ->and($license->variantId)->toBe($this->product->id)
        ->and($license->getFieldValue('seat'))->toBe('7');

    expect(DB::table('digitalproducts_products')->count())->toBe(0)
        ->and(DB::table('digitalproducts_licenses')->count())->toBe(0)
        ->and(app(CommerceLicenses::class)->isEnabled())->toBeTrue()
        ->and(Fields::getFieldById($this->relationField->id))->toBeInstanceOf(VariantsField::class)
        ->and(Commerce::getInstance()->getSettings()->licenseKeyLength)->toBe(30);

    $permissions = fn (string $table, string $column, int $id) => DB::table('userpermissions as p')
        ->join("$table as x", 'x.permissionId', '=', 'p.id')
        ->where("x.$column", $id)
        ->pluck('p.name')
        ->map(fn (string $name) => strtolower($name))
        ->all();

    $uid = strtolower($this->productType->uid);
    expect($permissions('userpermissions_usergroups', 'groupId', $this->group->id))->toEqualCanonicalizing([
        'accesscp', 'commerce-managelicenses',
        "commerce-viewproducttype:$uid", "commerce-createproducttype:$uid", "commerce-saveproducttype:$uid", "commerce-deleteproducttype:$uid",
    ])
        ->and($permissions('userpermissions_users', 'userId', $this->user->id))->toBe(['commerce-managelicenses']);
});

it('only runs where admin changes are allowed', function () {
    app(ProjectConfig::class)->readOnly = true;

    $this->artisan('digital-products:migrate-to-commerce', ['--force' => true])->assertFailed();

    expect(app(CommerceProductTypes::class)->getProductTypeByUid($this->productType->uid))->toBeNull()
        ->and(DB::table('digitalproducts_products')->count())->toBe(1)
        ->and(glob(database_path('migrations/*_'.MigrateToCommerceCommand::MIGRATION_NAME.'.php')))->toBeEmpty();
});
