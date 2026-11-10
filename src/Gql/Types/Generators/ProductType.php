<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\digitalproducts\gql\types\generators;

use Craft;
use craft\base\Field;
use craft\behaviors\FieldLayoutBehavior;
use craft\digitalproducts\elements\Product as ProductElement;
use craft\digitalproducts\gql\interfaces\elements\Product as ProductInterface;
use craft\digitalproducts\gql\types\elements\Product as ProductTypeElement;
use craft\digitalproducts\helpers\Gql as CommerceGqlHelper;
use craft\digitalproducts\Plugin;
use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;

/**
 * Class ProductType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.4
 */
class ProductType implements GeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes(mixed $context = null): array
    {
        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        $gqlTypes = [];

        foreach ($productTypes as $productType) {
            /** @var ProductType|FieldLayoutBehavior $productType */
            $typeName = ProductElement::gqlTypeNameByContext($productType);
            $requiredContexts = ProductElement::gqlScopesByContext($productType);

            if (!CommerceGqlHelper::isSchemaAwareOf($requiredContexts)) {
                continue;
            }

            $contentFields = $productType->getCustomFields();
            $contentFieldGqlTypes = [];

            /** @var Field $contentField */
            foreach ($contentFields as $contentField) {
                $contentFieldGqlTypes[$contentField->handle] = $contentField->getContentGqlType();
            }

            $productTypeFields = Craft::$app->getGql()->prepareFieldDefinitions(array_merge(ProductInterface::getFieldDefinitions(), $contentFieldGqlTypes), $typeName);

            // Generate a type for each entry type
            $gqlTypes[$typeName] = GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, new ProductTypeElement([
                'name' => $typeName,
                'fields' => function() use ($productTypeFields) {
                    return $productTypeFields;
                },
            ]));
        }

        return $gqlTypes;
    }
}
