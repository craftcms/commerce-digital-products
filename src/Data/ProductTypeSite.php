<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Data;

use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\DigitalProducts\ProductTypes;
use InvalidArgumentException;

/**
 * A product type's settings for one site.
 */
class ProductTypeSite extends Component
{
    public ?int $id = null;

    public ?int $productTypeId = null;

    public ?int $siteId = null;

    public bool $hasUrls = false;

    public ?string $uriFormat = null;

    public ?string $template = null;

    public bool $uriFormatIsRequired = true;

    private ?ProductType $productType = null;

    private ?Site $site = null;

    public function getProductType(): ProductType
    {
        if ($this->productType !== null) {
            return $this->productType;
        }

        if (! $this->productTypeId) {
            throw new InvalidArgumentException('Site is missing its product type ID');
        }

        return $this->productType = app(ProductTypes::class)->getProductTypeById($this->productTypeId)
            ?? throw new InvalidArgumentException('Invalid product type ID: '.$this->productTypeId);
    }

    public function setProductType(ProductType $productType): void
    {
        $this->productType = $productType;
    }

    public function getSite(): Site
    {
        return $this->site ??= ($this->siteId ? Sites::getSiteById($this->siteId) : null)
            ?? throw new InvalidArgumentException('Invalid site ID: '.$this->siteId);
    }

    #[\Override]
    public function getRules(): array
    {
        if ($this->uriFormatIsRequired && $this->hasUrls) {
            return ['uriFormat' => ['required']];
        }

        return [];
    }
}
