# Release Notes for Digital Products 6.0 (WIP)

> [!WARNING]
> Digital Products 6 requires Craft CMS 6, Craft Commerce 6 and PHP 8.5. Class names, events, services and some action URLs changed. Read [Upgrading from Craft 5](README.md#upgrading-from-craft-5) before upgrading.

### General

- Digital Products now requires Craft CMS 6, Craft Commerce 6 and PHP 8.5.
- Digital Products now runs on native Craft 6 and Commerce 6 APIs, without `craftcms/yii2-adapter`.
- Sites must be on Digital Products 4.0.0 or later before upgrading.
- When Commerce provides digital products itself and this plugin’s data has been migrated into it with `commerce:digital-products:migrate`, the plugin no longer registers its GraphQL types and queries, `craft.digitalProducts` or `craft:resave:digital-products`, which Commerce registers under the same names.

### Control Panel

- The product, license, product type and settings screens are now native Craft 6 screens.
- Products and licenses now use Craft’s element indexes and element editor.
- Product previews and sharing now use Craft’s element preview and sharing.
- Products with licenses can no longer be deleted.

### Console

- Renamed `resave/digital-products` to `craft:resave:digital-products`. `craft:resave/digital-products` still works as an alias.

### Database

- Added the `2026_11_10_000000_upgrade_from_craft_5` migration, which applies the 5.x schema changes to sites coming from 4.x and rewrites stored `craft\digitalproducts\…` class names in the database and project config.
- Removed the `m220914_100809_fix_soft_deleted`, `m240620_150738_content_refactor_elements`, `m260526_000000_drop_sku_price_columns` and `m260526_000001_nullable_license_product` migrations, which the new migration replaces.

### Extensibility

- The namespace changed from `craft\digitalproducts` to `CraftCms\DigitalProducts`.
- Added `CraftCms\DigitalProducts\Plugin`, which replaces `craft\digitalproducts\Plugin`.
- Added `CraftCms\DigitalProducts\Elements\Product`, which replaces `craft\digitalproducts\elements\Product`.
- Added `CraftCms\DigitalProducts\Elements\License`, which replaces `craft\digitalproducts\elements\License`.
- Added `CraftCms\DigitalProducts\Elements\Queries\ProductQuery`, which replaces `craft\digitalproducts\elements\db\ProductQuery`.
- Added `CraftCms\DigitalProducts\Elements\Queries\LicenseQuery`, which replaces `craft\digitalproducts\elements\db\LicenseQuery`.
- Added `CraftCms\DigitalProducts\Fields\Products`, which replaces `craft\digitalproducts\fields\Products`.
- Added `CraftCms\DigitalProducts\FieldLayoutElements\ProductTitleField`, which replaces `craft\digitalproducts\fieldlayoutelements\ProductTitleField`.
- Added `CraftCms\DigitalProducts\Data\ProductType`, which replaces `craft\digitalproducts\models\ProductType`.
- Added `CraftCms\DigitalProducts\Data\ProductTypeSite`, which replaces `craft\digitalproducts\models\ProductTypeSite`.
- Added `CraftCms\DigitalProducts\Settings`, which replaces `craft\digitalproducts\models\Settings`.
- Added `CraftCms\DigitalProducts\Models\License`, `Product`, `ProductType` and `ProductTypeSite`, which replace the `craft\digitalproducts\records\*` classes.
- Added `CraftCms\DigitalProducts\ProductTypes`, which replaces `craft\digitalproducts\services\ProductTypes`.
- Added `CraftCms\DigitalProducts\Licenses`, which replaces `craft\digitalproducts\services\Licenses`.
- Added `CraftCms\DigitalProducts\Database\Table`, which replaces `craft\digitalproducts\db\Table`.
- Added `CraftCms\DigitalProducts\Support\Gql`, which replaces `craft\digitalproducts\helpers\Gql`.
- Added `CraftCms\DigitalProducts\Support\ProjectConfigData`, which replaces `craft\digitalproducts\helpers\ProjectConfigData`.
- Added `CraftCms\DigitalProducts\Twig\DigitalProductsVariable`, which replaces `craft\digitalproducts\variables\DigitalProducts`.
- Added `CraftCms\DigitalProducts\Gql\Arguments\Elements\Product`, `Interfaces\Elements\Product`, `Queries\Product`, `Resolvers\Elements\Product`, `Types\Elements\Product` and `Types\Generators\ProductType`, which replace the `craft\digitalproducts\gql\…` classes.
- Added `CraftCms\DigitalProducts\Events\ProductTypeSaving`, which replaces `craft\digitalproducts\services\ProductTypes::EVENT_BEFORE_SAVE_PRODUCTTYPE`.
- Added `CraftCms\DigitalProducts\Events\ProductTypeSaved`, which replaces `craft\digitalproducts\services\ProductTypes::EVENT_AFTER_SAVE_PRODUCTTYPE`.
- Added `CraftCms\DigitalProducts\Events\LicenseKeyGenerating`, which replaces `craft\digitalproducts\elements\License::EVENT_GENERATE_LICENSE_KEY`.
- Added `CraftCms\DigitalProducts\DeletionBlockers\LicensesDeletionBlocker`.
- Added `CraftCms\DigitalProducts\Listeners\IssueLicensesOnOrderCompleted`, `IssueLicensesOnOrderPaid`, `PreventGuestPayment` and `AssignLicensesOnUserActivation`, which replace `Licenses::handleCompletedOrder()`, `maybePreventPayment()` and `handleUserActivation()`.
- Added `CraftCms\DigitalProducts\Policies\ProductPolicy` and `LicensePolicy`.
- Added `CraftCms\DigitalProducts\Validation\ProductRules`, `ProductTypeRules` and `LicenseRules`.
- Added `CraftCms\DigitalProducts\Console\Commands\ResaveDigitalProductsCommand`.
- Added `CraftCms\DigitalProducts\Http\Controllers\*`, which replace the `craft\digitalproducts\controllers\*` classes.
- `CraftCms\DigitalProducts\Licenses::licenseProductByOrder()` now returns the new `License`, or `null`, instead of a `bool`.
- Settings validation rules are now returned by `CraftCms\DigitalProducts\Settings::getRules()` instead of `rules()`.
- Services are now resolved from the container, such as `app(CraftCms\DigitalProducts\ProductTypes::class)`, instead of from the plugin instance.
- The `digital-products/products/create` action is now `digital-products/products/create/{productTypeHandle}` and only accepts POST requests.
- The `digital-products/product-types/delete` action now expects a JSON request with `id`.
- Removed `craft\digitalproducts\services\Products`. Its site-saved handling is now `CraftCms\DigitalProducts\Listeners\SiteSavedListener`.
- Removed `craft\digitalproducts\elements\actions\DeleteProduct`. Craft’s Delete element action is used instead.
- Removed `craft\digitalproducts\web\assets\cp\Bundle` and the plugin’s control panel Twig templates.
- Removed the `digital-products/licenses/edit`, `licenses/save`, `licenses/delete`, `products/delete-product`, `products/preview-product`, `products/share-product` and `products/view-shared-product` actions. Craft’s element editor, delete, preview and sharing actions are used instead.
- Removed the `EVENT_*` constants on `ProductTypes` and `License`.

### Development

- Replaced ECS with Laravel Pint (`composer check-cs`, `composer fix-cs`).
- Added Pest tests on Orchestra Testbench (`composer test`).
- Added a Testbench workbench with demo content (`composer serve`).
