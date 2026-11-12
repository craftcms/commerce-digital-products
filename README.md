<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="Digital Products icon"></p>

<h1 align="center">Digital Products</h1>

This plugin makes it possible to sell licenses for digital products with [Craft Commerce](https://craftcms.com/commerce).

## Requirements

Digital Products requires Craft 5.0.0 and Craft Commerce 5.0.0 or later.

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
./craft install/plugin digital-products
```

## Events

### The `beforeSaveProductType` and `afterSaveProductType` events

Plugins can be notified immediately before or after a product type is saved so your plugin can take action if needed:

```php
use craft\digitalproducts\events\ProductTypeEvent;
use craft\digitalproducts\services\ProductTypes;
use yii\base\Event;

// ...

Event::on(
    ProductTypes::class, 
    ProductTypes::EVENT_BEFORE_SAVE_PRODUCTTYPE, 
    function(ProductTypeEvent $e) {
        // Custom code to be executed when a product type is saved
    }
);
```

### The `beforeGenerateLicenseKey` event

Plugins get a chance to provide a license key instead of relying on Digital Products to generate one.

```php
use craft\digitalproducts\elements\License;
use craft\digitalproducts\events\GenerateKeyEvent;
use craft\digitalproducts\Plugin as DigitalProducts;
use yii\base\Event;

// ...

Event::on(
    License::class, 
    License::EVENT_GENERATE_LICENSE_KEY, 
    function(GenerateKeyEvent $e) {
        $licenseService = DigitalProducts::getInstance()->getLicenses();
        
        do {
            $licenseKey = // custom key generation logic...
        } while (!$licenseService->isLicenseKeyUnique($licenseKey));

        $e->licenseKey = $licenseKey;
    }
);
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

Digital products may be queried with GraphQL. Please read the [getting started docs](https://docs.craftcms.com/v3/graphql.html) to get up to speed with how Craft CMS handles GraphQL requests.

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
