<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Data;

use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\FieldLayout\Contracts\FieldLayoutProviderInterface;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Support\Url;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\ProductTypes;
use CraftCms\DigitalProducts\Validation\ProductTypeRules;
use CraftCms\RulesetValidation\Attributes\Ruleset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use RuntimeException;

use function CraftCms\Cms\t;

/**
 * A digital product type.
 */
#[Ruleset(ProductTypeRules::class)]
class ProductType extends Component implements FieldLayoutProviderInterface
{
    public ?int $id = null;

    public ?int $fieldLayoutId = null;

    public ?string $name = null;

    public ?string $handle = null;

    public ?string $skuFormat = null;

    public ?string $uid = null;

    /** @var array<int, ProductTypeSite>|null */
    private ?array $siteSettings = null;

    private ?FieldLayout $fieldLayout = null;

    public function __toString(): string
    {
        return (string) $this->handle;
    }

    public function getHandle(): ?string
    {
        return $this->handle;
    }

    public function getCpEditUrl(): string
    {
        return Url::cpUrl('digital-products/producttypes/'.$this->id);
    }

    /**
     * @return array<int, ProductTypeSite> Site settings, indexed by site ID.
     */
    public function getSiteSettings(): array
    {
        if ($this->siteSettings !== null) {
            return $this->siteSettings;
        }

        if (! $this->id) {
            return [];
        }

        $this->setSiteSettings(Arr::keyBy(app(ProductTypes::class)->getProductTypeSites($this->id), 'siteId'));

        return $this->siteSettings;
    }

    /**
     * @param  array<int, ProductTypeSite>  $siteSettings
     */
    public function setSiteSettings(array $siteSettings): void
    {
        $this->siteSettings = $siteSettings;

        foreach ($this->siteSettings as $settings) {
            $settings->setProductType($this);
        }
    }

    /** @return int[] */
    public function getSiteIds(): array
    {
        return array_keys($this->getSiteSettings());
    }

    public function getFieldLayout(): FieldLayout
    {
        if ($this->fieldLayout !== null) {
            return $this->fieldLayout;
        }

        if ($this->fieldLayoutId) {
            $fieldLayout = Fields::getLayoutById($this->fieldLayoutId, true)
                ?? throw new RuntimeException('Invalid field layout ID: '.$this->fieldLayoutId);
        } else {
            $fieldLayout = new FieldLayout(['type' => Product::class]);
        }

        $fieldLayout->provider = $this;

        return $this->fieldLayout = $fieldLayout;
    }

    /**
     * Alias of {@see getFieldLayout()}, kept for templates written against Digital Products 4.x and 5.x.
     */
    public function getProductFieldLayout(): FieldLayout
    {
        return $this->getFieldLayout();
    }

    public function setFieldLayout(FieldLayout $fieldLayout): void
    {
        $fieldLayout->provider = $this;
        $this->fieldLayout = $fieldLayout;
    }

    /**
     * Returns the custom fields in the product field layout.
     *
     * @return FieldInterface[]
     */
    public function getCustomFields(): array
    {
        return $this->getFieldLayout()->getCustomFields();
    }

    #[\Override]
    public function afterValidate(?Validator $validator = null): void
    {
        $fieldLayout = $this->getFieldLayout();
        $fieldLayout->reservedFieldHandles = [
            'availableForPurchase',
            'existingLicenses',
            'price',
            'product',
            'promotable',
            'sku',
            'type',
        ];

        if (! $fieldLayout->validate()) {
            $this->addModelErrors($fieldLayout, 'fieldLayout');
        }

        foreach ($this->getSiteSettings() as $siteSettings) {
            if (! $siteSettings->validate()) {
                $this->addModelErrors($siteSettings, "siteSettings[$siteSettings->siteId]");
            }
        }

        if (empty($this->getSiteSettings())) {
            $this->errors()->add('siteSettings', t('At least one site must be enabled for the product type.', category: 'digital-products'));
        }
    }

    /**
     * Returns the product type's project config data, stored at `digital-products.productTypes.{uid}`.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $config = [
            'name' => $this->name,
            'handle' => $this->handle,
            'skuFormat' => $this->skuFormat,
            'siteSettings' => [],
        ];

        $fieldLayout = $this->getFieldLayout();

        if ($fieldLayoutConfig = $fieldLayout->getConfig()) {
            if (empty($fieldLayout->id)) {
                $fieldLayout->uid ??= (string) Str::uuid();
                $layoutUid = $fieldLayout->uid;
            } else {
                $layoutUid = DB::table(CraftTable::FIELDLAYOUTS)->uidById($fieldLayout->id);
            }

            $config['fieldLayouts'] = [$layoutUid => $fieldLayoutConfig];
        }

        foreach ($this->getSiteSettings() as $siteId => $settings) {
            $siteUid = DB::table(CraftTable::SITES)->uidById($siteId);
            $config['siteSettings'][$siteUid] = [
                'hasUrls' => $settings->hasUrls,
                'uriFormat' => $settings->uriFormat,
                'template' => $settings->template,
            ];
        }

        return $config;
    }
}
