# Upgrading to Digital Products 6.0 (Craft 6)

This guide lists what changed in 6.0 and what you need to update in your project, templates and custom code.

Digital products are becoming a first-party feature of Commerce 6. Commerce 6 ships a command that moves this plugin's data into Commerce's own tables; until you run it, Digital Products 6.0 keeps working on Craft 6 and Commerce 6.

## Requirements

- Craft CMS 6.0 or later
- Craft Commerce 6.0 or later
- PHP 8.5 or later
- `craftcms/yii2-adapter` is **not** needed. The plugin is built only on native Craft 6 and Commerce 6 APIs.

## Upgrade steps

1. Update Digital Products to the latest 4.x or 5.x release on Craft 5 first (6.0 requires 4.0.0 or later).
2. Upgrade your project to Craft 6 and Commerce 6.
3. Update the plugin: `composer require craftcms/digital-products:^6.0`.
4. Run the migrations: `php artisan craft:migrate:all`. The `2026_11_10_000000_upgrade_from_craft_5` migration:
   - applies the 5.x schema changes if the site comes from 4.x: copies `digitalproducts_products.sku` and `price` into `commerce_purchasables.sku` and `commerce_purchasables_stores.basePrice`, drops both columns, and makes `digitalproducts_licenses.productId` and `ownerEmail` nullable;
   - rewrites the stored Craft 5 class names (table below) in `elements.type`, `fieldlayouts.type`, `fields.type`, `commerce_discount_purchasables.purchasableType`, `commerce_sale_purchasables.purchasableType`, the JSON in `fieldlayouts.config` and `fields.settings` (field layout elements, conditions), and everywhere in project config (field types, field layouts, `elementSources` keys, conditions).
5. Commit the updated project config, then review the breaking changes below.

Tables, columns, element IDs, project config keys (`digital-products.productTypes.{uid}`, `digital-products.licenseFieldLayouts`), permission names, setting names and the plugin handle are unchanged.

## Breaking changes

### Classes and namespaces

The namespace moved from `craft\digitalproducts` to `CraftCms\DigitalProducts`.

| Before | After |
| --- | --- |
| `craft\digitalproducts\Plugin` | `CraftCms\DigitalProducts\Plugin` |
| `craft\digitalproducts\elements\Product` | `CraftCms\DigitalProducts\Elements\Product` |
| `craft\digitalproducts\elements\License` | `CraftCms\DigitalProducts\Elements\License` |
| `craft\digitalproducts\elements\db\ProductQuery` | `CraftCms\DigitalProducts\Elements\Queries\ProductQuery` |
| `craft\digitalproducts\elements\db\LicenseQuery` | `CraftCms\DigitalProducts\Elements\Queries\LicenseQuery` |
| `craft\digitalproducts\fields\Products` | `CraftCms\DigitalProducts\Fields\Products` |
| `craft\digitalproducts\fieldlayoutelements\ProductTitleField` | `CraftCms\DigitalProducts\FieldLayoutElements\ProductTitleField` |
| `craft\digitalproducts\models\ProductType` | `CraftCms\DigitalProducts\Data\ProductType` |
| `craft\digitalproducts\models\ProductTypeSite` | `CraftCms\DigitalProducts\Data\ProductTypeSite` |
| `craft\digitalproducts\models\Settings` | `CraftCms\DigitalProducts\Settings` |
| `craft\digitalproducts\records\*` (ActiveRecord) | `CraftCms\DigitalProducts\Models\*` (Eloquent) |
| `craft\digitalproducts\services\ProductTypes` | `CraftCms\DigitalProducts\ProductTypes` |
| `craft\digitalproducts\services\Licenses` | `CraftCms\DigitalProducts\Licenses` |
| `craft\digitalproducts\services\Products` | Removed. Its site-saved handling is `CraftCms\DigitalProducts\Listeners\SiteSavedListener`. |
| `craft\digitalproducts\db\Table` | `CraftCms\DigitalProducts\Database\Table` |
| `craft\digitalproducts\helpers\Gql` | `CraftCms\DigitalProducts\Support\Gql` |
| `craft\digitalproducts\helpers\ProjectConfigData` | `CraftCms\DigitalProducts\Support\ProjectConfigData` |
| `craft\digitalproducts\variables\DigitalProducts` | `CraftCms\DigitalProducts\Twig\DigitalProductsVariable` |
| `craft\digitalproducts\gql\…\Product` | `CraftCms\DigitalProducts\Gql\…\Product` (same sub-namespaces, capitalized) |
| `craft\digitalproducts\events\ProductTypeEvent` | `CraftCms\DigitalProducts\Events\ProductTypeSaving` / `ProductTypeSaved` |
| `craft\digitalproducts\events\GenerateKeyEvent` | `CraftCms\DigitalProducts\Events\LicenseKeyGenerating` |
| `craft\digitalproducts\controllers\*` | `CraftCms\DigitalProducts\Http\Controllers\*` |
| `craft\digitalproducts\elements\actions\DeleteProduct` | Removed. Craft's own Delete action is used, and products with licenses are blocked by `CraftCms\DigitalProducts\DeletionBlockers\LicensesDeletionBlocker`. |
| `craft\digitalproducts\web\assets\cp\Bundle` | Removed. The control panel screens are native Craft 6 screens. |

Services come from the container instead of the plugin instance:

```php
// Before
DigitalProducts::getInstance()->getProductTypes()->getAllProductTypes();
DigitalProducts::getInstance()->getLicenses()->isLicenseKeyUnique($key);

// After
app(\CraftCms\DigitalProducts\ProductTypes::class)->getAllProductTypes();
app(\CraftCms\DigitalProducts\Licenses::class)->isLicenseKeyUnique($key);
```

The product type service keeps its method names (`getAllProductTypes()`, `getEditableProductTypes()`, `getProductTypeById()`, `getProductTypeByHandle()`, `getProductTypeByUid()`, `saveProductType()`, `deleteProductTypeById()`, `isProductTypeTemplateValid()`). `Licenses::licenseProductByOrder()` now returns the `License` (or `null`) instead of a `bool`. `Licenses::handleCompletedOrder()`, `maybePreventPayment()` and `handleUserActivation()` were static Yii event handlers; they are now the listeners `IssueLicensesOnOrderCompleted`, `IssueLicensesOnOrderPaid`, `PreventGuestPayment` and `AssignLicensesOnUserActivation` in `CraftCms\DigitalProducts\Listeners`.

### Events

Yii-style events were replaced by Laravel events. The `EVENT_*` constants are removed.

| Before | After |
| --- | --- |
| `ProductTypes::EVENT_BEFORE_SAVE_PRODUCTTYPE` | `CraftCms\DigitalProducts\Events\ProductTypeSaving` |
| `ProductTypes::EVENT_AFTER_SAVE_PRODUCTTYPE` | `CraftCms\DigitalProducts\Events\ProductTypeSaved` |
| `License::EVENT_GENERATE_LICENSE_KEY` | `CraftCms\DigitalProducts\Events\LicenseKeyGenerating` |

Before:

```php
use craft\digitalproducts\events\ProductTypeEvent;
use craft\digitalproducts\services\ProductTypes;
use yii\base\Event;

Event::on(ProductTypes::class, ProductTypes::EVENT_AFTER_SAVE_PRODUCTTYPE, function (ProductTypeEvent $event) {
    // $event->productType, $event->isNew
});
```

After:

```php
use CraftCms\DigitalProducts\Events\ProductTypeSaved;
use Illuminate\Support\Facades\Event;

Event::listen(function (ProductTypeSaved $event): void {
    // $event->productType, $event->isNew
});
```

`ProductTypeSaving` is dispatched before validation, and listeners may change `$event->productType`. `ProductTypeSaved` is dispatched once the project config change has been applied. Neither has a `sender`.

Supplying a license key, before:

```php
use craft\digitalproducts\elements\License;
use craft\digitalproducts\events\GenerateKeyEvent;
use yii\base\Event;

Event::on(License::class, License::EVENT_GENERATE_LICENSE_KEY, function (GenerateKeyEvent $event) {
    $event->licenseKey = myKeyFor($event->license);
});
```

After:

```php
use CraftCms\DigitalProducts\Events\LicenseKeyGenerating;
use CraftCms\DigitalProducts\Licenses;
use Illuminate\Support\Facades\Event;

Event::listen(function (LicenseKeyGenerating $event): void {
    do {
        $key = myKeyFor($event->license);
    } while (! app(Licenses::class)->isLicenseKeyUnique($key));

    $event->licenseKey = $key;
});
```

Leave `$licenseKey` as `null` to have the plugin generate one from `licenseKeyCharacters` and `licenseKeyLength`. As before, the key is generated when a license first has a product. A license created in the control panel without a product holds a placeholder until then, and `License::hasLicenseKey()` returns `false` for it.

### Settings and config

The settings are unchanged (`autoAssignUserOnPurchase`, `autoAssignLicensesOnUserRegistration`, `licenseKeyCharacters`, `licenseKeyLength`, `requireLoggedInUser`, `generateLicenseOnOrderPaid`) and still live in project config at `plugins.digital-products.settings`. The settings model is `CraftCms\DigitalProducts\Settings`, which can be injected: `app(\CraftCms\DigitalProducts\Settings::class)`. Validation rules are `getRules()` (Laravel rules) instead of `rules()`.

### Routes and action URLs

Kept:

- `actions/digital-products/download/download` with `licenseKey` and `assetId` (GET or POST, anonymous; the license key is the credential).
- `actions/digital-products/settings/save-settings` and `actions/digital-products/settings/save-license-field-layout`.
- The CP URLs: `digital-products/products[/{typeHandle}]`, `digital-products/products/{typeHandle}/new`, `digital-products/products/{typeHandle}/{id}-{slug}`, `digital-products/licenses`, `digital-products/licenses/new`, `digital-products/licenses/{id}`, `digital-products/producttypes[/new|/{id}]`, `digital-products/settings`, `digital-products/settings/licenses`.

Changed:

| Before | After |
| --- | --- |
| `digital-products/product-types/save` | Same path. The form posts `name`, `handle`, `skuFormat`, `sites[{siteHandle}][enabled\|uriFormat\|template]` and `fieldLayout`. |
| `digital-products/product-types/delete` | Same path. Expects a JSON request with `id`. |
| `digital-products/products/create?productTypeHandle=…` | `actions/digital-products/products/create/{productTypeHandle}` (POST), or the `digital-products/products/{typeHandle}/new` CP URL |
| `digital-products/licenses/create` | Same path (POST), or the `digital-products/licenses/new` CP URL |
| `digital-products/licenses/edit`, `licenses/save`, `licenses/delete` | Craft's element editor (`elements/save`, element delete) |
| `digital-products/products/delete-product` | Craft's element delete; blocked while licenses exist |
| `digital-products/products/preview-product`, `share-product`, `view-shared-product` | Craft's element preview and sharing, using the product type's site template |

### Templates and Twig

`craft.digitalProducts` still offers `products()`, `licenses()`, `getProductTypes()` and `getPlugin()`. Element query params are unchanged. The plugin's own CP Twig templates were removed; the control panel screens are native Craft 6 screens.

### Control panel

The product type, settings and license field layout screens are native Craft 6 screens under Digital Products › Settings (General, License Fields, Product Types). Products and licenses use Craft's element indexes and element editor.

### GraphQL

Unchanged: the `digitalProducts` and `digitalProduct` queries, the `DigitalProductInterface` interface, `{productTypeHandle}_Product` type names, and the `digitalProductTypes.{uid}:read` schema permissions.

### Console

`craft resave/digital-products` is now `php artisan craft:resave:digital-products` (alias `craft:resave/digital-products`), with `--type` and Craft's standard resave options.

### Permissions

Unchanged: `digitalProducts-manageProducts` (with a nested `digitalProducts-manageProductType:{uid}` per product type), `digitalProducts-manageLicenses` and `digitalProducts-manageProductTypes`. The settings screens and setting actions still require an admin.

### Database and migrations

The schema is the 5.x schema. A fresh install creates it with `database/migrations/Install.php`. The 4.x/5.x Yii migrations (`m220914_100809_fix_soft_deleted`, `m240620_150738_content_refactor_elements`, `m260526_000000_drop_sku_price_columns`, `m260526_000001_nullable_license_product`) are replaced by the single `2026_11_10_000000_upgrade_from_craft_5` migration described above. It is safe on a 5.x schema: the 4.x steps only run when the old columns are present.

Uninstalling the plugin drops its four tables and removes the `digital-products` project config. It never deletes elements, and it works when the tables are already empty, as Commerce's migration command leaves them.

## Code style and development

ECS was replaced by Laravel Pint (`composer check-cs`, `composer fix-cs`). Tests are Pest on Orchestra Testbench (`composer test`), and `composer serve` builds and serves a workbench with Craft, Commerce and the plugin installed.

## Decisions recorded during the upgrade

- End state B: no `craftcms/yii2-adapter`. The plugin uses only `CraftCms\Cms\…` and `CraftCms\Commerce\…` classes.
- Own events: a clean break to Laravel events, with no compatibility layer for the old `EVENT_*` constants.
- `schemaVersion` is `6.0.0.0`. `minVersionRequired` is `4.0.0`, so sites must be on 4.x or 5.x before upgrading.
- A new license created in the control panel holds a placeholder key until it has a product, so a draft-like license with no product can be saved; the real key is generated on the first save with a product.

## Need help?

Open an issue at <https://github.com/craftcms/commerce-digital-products/issues>.
