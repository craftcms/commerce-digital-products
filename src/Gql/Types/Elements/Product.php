<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\digitalproducts\gql\types\elements;

use craft\digitalproducts\elements\Product as ProductElement;
use craft\digitalproducts\gql\interfaces\elements\Product as ProductInterface;
use craft\gql\types\elements\Element as ElementType;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.4
 */
class Product extends ElementType
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            ProductInterface::getType(),
        ];

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    protected function resolve($source, $arguments, $context, ResolveInfo $resolveInfo): mixed
    {
        /** @var ProductElement $source */
        $fieldName = $resolveInfo->fieldName;

        return match ($fieldName) {
            'productTypeHandle' => $source->getType()->handle,
            default => parent::resolve($source, $arguments, $context, $resolveInfo),
        };
    }
}
