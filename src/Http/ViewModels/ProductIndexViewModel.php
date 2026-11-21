<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Http\ViewModels;

use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Cms\Http\ViewModels\ContentIndexViewModel;
use CraftCms\Cms\Support\Url;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\ProductTypes;
use Override;

/**
 * The element index payload for digital products, at `digital-products/products/{productTypeHandle?}`.
 */
class ProductIndexViewModel extends ContentIndexViewModel
{
    public function __construct(
        ElementIndexRequest $request,
        private readonly ?string $productTypeHandle = null,
    ) {
        parent::__construct(Product::class, $request);
    }

    #[Override]
    public function indexUrl(): string
    {
        return Url::cpUrl('digital-products/products');
    }

    #[Override]
    protected function sourceUrl(array $source): ?string
    {
        $uri = Product::sourceCpUri($source);

        return $uri === null ? parent::sourceUrl($source) : Url::cpUrl($uri);
    }

    /**
     * Maps the `digital-products/products/{handle}` segment to its source key.
     */
    #[Override]
    protected function defaultSourceKey(): ?string
    {
        if ($this->productTypeHandle === null || $this->productTypeHandle === '') {
            return null;
        }

        $productType = app(ProductTypes::class)->getProductTypeByHandle($this->productTypeHandle);

        return $productType ? "productType:$productType->uid" : null;
    }
}
