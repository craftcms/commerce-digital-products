<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Fields;

use CraftCms\Cms\Field\BaseRelationField;
use CraftCms\Cms\Gql\Gql as GqlService;
use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\Gql\Arguments\Elements\Product as ProductArguments;
use CraftCms\DigitalProducts\Gql\Interfaces\Elements\Product as ProductInterface;
use CraftCms\DigitalProducts\Gql\Resolvers\Elements\Product as ProductResolver;
use GraphQL\Type\Definition\Type;
use Override;

use function CraftCms\Cms\t;

/**
 * Relates digital products to other elements.
 */
class Products extends BaseRelationField
{
    #[Override]
    public static function displayName(): string
    {
        return t('Digital Products', category: 'digital-products');
    }

    #[Override]
    public static function icon(): string
    {
        return 'file-arrow-down';
    }

    #[Override]
    public static function defaultSelectionLabel(): string
    {
        return t('Add a digital product', category: 'digital-products');
    }

    public static function elementType(): string
    {
        return Product::class;
    }

    #[Override]
    public function getContentGqlType(): array|Type
    {
        return [
            'name' => $this->handle,
            'type' => Type::listOf(ProductInterface::getType()),
            'args' => ProductArguments::getArguments(),
            'resolve' => ProductResolver::class.'::resolve',
            'complexity' => GqlHelper::relatedArgumentComplexity(GqlService::GRAPHQL_COMPLEXITY_EAGER_LOAD),
        ];
    }
}
