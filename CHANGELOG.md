# Release Notes for Digital Products

## Unreleased

- Fixed a bug where the `product()` method on `LicenseQuery` didn't accept arrays or `not` conditions. ([#104](https://github.com/craftcms/commerce-digital-products/issues/104))
- Added a `digital-products/download/download` action that streams a licensed asset to the browser without exposing the underlying file URL. ([#67](https://github.com/craftcms/commerce-digital-products/issues/67), [#109](https://github.com/craftcms/commerce-digital-products/issues/109))
- Fixed a PHP error that could occur when paying for a completed order that contains deleted digital products.
- Fixed an SQL error that could occur when saving a new digital product. ([#110](https://github.com/craftcms/commerce-digital-products/issues/110))
- Fixed a bug where the Price field was not editable in the element index inline editor. ([#112](https://github.com/craftcms/commerce-digital-products/issues/112))
- Fixed an error that occurred when viewing digital products in "Display as cards" view. ([#111](https://github.com/craftcms/commerce-digital-products/issues/111))
- Digital products and Licenses now use Craft's native element editor, with breadcrumb navigation, draft-based creation, and standard sidebar metadata.
- Licenses can now be created manually from the control panel via a "New license" button.
- `productId` and `ownerEmail` columns are now nullable, allowing licenses to be created without an initial product or owner email.

## 4.0.3 - 2025-02-03

- Fixed a bug where users with “Manage licenses” permission weren’t allowed to edit licenses. ([#105](https://github.com/craftcms/digital-products/issues/105))
- Fixed a PHP error that could occur when eager loading orders on a license query. ([#100](https://github.com/craftcms/digital-products/issues/100))

## 4.0.2 - 2024-09-11

- Fixed a bug where GraphQL queries for digital products would fail.

## 4.0.1 - 2024-09-11

- Fixed a bug where product fields content was not prepared for Craft 5.

## 4.0.0 - 2024-03-20

- Digital Products now requires Craft Commerce 5.0.0-beta.1 or later.

## 3.2.4 - 2025-02-03

- Fixed a bug where users with “Manage licenses” permission weren’t allowed to edit licenses. ([#105](https://github.com/craftcms/digital-products/issues/105))
- Fixed a PHP error that could occur when eager loading orders on a license query. ([#100](https://github.com/craftcms/digital-products/issues/100))

## 3.2.3 - 2023-09-11

- Fixed an XSS vulnerability.

## 3.2.2 - 2023-03-22

- Fixed a PHP error that occurred when live previewing a digital product. ([#84](https://github.com/craftcms/digital-products/issues/84))

## 3.2.1 - 2023-03-09

- Fixed a bug where digital product Licenses couldn’t be edited or deleted in Craft 4.3.2+.
- Fixed a bug where garbage collection wasn’t running.

## 3.2.0 - 2023-01-25

- Product type field layouts now support UI elements. ([#60](https://github.com/craftcms/digital-products/issues/60))
- Fixed a PHP error that occurred when uninstalling the plugin. ([#80](https://github.com/craftcms/digital-products/issues/80))
- Fixed a bug where digital products couldn’t be edited or deleted in Craft 4.3.2+. ([#79](https://github.com/craftcms/digital-products/issues/79))

## 3.1.0 - 2022-11-23

### Fixed
- Fixed a bug where it was possible to soft-delete digital products with active licenses. ([#75](https://github.com/craftcms/digital-products/issues/75), [#78](https://github.com/craftcms/digital-products/pull/78))

## 3.0.2 - 2022-08-05

### Fixed
- Fixed a PHP error that occurred when creating a new site.

## 3.0.1 - 2022-06-13

### Added
- Added the `resave/digital-products` command.

## 3.0.0.1 - 2022-05-05

### Fixed
- Fixed a PHP error that occurred when trying to manage permissions.

## 3.0.0 - 2022-05-04

### Added
- Added Craft CMS 4 and Craft Commerce 4 compatibility.

## 2.4.3.2 - 2021-08-18

### Fixed
- Fixed a bug where the `afterSave` event was not being triggered for license elements.

## 2.4.3.1 - 2021-04-28

### Fixed
- Fixed a PHP error that occurred when saving a product with a duplicate SKU. ([#61](https://github.com/craftcms/digital-products/issues/61))
- Fixed errors that could occur when switching the selected site from the Digital Products index page and Edit Digital Product pages. ([#54](https://github.com/craftcms/digital-products/issues/54))
- Fixed a bug where it wasn’t possible to hard-delete digital products. ([#56](https://github.com/craftcms/digital-products/issues/56))
- Fixed a bug where the “Product Types” nav item was shown for users that didn’t have permission to manage product types. ([#53](https://github.com/craftcms/digital-products/issues/53))

## 2.4.3 - 2021-04-02

### Changed
- Products’ and licenses’ date sort options are now sorted in descending order by default when selected (requires Craft 3.5.9 or later).

### Fixed
- Fixed a 400 error that could occur when a product type’s Template setting was blank.
- Fixed plugin description that implied compatibility only with Craft Commerce 2.
- Fixed a PHP error that could occur when saving a new product without a price. ([#58](https://github.com/craftcms/digital-products/issues/58))

## 2.4.2 - 2020-08-24

### Fixed
- Fixed a deprecation warning in `craft\digitalproducts\controllers\ProductsController`.

## 2.4.1 - 2020-08-24

### Fixed
- Fixed a javascript error that caused the “Add Products” button to be hidden. ([#49](https://github.com/craftcms/digital-products/issues/49))
- Fixed a bug where the `afterSaveProductType` and `beforeSaveProductType` events triggered the wrong event object. ([#48](https://github.com/craftcms/digital-products/issues/48))

## 2.4.0 - 2020-05-05

### Added
- Added GraphQL support for digital products. ([#46](https://github.com/craftcms/digital-products/issues/46))
- It’s now possible to update statuses and delete products form the Products index page. ([#34](https://github.com/craftcms/digital-products/issues/34))
- Added `craft\digital-products\elements\Products::getGqlTypeName()`.
- Added `craft\digital-products\elements\Products::gqlScopesByContext()`.
- Added `craft\digital-products\elements\Products::gqlTypeNameByContext()`.
- Added `craft\digital-products\gql\arguments\elements\Product`.
- Added `craft\digital-products\gql\interfaces\elements\Product`.
- Added `craft\digital-products\gql\queries\Product`.
- Added `craft\digital-products\gql\resolvers\elements\Product`.
- Added `craft\digital-products\gql\types\elements\Product`.
- Added `craft\digital-products\gql\types\gemerators\ProductType`.
- Added `craft\digital-products\helpers\Gql`.
- Added `craft\digital-products\Plugin::defineActions()`.
- Added `craft\digital-products\services\ProductTypes::getProductTypeByUid()`.

## 2.3.1 - 2019-10-09

### Fixed
- Fixed deprecation warnings on Edit Product page and Product Types index. ([#44](https://github.com/craftcms/digital-products/issues/44))

## 2.3.0 - 2019-07-24

### Changed
- Update Craft Commerce requirements to allow for Craft Commerce 3.

## 2.2.4.1 - 2019-07-18

### Fixed
- Fixed an error where it was impossible to list existing digital products. ([#42](https://github.com/craftcms/digital-products/issues/42))

##  2.2.4 - 2019-07-17

### Fixed
- Fixed a bug where product permissions were not being checked correctly. ([#42](https://github.com/craftcms/digital-products/issues/42))

##  2.2.3 - 2019-07-16

### Fixed
- Digital products are now ignored by the shipping engine.

## 2.2.2 - 2019-07-07

### Fixed
- Fixed an error that occurred when deleting a disabled product. ([#41](https://github.com/craftcms/digital-products/issues/41))
- Fixed Live Preview and sharing support.

## 2.2.1 - 2019-06-14

### Added
- Added the `cp.digital-products.product.edit.details` template hook.

### Fixed
- Fixed an error that could occur when updating to 2.2.0 and there were user groups with no defined permissions.

## 2.2.0 - 2019-04-26

### Added
- Added support for the `project-config/rebuild` command.

### Changed
- Digital Products now correctly typecasts the boolean and integer values saved to the project config.
- Digital Products now requires Craft 3.1.20 and Craft Commerce 2.1.0 or later.

### Fixed
- Fixed an error that could occur when saving a digital product if it didn’t have a boolean `promotable` value set.
- Fixed a bug where edit product permissions were missing. ([#33](https://github.com/craftcms/digital-products/pull/33))
- Fixed an error that could prevent licenses from getting listed properly in the Control Panel.

## 2.1.0 - 2019-01-24

### Added
- Added support for project config.
- Added the `generateLicenseOnOrderPaid` setting which allows to specify when the license should be created.

### Changed
- Digital Products now requires Craft 3.1.0-alpha.1 or later.
- Digital Products now requires Commerce 2.0 or later.

### Fixed
- Fixed an error where adding or removing sites would not reflect those changes in product type settings.
- Fixed an error where a non-existing Commerce event was being referenced.

## 2.0.4 - 2018-12-07

### Fixed
- Fixed a bug where it wasn't possible to delete a product from its edit page. ([#24](https://github.com/craftcms/digital-products/issues/24))
- Fixed deprecation errors in Control Panel templates. ([#25](https://github.com/craftcms/digital-products/issues/25))
- Fixed a bug where the "Digital Products" item on the Settings page linked to a blank page. ([#23](https://github.com/craftcms/digital-products/issues/23))
- Fixed a bug where querying for licenses would not work in some cases. ([#11](https://github.com/craftcms/commerce-digital-products/issues/11))

## 2.0.3 - 2018-10-19

### Added
- Added `craft\digitalproducts\models\ProductTypeSite::getSite()`.

### Changed
- Licenses are now generated after the order is paid, instead of when it’s completed. ([#21](https://github.com/craftcms/commerce-digital-products/issues/21))

### Fixed
- Fixed a bug where Digital Products fields were named “Products”. ([#13](https://github.com/craftcms/commerce-digital-products/issues/13))
- Fixed a bug where querying for products didn’t always return the correct results.
- Fixed a bug where only fields in the first field layout tab were visible when editing a product. ([#8](https://github.com/craftcms/commerce-digital-products/issues/8))
- Fixed a bug where products coludn’t hvae URLs. ([#9](https://github.com/craftcms/commerce-digital-products/issues/9))
- Fixed a bug where querying for licenses would not work in some cases. ([#11](https://github.com/craftcms/commerce-digital-products/issues/11))
- Fixed a bug where it wasn’t possible to delete a product. ([#12](https://github.com/craftcms/commerce-digital-products/issues/12))
- Fixed a bug where product template paths and URI formats weren’t being automatically generated correctly. ([#16](https://github.com/craftcms/commerce-digital-products/issues/16))
- Fixed deprecation errors. ([#19](https://github.com/craftcms/commerce-digital-products/issues/19))

## 2.0.2 - 2018-05-14

### Fixed
- Fixed a bug where licenses were not being generated after completing an order.

## 2.0.1 - 2018-05-09

### Fixed
- Fixed a bug where tax categories were not being populated when editing a product. ([#3](https://github.com/craftcms/commerce-digital-products/issues/3))
- Fixed broken links in `composer.json`. ([#4](https://github.com/craftcms/commerce-digital-products/issues/4))
- Fixed an error that occurred on the Licenses index page. ([#7](https://github.com/craftcms/commerce-digital-products/issues/7))
- Fixed a bug where the “New product” button wasn’t working on the Products index page. ([#6](https://github.com/craftcms/commerce-digital-products/issues/6))

## 2.0.0 - 2018-04-04

### Added
- Added Craft 3 compatibility.

### Changed
- Digital products now fires `beforeSaveProductType`, `afterSaveProductType` and `beforeGenerateLicenseKey` events.
- Product types are now accessible via `$product->getProductType()` instead of `$product->getType()`.
- Instead of eager-loading a boolean flag `isLicensed` for products, you must now eager-load the `existingLicenses` property, which is an array of existing licenses for that product for the current user.

### Fixed
- Fixed a broken foreign key constraint. ([#1](https://github.com/craftcms/commerce-digital-products/issues/1))

## 1.0.4 - 2017-03-11

### Fixed
- Fixed a bug where digital product prices weren’t displaying correctly.
- Fixed a bug where digital product prices could be saved incorrectly.

## 1.0.3 - 2016-11-02

### Changed
- Added support for a plugin release feed.

## 1.0.2 - 2016-10-12

### Fixed
- Fixed bugs.

## 1.0.1 - 2016-10-03

### Fixed
- Fixed bugs.

## 1.0.0 - 2016-06-21

- Initial release
