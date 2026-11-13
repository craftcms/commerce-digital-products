<p align="center"><img src="./resources/icon.svg" width="100" height="100" alt="Digital Products icon"></p>

<h1 align="center">Digital Products</h1>

This plugin makes it possible to sell licenses for digital products with [Craft Commerce](https://craftcms.com/commerce).

## Requirements

Digital Products 6 requires Craft CMS 6, Craft Commerce 6 and PHP 8.5 or later. It doesn't need `craftcms/yii2-adapter`.

Upgrading from Digital Products 4.x or 5.x on Craft 5? Read [Upgrading from Craft 5](#upgrading-from-craft-5).

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Digital Products”. Then click on the “Install” button in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require craftcms/digital-products

# tell Craft to install the plugin
php artisan craft:plugin:install digital-products
```

## Upgrading from Craft 5

Digital Products 6 runs on native Craft 6 and Commerce 6 APIs. Your data, project config, permissions, settings, Twig variable, GraphQL schema and download URLs carry over. PHP code that uses the plugin's classes, services or events needs updating.

### Upgrade steps

1. Update Digital Products to the latest 4.x or 5.x release while the site is still on Craft 5. Digital Products 6 requires 4.0.0 or later.
2. Upgrade the project to Craft 6 and Commerce 6.
3. Update the plugin: `composer require craftcms/digital-products:^6.0`.
4. Run the migrations: `php artisan craft:migrate:all`.
5. Commit the updated project config.
6. Update your custom code using the sections below.

The `2026_11_10_000000_upgrade_from_craft_5` migration does two things:

- On sites coming from 4.x, it applies the 5.x schema changes. It copies `digitalproducts_products.sku` and `price` into `commerce_purchasables.sku` and `commerce_purchasables_stores.basePrice`, drops both columns, and makes `digitalproducts_licenses.productId` and `ownerEmail` nullable. On a 5.x schema these steps are skipped.
- It rewrites the stored Craft 5 class names (see [Classes](#classes)) in `elements.type`, `fieldlayouts.type`, `fields.type`, `commerce_discount_purchasables.purchasableType`, `commerce_sale_purchasables.purchasableType`, the field layout and field settings JSON, and project config (field types, field layouts, element sources and conditions).

It replaces the 4.x/5.x migrations `m220914_100809_fix_soft_deleted`, `m240620_150738_content_refactor_elements`, `m260526_000000_drop_sku_price_columns` and `m260526_000001_nullable_license_product`.

These stay the same: the `digitalproducts_*` tables and their columns, element IDs, the `digital-products.productTypes.{uid}` and `digital-products.licenseFieldLayouts` project config keys, permission names, setting names and the `digital-products` plugin handle.

### Classes

The namespace changed from `craft\digitalproducts` to `CraftCms\DigitalProducts`. Update your `use` statements:

| Craft 5 | Craft 6 |
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
| `craft\digitalproducts\services\Products` | Removed. Site saves are handled by `CraftCms\DigitalProducts\Listeners\SiteSavedListener`. |
| `craft\digitalproducts\db\Table` | `CraftCms\DigitalProducts\Database\Table` |
| `craft\digitalproducts\helpers\Gql` | `CraftCms\DigitalProducts\Support\Gql` |
| `craft\digitalproducts\helpers\ProjectConfigData` | `CraftCms\DigitalProducts\Support\ProjectConfigData` |
| `craft\digitalproducts\variables\DigitalProducts` | `CraftCms\DigitalProducts\Twig\DigitalProductsVariable` |
| `craft\digitalproducts\gql\…\Product` | `CraftCms\DigitalProducts\Gql\…\Product` (same sub-namespaces, capitalized) |
| `craft\digitalproducts\events\ProductTypeEvent` | `CraftCms\DigitalProducts\Events\ProductTypeSaving` and `ProductTypeSaved` |
| `craft\digitalproducts\events\GenerateKeyEvent` | `CraftCms\DigitalProducts\Events\LicenseKeyGenerating` |
| `craft\digitalproducts\controllers\*` | `CraftCms\DigitalProducts\Http\Controllers\*` |
| `craft\digitalproducts\elements\actions\DeleteProduct` | Removed. Craft's Delete action is used, and `CraftCms\DigitalProducts\DeletionBlockers\LicensesDeletionBlocker` blocks deleting products that have licenses. |
| `craft\digitalproducts\web\assets\cp\Bundle` | Removed. |

### Services

Get services from the container instead of the plugin instance:

```php
// Craft 5
DigitalProducts::getInstance()->getProductTypes()->getAllProductTypes();
DigitalProducts::getInstance()->getLicenses()->isLicenseKeyUnique($key);

// Craft 6
app(\CraftCms\DigitalProducts\ProductTypes::class)->getAllProductTypes();
app(\CraftCms\DigitalProducts\Licenses::class)->isLicenseKeyUnique($key);
```

`ProductTypes` keeps its method names: `getAllProductTypes()`, `getEditableProductTypes()`, `getProductTypeById()`, `getProductTypeByHandle()`, `getProductTypeByUid()`, `saveProductType()`, `deleteProductTypeById()` and `isProductTypeTemplateValid()`.

In `Licenses`:

- `licenseProductByOrder()` returns the new `License`, or `null`, instead of a `bool`.
- `handleCompletedOrder()`, `maybePreventPayment()` and `handleUserActivation()` were removed. The `IssueLicensesOnOrderCompleted`, `IssueLicensesOnOrderPaid`, `PreventGuestPayment` and `AssignLicensesOnUserActivation` listeners in `CraftCms\DigitalProducts\Listeners` do their work.

### Event listeners

The Yii events and their `EVENT_*` constants were removed. Listen for these Laravel events instead:

| Craft 5 | Craft 6 |
| --- | --- |
| `ProductTypes::EVENT_BEFORE_SAVE_PRODUCTTYPE` | `CraftCms\DigitalProducts\Events\ProductTypeSaving` |
| `ProductTypes::EVENT_AFTER_SAVE_PRODUCTTYPE` | `CraftCms\DigitalProducts\Events\ProductTypeSaved` |
| `License::EVENT_GENERATE_LICENSE_KEY` | `CraftCms\DigitalProducts\Events\LicenseKeyGenerating` |

A product type listener changes from this:

```php
use craft\digitalproducts\events\ProductTypeEvent;
use craft\digitalproducts\services\ProductTypes;
use yii\base\Event;

Event::on(ProductTypes::class, ProductTypes::EVENT_AFTER_SAVE_PRODUCTTYPE, function (ProductTypeEvent $event) {
    // $event->productType, $event->isNew
});
```

to this:

```php
use CraftCms\DigitalProducts\Events\ProductTypeSaved;
use Illuminate\Support\Facades\Event;

Event::listen(function (ProductTypeSaved $event): void {
    // $event->productType, $event->isNew
});
```

`ProductTypeSaving` is dispatched before validation, and listeners may change `$event->productType`. `ProductTypeSaved` is dispatched once the project config change has been applied. Neither event has a `sender`.

A license key listener changes from this:

```php
use craft\digitalproducts\elements\License;
use craft\digitalproducts\events\GenerateKeyEvent;
use yii\base\Event;

Event::on(License::class, License::EVENT_GENERATE_LICENSE_KEY, function (GenerateKeyEvent $event) {
    $event->licenseKey = myKeyFor($event->license);
});
```

to the `LicenseKeyGenerating` listener shown in [Events](#events). Leave `$event->licenseKey` as `null` to have the plugin generate a key from the `licenseKeyCharacters` and `licenseKeyLength` settings. The key is generated when a license first has a product. A license created in the control panel without a product holds a placeholder key until then, and `License::hasLicenseKey()` returns `false` for it.

### Settings

The settings and their names are unchanged: `autoAssignUserOnPurchase`, `autoAssignLicensesOnUserRegistration`, `licenseKeyCharacters`, `licenseKeyLength`, `requireLoggedInUser` and `generateLicenseOnOrderPaid`. They're still stored at `plugins.digital-products.settings`. Inject the settings model with `app(\CraftCms\DigitalProducts\Settings::class)`. Its validation rules come from `getRules()` (Laravel rules) instead of `rules()`.

### Routes and action URLs

These are unchanged:

- `actions/digital-products/download/download` with `licenseKey` and `assetId`, as GET or POST without logging in.
- `actions/digital-products/settings/save-settings` and `actions/digital-products/settings/save-license-field-layout`.
- The control panel URLs under `digital-products/products`, `digital-products/licenses`, `digital-products/producttypes` and `digital-products/settings`.

If your code posts to these actions, update it:

| Craft 5 | Craft 6 |
| --- | --- |
| `digital-products/product-types/save` | Same path. Post `name`, `handle`, `skuFormat`, `sites[{siteHandle}][enabled\|uriFormat\|template]` and `fieldLayout`. |
| `digital-products/product-types/delete` | Same path. Send a JSON request with `id`. |
| `digital-products/products/create?productTypeHandle=…` | POST to `actions/digital-products/products/create/{productTypeHandle}`, or link to `digital-products/products/{typeHandle}/new` |
| `digital-products/licenses/create` | Same path (POST), or link to `digital-products/licenses/new` |
| `digital-products/licenses/edit`, `licenses/save`, `licenses/delete` | Craft's element editor and element actions (`elements/save`, element delete) |
| `digital-products/products/delete-product` | Craft's element delete, which is blocked while the product has licenses |
| `digital-products/products/preview-product`, `share-product`, `view-shared-product` | Craft's element preview and sharing, using the product type's site template |

### Templates and the control panel

`craft.digitalProducts` still has `products()`, `licenses()`, `getProductTypes()` and `getPlugin()`, and element query params are unchanged. The plugin's control panel Twig templates were removed. Products and licenses use Craft's element indexes and element editor, and the settings screens are under Digital Products › Settings (General, License Fields, Product Types).

### GraphQL

Nothing changes: the `digitalProducts` and `digitalProduct` queries, the `DigitalProductInterface` interface, the `{productTypeHandle}_Product` type names and the `digitalProductTypes.{uid}:read` schema permissions all keep their names.

### Console commands

`craft resave/digital-products` is now `php artisan craft:resave:digital-products` (alias `craft:resave/digital-products`). It takes `--type` and Craft's standard resave options.

`php artisan digital-products:migrate-to-commerce` moves everything into Commerce. See [Moving to Commerce licenses](#moving-to-commerce-licenses).

### Permissions

The permissions are unchanged: `digitalProducts-manageProducts` with a nested `digitalProducts-manageProductType:{uid}` per product type, `digitalProducts-manageLicenses` and `digitalProducts-manageProductTypes`. The settings screens and actions still require an admin.

### Uninstalling

Uninstalling drops the plugin's four tables and removes its `digital-products` project config. It never deletes elements, and it works when the tables are already empty, as `digital-products:migrate-to-commerce` leaves them.

## Moving to Commerce licenses

Commerce 6 issues licenses itself. Instead of separate digital products, any product type can allow its variants to issue licenses, and each variant has an **Issues License** switch. Commerce keeps its license features hidden while this plugin's tables have data, so both can be installed while you move over.

### Running the migration

Back up your database, then run this where admin changes are allowed, such as your development environment:

```bash
php artisan digital-products:migrate-to-commerce
```

The command:

- creates a Commerce product type for each digital product type, with the same UID, handle, site settings and SKU format. It allows licenses, has one variant per product, and uses the digital product type's **Download Field** as its **License Download Field**. Its variant field layout is the digital product field layout without the title field, so custom field values carry over.
- moves the license field layout to Commerce, turns Digital Products relation fields into Variants fields, and swaps user group permissions for their Commerce equivalents: `digitalProducts-manageProductType:{uid}` becomes `commerce-viewProductType:{uid}`, `commerce-createProductType:{uid}`, `commerce-saveProductType:{uid}` and `commerce-deleteProductType:{uid}`, and `digitalProducts-manageLicenses` becomes `commerce-manageLicenses`.
- copies the plugin settings to Commerce's license settings.
- writes a content migration to `database/migrations/{timestamp}_migrate_digital_products_to_commerce.php` and runs it. The migration turns each digital product into the only variant of a new product, moves licenses and user permissions, and empties the plugin's tables.

Each variant keeps its digital product's element ID, SKU, price, tax category and custom field values. Line items, relations and licenses keep pointing at it. The new product gets the digital product's title, slug, URI, status and dates. Licenses keep their IDs and keys.

Commit the migration and your project config changes. Deploy them and run `php artisan craft:up` in each environment. Craft applies project config before it runs content migrations, so the product types exist before the migration moves the products and licenses into them.

Only uninstall the plugin after every environment has run the migration. Uninstalling drops the plugin's tables, so if an environment applies the uninstall before it has run the migration, its digital products and licenses are lost.

### Updating templates

Commerce doesn't provide `craft.digitalProducts`, the digital products GraphQL queries or `craft:resave:digital-products`. After migrating, a digital product is a Commerce product with one variant. The product has the title and URL. The variant has the SKU, price, custom fields and the **Issues License** setting.

| Digital Products | Commerce |
| --- | --- |
| `craft.digitalProducts.products()` | `craft.products()` (filter with `.type('handle')`) |
| `craft.digitalProducts.licenses()` | `craft.licenses()` |
| `craft.digitalProducts.getProductTypes()` | `craft.commerce.productTypes.allProductTypes` |
| `product.sku`, `product.price` | `product.defaultVariant.sku`, `product.defaultVariant.price` |
| `product.myField` (a custom field) | `product.defaultVariant.myField` |
| `purchasableId: product.id` (add to cart) | `purchasableId: product.defaultVariant.id` |
| `product.existingLicenses` | `craft.licenses().owner(currentUser).product(product).exists()` |
| `license.product` | `license.variant`. `license.product` is now the Commerce product that owns the variant. |
| `license.productName` | `license.variantName` |
| `license.product.myField` (a custom field) | `license.variant.myField` |
| `licenses.product('SKU')` | `licenses.variant('SKU')` |
| `licenses.productId(id)` | `licenses.variantId(id)`. Variants keep the digital products' IDs. |
| `.with(['product'])` on licenses | `.with(['variant'])` |
| `actionUrl('digital-products/download/download', {licenseKey: …, assetId: …})` | `license.getDownloadUrl(asset.id)`, or `actionUrl('commerce/licenses/download', {licenseKey: …, assetId: …})` |
| A Digital Products relation field | Returns variants. Use `variant.product.url` for a link. |

License query params that stay the same: `type`, `owner`, `ownerId`, `email`, `licenseKey`, `orderId`, `before` and `after`.

For example, a list of the current user's licenses with download links:

```twig
{% set licenses = craft.licenses()
  .owner(currentUser)
  .with(['variant', 'order'])
  .all() %}

{% for license in licenses %}
  {% set file = license.variant.downloadFile.one() %}
  <a href="{{ license.product.url }}">{{ license.product.title }}</a>
  {% if file %}
    <a href="{{ license.getDownloadUrl(file.id) }}">Download</a>
  {% endif %}
{% endfor %}
```

And whether the current user owns a product:

```twig
{% for product in craft.products().type('onlineCourses').all() %}
  {% if currentUser and craft.licenses().owner(currentUser).product(product).exists() %}
    You already own {{ product.title }}.
  {% else %}
    <a href="{{ product.url }}">Get it now!</a>
  {% endif %}
{% endfor %}
```

## Events

Digital Products dispatches Laravel events. Listen to them with `Illuminate\Support\Facades\Event`.

### `ProductTypeSaving` and `ProductTypeSaved`

Dispatched before a product type is validated and saved, and after the change has been applied:

```php
use CraftCms\DigitalProducts\Events\ProductTypeSaved;
use Illuminate\Support\Facades\Event;

Event::listen(function (ProductTypeSaved $event): void {
    // $event->productType, $event->isNew
});
```

### `LicenseKeyGenerating`

Lets you supply a license key instead of relying on Digital Products to generate one:

```php
use CraftCms\DigitalProducts\Events\LicenseKeyGenerating;
use CraftCms\DigitalProducts\Licenses;
use Illuminate\Support\Facades\Event;

Event::listen(function (LicenseKeyGenerating $event): void {
    do {
        $licenseKey = // custom key generation logic, using $event->license...
    } while (! app(Licenses::class)->isLicenseKeyUnique($licenseKey));

    $event->licenseKey = $licenseKey;
});
```

## Eager loading

Both licenses and products have several eager-loadable properties.

### Licenses

* `product` allows you to eager-load the product associated with the license.
* `order` allows you to eager-load the order associated with the license, if any.
* `owner` allows you to eager-load the Craft user that owns the license, if any.

### Products
* `existingLicenses` eager-loads all the existing licenses for the currently logged in Craft User.

## Examples

### Displaying the licensed product for the currently logged in Craft User.

```twig
{% if currentUser %}
    {% set licenses = craft.digitalProducts
        .licenses
        .owner(currentUser)
        .with(['product', 'order'])
        .all()
    %}

    <div class="panel panel-default">
    <div class="panel-heading"><h3 class="panel-title">Licenses</h3></div>
    {% if licenses %}
        <table class="table">
            <thead>
                <tr>
                    <th>Licensed product</th>
                    <th>License date</th>
                    <th>Order</th>
                </tr>
            </thead>
            <tbody>
            {% for license in licenses %}
                <tr>
                    <td><a href="{{ license.product.getUrl() }}">
                        {{ license.product.title }}
                    </a></td>
                    <td>{{ license.dateCreated|date('Y-m-d H:i:s') }}</td>
                    <td>
                        {% if license.orderId %}
                            <a href="/store/order?number={{ license.order.number }}">
                                Order no. {{ license.orderId }}
                            </a>
                        {% endif %}
                    </td>
                </tr>
            {% endfor %}
            </tbody>
        </table>
    {% endif %}
{% else %}
    <p>Please log in first</p>
{% endif %}
```

### Checking if currently logged in user is licensed to access a product.

```twig
{% set products = craft.digitalProducts
    .products
    .type('onlineCourses')
    .with(['existingLicenses'])
    .all()
%}

{% if products|length %}
    <table class="table">
        <thead>
            <tr>
                <th>Product</th>
                <th>License status</th>
            </tr>
        </thead>
        <tbody>
            {% for product in products %}
                <tr>
                    <td>{{ product.title }}</td>
                    <td>
                        {% if product.existingLicenses|length %}
                            You already own this product.
                        {% else %}
                            <a href="{{ product.getUrl() }}">Get it now!</a>
                        {% endif %}
                    </td>
                </tr>
            {% endfor %}
        </tbody>
    </table>
{% endif %}
```

## Serving licensed file downloads

To let customers download a file after purchasing a digital product, add an Assets field to your product type and attach the downloadable file to each product. Then use the `digital-products/download/download` action to stream the file to the browser. The underlying asset URL is never exposed.

The action accepts these parameters:

- `licenseKey` (required): the customer's license key
- `assetId`: the ID of the asset to download

Which assets a license can download depends on the product type's **Download Field** setting:

- **A field is selected:** only assets in that field on the licensed product can be downloaded. `assetId` is optional and defaults to the field's first asset.
- **Any related asset (the default):** any asset related to the licensed product can be downloaded, and `assetId` is required.

A 404 is returned if the license key is invalid or the asset isn't downloadable with it.

### Example

In a Twig template (e.g. an order confirmation page or the customer's account area):

```twig
{% for license in licenses %}
    {% set product = license.product %}
    {% set file = product.downloadFile.one() %}

    {% if file %}
        <a href="{{ actionUrl('digital-products/download/download', {
            licenseKey: license.licenseKey,
            assetId: file.id
        }) }}">Download {{ product.title }}</a>
    {% endif %}
{% endfor %}
```

This works with assets stored anywhere — local volumes, private S3 buckets, or any other filesystem — because the file is streamed through Craft rather than redirected to a signed URL.

## GraphQL

Digital products may be queried with GraphQL. Please read the [getting started docs](https://craftcms.com/docs/) to get up to speed with how Craft CMS handles GraphQL requests.

The GraphQL implementation provides two query options: `digitalProducts` for returning multiple products, and `digitalProduct` for returning a single product.  

### An example query and response

#### Query payload

```graphql
query {
    digitalProducts(type: "eBooks", limit: 2) {
        title,
        sku,
        price
    }
}
```

#### The response

```json
{
    "data": {
        "digitalProducts": [
            {
                "title": "Breaking Bad: The Recipes",
                "sku": "BB-TR",
                "price": 14.99
            },
            {
                "title": "The Clone Wars: Color The Clones",
                "sku": "TCW-CTC",
                "price": 7.95
            }
        ]
    }
}
```

#### The `digitalProducts`/`digitalProduct` query

Both the queries use the same argument set.

| Argument | Type | Description
| - | - | -
| `id`| `[QueryArgument]` | Narrows the query results based on the elements’ IDs.
| `uid`| `[String]` | Narrows the query results based on the elements’ UIDs.
| `status`| `[String]` | Narrows the query results based on the elements’ statuses.
| `unique`| `Boolean` | Determines whether only elements with unique IDs should be returned by the query.
| `title`| `[String]` | Narrows the query results based on the elements’ titles.
| `sku`| `[String]` | Narrows the query results based on the digital products’ SKUs.
| `slug`| `[String]` | Narrows the query results based on the elements’ slugs.
| `uri`| `[String]` | Narrows the query results based on the elements’ URIs.
| `search`| `String` | Narrows the query results to only elements that match a search query.
| `relatedTo`| `[Int]` | Narrows the query results to elements that relate to *any* of the provided element IDs. This argument is ignored, if `relatedToAll` is also used.
| `relatedToAll`| `[Int]` | Narrows the query results to elements that relate to *all* of the provided element IDs. Using this argument will cause `relatedTo` argument to be ignored.
| `ref`| `[String]` | Narrows the query results based on a reference string.
| `fixedOrder`| `Boolean` | Causes the query results to be returned in the order specified by the `id` argument.
| `inReverse`| `Boolean` | Causes the query results to be returned in reverse order.
| `dateCreated`| `[String]` | Narrows the query results based on the elements’ creation dates.
| `dateUpdated`| `[String]` | Narrows the query results based on the elements’ last-updated dates.
| `offset`| `Int` | Sets the offset for paginated results.
| `limit`| `Int` | Sets the limit for paginated results.
| `orderBy`| `String` | Sets the field the returned elements should be ordered by
| `type`| `[String]` | Narrows the query results based on the digital products’ prdocut type handles.
| `typeId`| `[QueryArgument]` | Narrows the query results based on the digital products’ product types, per the types’ IDs.
| `postDate`| `[String]` | Narrows the query results based on the digital products’ post dates.
| `before`| `String` | Narrows the query results to only digital products that were posted before a certain date.
| `after`| `String` | Narrows the query results to only digital products that were posted on or after a certain date.
| `expiryDate`| `[String]` | Narrows the query results based on the digital products’ expiry dates.

## Development

```bash
composer install
composer test       # Pest, on Craft's PluginTestCase with Commerce installed
composer check-cs   # Pint
composer phpstan    # Larastan
composer serve      # builds and serves the workbench at http://127.0.0.1:8000/admin (admin / password123)
```

The workbench needs Commerce's control panel assets built into `public/vendor/craftcms/commerce/build` of the Testbench skeleton (run `vite build` in a Commerce 6 checkout and copy `public/build`).
