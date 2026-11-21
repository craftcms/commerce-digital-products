<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Gql\Types\Elements;

use CraftCms\Cms\Gql\Types\Elements\Element;
use CraftCms\DigitalProducts\Gql\Interfaces\Elements\Product as ProductInterface;
use GraphQL\Type\Definition\ResolveInfo;
use Override;

class Product extends Element
{
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            ProductInterface::getType(),
        ];

        parent::__construct($config);
    }

    #[Override]
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        return match ($resolveInfo->fieldName) {
            'productTypeHandle' => $source->getType()->handle,
            'productTypeId' => $source->typeId,
            default => parent::resolve($source, $arguments, $context, $resolveInfo),
        };
    }
}
