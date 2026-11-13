<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Http\Controllers;

use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Cms\Support\Url;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\Http\ViewModels\ProductIndexViewModel;
use CraftCms\DigitalProducts\ProductTypes;
use Inertia\Inertia;
use Inertia\Response;

use function CraftCms\Cms\t;

/**
 * The digital products element index.
 */
readonly class ProductsController
{
    public function __construct(private ProductTypes $productTypes) {}

    public function index(ElementIndexRequest $request, ?string $productTypeHandle = null): Response
    {
        $editableProductTypes = $this->productTypes->getEditableProductTypes();

        abort_if($editableProductTypes === [], 403, 'User is not permitted to edit any product types.');

        $viewModel = new ProductIndexViewModel($request, $productTypeHandle);

        return Inertia::render('elements/Index', [$viewModel])
            ->with([
                'indexUrl' => $viewModel->indexUrl(),
                'newElement' => $this->newElement($editableProductTypes, $productTypeHandle),
            ]);
    }

    /**
     * The “New product” button, for the selected product type or else the first one the user can edit.
     *
     * @param  ProductType[]  $editableProductTypes
     * @return array{label: string, href: string}
     */
    private function newElement(array $editableProductTypes, ?string $productTypeHandle): array
    {
        $productType = collect($editableProductTypes)->firstWhere('handle', $productTypeHandle)
            ?? $editableProductTypes[0];

        return [
            'label' => count($editableProductTypes) > 1
                ? t('New {productType} product', ['productType' => t($productType->name, category: 'site')], 'digital-products')
                : t('New {type}', ['type' => Product::lowerDisplayName()]),
            'href' => Url::cpUrl("digital-products/products/$productType->handle/new"),
        ];
    }
}
