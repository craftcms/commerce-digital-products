<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\digitalproducts\gql\queries;

use craft\digitalproducts\gql\arguments\elements\Product as ProductArguments;
use craft\digitalproducts\gql\interfaces\elements\Product as ProductInterface;
use craft\digitalproducts\gql\resolvers\elements\Product as ProductResolver;
use craft\digitalproducts\helpers\Gql as GqlHelper;
use craft\gql\base\Query;
use GraphQL\Type\Definition\Type;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.4
 */
class Product extends Query
{
    /**
     * @inheritdoc
     */
    public static function getQueries($checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canQueryProducts()) {
            return [];
        }

        return [
            'digitalProducts' => [
                'type' => Type::listOf(ProductInterface::getType()),
                'args' => ProductArguments::getArguments(),
                'resolve' => ProductResolver::class . '::resolve',
                'description' => 'Used to query for digital products.',
            ],
            'digitalProduct' => [
                'type' => ProductInterface::getType(),
                'args' => ProductArguments::getArguments(),
                'resolve' => ProductResolver::class . '::resolveOne',
                'description' => 'Used to query for a digital product.',
            ],
        ];
    }
}
