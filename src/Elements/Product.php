<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Elements;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Actions\Delete;
use CraftCms\Cms\Element\Data\EagerLoadPlan;
use CraftCms\Cms\Element\ElementCollection;
use CraftCms\Cms\Element\ElementHelper;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Support\Facades\ElementActions;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Query;
use CraftCms\Cms\Support\Sequence;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Controls\DateTime as DateTimeControl;
use CraftCms\Cms\Ui\Controls\Slug;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Commerce\Database\Table as CommerceTable;
use CraftCms\Commerce\Helpers\Purchasable as PurchasableHelper;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Database\Table;
use CraftCms\DigitalProducts\DeletionBlockers\LicensesDeletionBlocker;
use CraftCms\DigitalProducts\Elements\Queries\ProductQuery;
use CraftCms\DigitalProducts\Models\Product as ProductRecord;
use CraftCms\DigitalProducts\ProductTypes;
use CraftCms\DigitalProducts\Validation\ProductRules;
use CraftCms\RulesetValidation\Attributes\Ruleset;
use DateTime;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Override;
use RuntimeException;
use Throwable;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\renderSandboxedObjectTemplate;
use function CraftCms\Cms\t;

/**
 * A digital product: a Commerce purchasable that issues licenses when it is bought.
 *
 * The element ID is the row ID in `digitalproducts_products`, `commerce_purchasables` and
 * `commerce_purchasables_stores.purchasableId`. The SKU and price are stored by Commerce.
 *
 * @property-read ProductType $type
 * @property-read License[] $existingLicenses
 */
#[Ruleset(ProductRules::class)]
class Product extends Purchasable
{
    public const string STATUS_LIVE = 'live';

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_EXPIRED = 'expired';

    public ?int $typeId = null;

    public ?DateTime $postDate = null;

    public ?DateTime $expiryDate = null;

    /** @var License[]|null */
    private ?array $existingLicenses = null;

    #[Override]
    public static function displayName(): string
    {
        return t('Digital Product', category: 'digital-products');
    }

    #[Override]
    public static function lowerDisplayName(): string
    {
        return t('digital product', category: 'digital-products');
    }

    #[Override]
    public static function pluralDisplayName(): string
    {
        return t('Digital Products', category: 'digital-products');
    }

    #[Override]
    public static function pluralLowerDisplayName(): string
    {
        return t('digital products', category: 'digital-products');
    }

    #[Override]
    public static function hasTitles(): bool
    {
        return true;
    }

    #[Override]
    public static function hasStatuses(): bool
    {
        return true;
    }

    #[Override]
    public static function hasUris(): bool
    {
        return true;
    }

    #[Override]
    public static function isLocalized(): bool
    {
        return true;
    }

    #[Override]
    public static function hasDrafts(): bool
    {
        return true;
    }

    #[Override]
    public static function hasInventory(): bool
    {
        return false;
    }

    #[Override]
    public static function statuses(): array
    {
        return [
            self::STATUS_LIVE => t('Live', category: 'digital-products'),
            self::STATUS_PENDING => t('Pending', category: 'digital-products'),
            self::STATUS_EXPIRED => t('Expired', category: 'digital-products'),
            self::STATUS_DISABLED => t('Disabled', category: 'digital-products'),
        ];
    }

    #[Override]
    public static function find(): ProductQuery
    {
        return new ProductQuery;
    }

    #[Override]
    protected static function defineSources(string $context): array
    {
        $productTypesService = app(ProductTypes::class);
        $editable = $context === 'index';
        $productTypes = $editable
            ? $productTypesService->getEditableProductTypes()
            : $productTypesService->getAllProductTypes();

        $sources = [
            [
                'key' => '*',
                'label' => t('All products', category: 'digital-products'),
                'criteria' => [
                    'typeId' => array_map(fn (ProductType $productType) => $productType->id, $productTypes),
                    'editable' => $editable,
                ],
                'defaultSort' => ['postDate', 'desc'],
            ],
        ];

        if ($productTypes !== []) {
            $sources[] = ['heading' => t('Product Types', category: 'digital-products')];
        }

        $user = currentUser();

        foreach ($productTypes as $productType) {
            $key = 'productType:'.$productType->uid;

            $sources[$key] = [
                'key' => $key,
                'label' => t($productType->name, category: 'site'),
                'data' => [
                    'handle' => $productType->handle,
                    'editable' => (bool) $user?->can('digitalProducts-manageProductType:'.$productType->uid),
                ],
                'criteria' => [
                    'typeId' => $productType->id,
                    'editable' => $editable,
                ],
                'sites' => $productType->getSiteIds(),
                'defaultSort' => ['postDate', 'desc'],
            ];
        }

        return $sources;
    }

    #[Override]
    protected static function defineFieldLayouts(?string $source): array
    {
        $productTypesService = app(ProductTypes::class);

        if ($source === null || $source === '*') {
            $productTypes = $productTypesService->getAllProductTypes();
        } elseif (preg_match('/^productType:(.+)$/', $source, $matches)) {
            $productTypes = array_filter([$productTypesService->getProductTypeByUid($matches[1])]);
        } else {
            $productTypes = [];
        }

        return array_map(fn (ProductType $productType) => $productType->getFieldLayout(), array_values($productTypes));
    }

    #[Override]
    protected static function defineActions(string $source): array
    {
        return [
            ElementActions::createAction([
                'type' => Delete::class,
                'confirmationMessage' => t('Are you sure you want to delete the selected products?', category: 'digital-products'),
                'successMessage' => t('Products deleted.', category: 'digital-products'),
            ], static::class),
        ];
    }

    #[Override]
    protected static function includeSetStatusAction(): bool
    {
        return true;
    }

    #[Override]
    public static function deletionBlockers(ElementCollection $elements, bool $hardDelete): array
    {
        return [
            ...parent::deletionBlockers($elements, $hardDelete),
            new LicensesDeletionBlocker($elements, $hardDelete),
        ];
    }

    #[Override]
    protected static function defineTableAttributes(): array
    {
        return array_merge(parent::defineTableAttributes(), [
            'title' => ['label' => t('Title', category: 'digital-products')],
            'type' => ['label' => t('Type', category: 'digital-products')],
            'slug' => ['label' => t('Slug', category: 'digital-products')],
            'postDate' => ['label' => t('Post Date', category: 'digital-products')],
            'expiryDate' => ['label' => t('Expiry Date', category: 'digital-products')],
            'promotable' => ['label' => t('Promotable', category: 'digital-products')],
        ]);
    }

    #[Override]
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        if ($source === '*') {
            $attributes[] = 'type';
        }

        return [...$attributes, 'sku', 'price', 'postDate', 'expiryDate'];
    }

    #[Override]
    protected static function defineSortOptions(): array
    {
        return [
            'title' => t('Title', category: 'digital-products'),
            [
                'label' => t('Post Date', category: 'digital-products'),
                'orderBy' => Table::PRODUCTS.'.postDate',
                'attribute' => 'postDate',
                'defaultDir' => 'desc',
            ],
            [
                'label' => t('Expiry Date', category: 'digital-products'),
                'orderBy' => Table::PRODUCTS.'.expiryDate',
                'attribute' => 'expiryDate',
                'defaultDir' => 'desc',
            ],
            [
                'label' => t('Price', category: 'digital-products'),
                'orderBy' => 'purchasables_stores.basePrice',
                'attribute' => 'price',
                'defaultDir' => 'asc',
            ],
            'sku' => t('SKU', category: 'digital-products'),
        ];
    }

    #[Override]
    public static function sourceCpUri(array $source, ?string $page = null): ?string
    {
        $handle = $source['data']['handle'] ?? null;

        if (! str_starts_with((string) ($source['key'] ?? ''), 'productType:') || ! is_string($handle) || $handle === '') {
            return null;
        }

        return "digital-products/products/$handle";
    }

    #[Override]
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        if ($handle === 'existingLicenses') {
            $userId = currentUser()?->getCraftUserId();

            $map = $userId ? DB::table(Table::LICENSES)
                ->select(['productId as source', 'id as target'])
                ->whereIn('productId', array_map(fn (self $element) => $element->id, $sourceElements))
                ->where('userId', $userId)
                ->get()
                ->map(fn (object $row) => (array) $row)
                ->all() : [];

            return [
                'elementType' => License::class,
                'map' => $map,
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    public static function gqlTypeNameByContext(mixed $context): string
    {
        /** @var ProductType $context */
        return $context->handle.'_Product';
    }

    #[Override]
    public static function gqlScopesByContext(mixed $context): array
    {
        /** @var ProductType $context */
        return ['digitalProductTypes.'.$context->uid];
    }

    #[Override]
    public function __toString(): string
    {
        return (string) $this->title;
    }

    #[Override]
    public function setEagerLoadedElements(string $handle, array $elements, EagerLoadPlan $plan): void
    {
        if ($handle === 'existingLicenses') {
            $this->existingLicenses = array_values(array_filter($elements, fn ($element) => $element instanceof License));

            return;
        }

        parent::setEagerLoadedElements($handle, $elements, $plan);
    }

    /**
     * Returns the product's type.
     *
     * @throws RuntimeException if the type is missing or invalid.
     */
    public function getType(): ProductType
    {
        if ($this->typeId === null) {
            throw new RuntimeException('Digital product is missing its product type ID');
        }

        return app(ProductTypes::class)->getProductTypeById($this->typeId)
            ?? throw new RuntimeException('Invalid product type ID: '.$this->typeId);
    }

    public function getName(): ?string
    {
        return $this->title;
    }

    #[Override]
    public function getDescription(): string
    {
        return (string) $this->title;
    }

    /**
     * Returns the current user's licenses for this product.
     *
     * @return License[]
     */
    public function getExistingLicenses(): array
    {
        if ($this->existingLicenses !== null) {
            return $this->existingLicenses;
        }

        $userId = currentUser()?->getCraftUserId();

        return $this->existingLicenses = $userId && $this->id
            ? License::find()->productId($this->getCanonicalId())->ownerId($userId)->all()
            : [];
    }

    #[Override]
    public function getFieldLayout(): ?FieldLayout
    {
        if ($this->typeId === null) {
            return null;
        }

        return $this->getType()->getFieldLayout();
    }

    #[Override]
    public function getUriFormat(): ?string
    {
        if ($this->typeId === null) {
            return null;
        }

        $siteSettings = $this->getType()->getSiteSettings()[$this->siteId] ?? null;

        return $siteSettings?->hasUrls ? $siteSettings->uriFormat : null;
    }

    #[Override]
    public function getSupportedSites(): array
    {
        if ($this->typeId === null) {
            return parent::getSupportedSites();
        }

        return $this->getType()->getSiteIds();
    }

    #[Override]
    protected function route(): array|string|null
    {
        $siteSettings = $this->getType()->getSiteSettings()[$this->siteId] ?? null;

        if (! $siteSettings?->hasUrls) {
            return null;
        }

        return [
            'templates/render', [
                'template' => (string) $siteSettings->template,
                'variables' => [
                    'product' => $this,
                ],
            ],
        ];
    }

    #[Override]
    protected function previewTargets(): array
    {
        if ($this->typeId === null || ! ($this->getType()->getSiteSettings()[$this->siteId] ?? null)?->hasUrls) {
            return [];
        }

        return [[
            'label' => t('Primary {type} page', ['type' => self::lowerDisplayName()]),
            'url' => $this->getUrl(),
        ]];
    }

    #[Override]
    protected function cacheTags(): array
    {
        return ["digitalProductType:$this->typeId"];
    }

    #[Override]
    public function getStatus(): ?string
    {
        $status = parent::getStatus();

        if ($status === self::STATUS_ENABLED && $this->postDate) {
            $currentTime = time();
            $postDate = $this->postDate->getTimestamp();
            $expiryDate = $this->expiryDate?->getTimestamp();

            if ($postDate <= $currentTime && ($expiryDate === null || $expiryDate > $currentTime)) {
                return self::STATUS_LIVE;
            }

            if ($postDate > $currentTime) {
                return self::STATUS_PENDING;
            }

            return self::STATUS_EXPIRED;
        }

        return $status;
    }

    /**
     * A digital product can be bought while it is live and available for purchase.
     */
    #[Override]
    public function getIsAvailable(): bool
    {
        return $this->availableForPurchase
            && $this->getStatus() === self::STATUS_LIVE
            && ! PurchasableHelper::isTempSku($this->getSku());
    }

    #[Override]
    public function getIsShippable(): bool
    {
        return false;
    }

    #[Override]
    public function hasFreeShipping(): bool
    {
        return true;
    }

    #[Override]
    public function getIsPromotable(): bool
    {
        return $this->promotable;
    }

    #[Override]
    public function getSnapshot(): array
    {
        return [
            ...parent::getSnapshot(),
            'typeId' => $this->typeId,
            'productType' => $this->typeId ? $this->getType()->handle : null,
        ];
    }

    #[Override]
    public function getPostEditUrl(): ?string
    {
        return Url::cpUrl('digital-products/products');
    }

    #[Override]
    protected function cpEditUrl(): ?string
    {
        if ($this->typeId === null) {
            return null;
        }

        $path = sprintf('digital-products/products/%s/%s', $this->getType()->handle, $this->getCanonicalId());

        if ($this->slug && ! str_starts_with($this->slug, '__')) {
            $path .= '-'.str_replace('/', '-', $this->slug);
        }

        return $path;
    }

    #[Override]
    protected function crumbs(): array
    {
        $productType = $this->getType();
        $items = array_map(fn (ProductType $type) => [
            'type' => 'link',
            'label' => t($type->name, category: 'site'),
            'href' => Url::cpUrl("digital-products/products/$type->handle"),
            'selected' => $type->id === $productType->id,
        ], app(ProductTypes::class)->getEditableProductTypes());

        return [
            new ActionItem()->label(t('Digital Products', category: 'digital-products'))->href(Url::cpUrl('digital-products')),
            new ActionItem()->label(t('Products', category: 'digital-products'))->href(Url::cpUrl('digital-products/products')),
            new ActionItem()
                ->label(t($productType->name, category: 'site'))
                ->href(Url::cpUrl("digital-products/products/$productType->handle"))
                ->items(count($items) > 1 ? $items : []),
        ];
    }

    #[Override]
    protected function metaFieldsNodes(bool $static): array
    {
        $mode = $static ? ControlMode::Disabled : ControlMode::Editable;

        $slug = Slug::make('slug')
            ->value(! ElementHelper::isTempSlug($this->slug) ? $this->slug : null)
            ->mode($mode);

        if (! $static) {
            $slug->source('title')->autoGenerate(
                $this->isProvisionalDraft || $this->slug === null || ElementHelper::isTempSlug($this->slug),
            );
        }

        return [
            Field::make(t('Slug'))->control($slug),
            Field::make(t('Post Date', category: 'digital-products'))
                ->control(DateTimeControl::make('postDate')
                    ->showTime()
                    ->minuteIncrement(1)
                    ->value(self::dateTimeControlValue($this->postDate))
                    ->mode($mode)),
            Field::make(t('Expiry Date', category: 'digital-products'))
                ->control(DateTimeControl::make('expiryDate')
                    ->showTime()
                    ->minuteIncrement(1)
                    ->value(self::dateTimeControlValue($this->expiryDate))
                    ->mode($mode)),
            ...parent::metaFieldsNodes($static),
        ];
    }

    #[Override]
    protected function metaFieldsHtml(bool $static): string
    {
        return implode("\n", [
            $this->slugFieldHtml($static),
            FormFields::dateTimeFieldHtml([
                'status' => $this->getAttributeStatus('postDate'),
                'label' => t('Post Date', category: 'digital-products'),
                'id' => 'postDate',
                'name' => 'postDate',
                'value' => $this->postDate,
                'errors' => $this->errors()->get('postDate'),
                'disabled' => $static,
            ]),
            FormFields::dateTimeFieldHtml([
                'status' => $this->getAttributeStatus('expiryDate'),
                'label' => t('Expiry Date', category: 'digital-products'),
                'id' => 'expiryDate',
                'name' => 'expiryDate',
                'value' => $this->expiryDate,
                'errors' => $this->errors()->get('expiryDate'),
                'disabled' => $static,
            ]),
            parent::metaFieldsHtml($static),
        ]);
    }

    #[Override]
    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'type' => $this->typeId ? Html::encode(t($this->getType()->name, category: 'site')) : '',
            'promotable' => $this->promotable ? Html::tag('span', '', [
                'class' => 'checkbox-icon',
                'role' => 'img',
                'title' => t('Yes', category: 'digital-products'),
            ]) : '',
            default => (string) parent::attributeHtml($attribute),
        };
    }

    /**
     * Generates the SKU from the product type's SKU format when none was given.
     */
    #[Override]
    public function prepareForValidation(): void
    {
        $this->generateSku();
    }

    #[Override]
    public function beforeSave(bool $isNew): bool
    {
        if (! $this->postDate && ($this->enabled && $this->getEnabledForSite())) {
            $this->postDate = DateTime::createFromInterface(now());
        }

        $this->generateSku();

        return parent::beforeSave($isNew);
    }

    #[Override]
    public function afterSave(bool $isNew): void
    {
        if (! $this->propagating) {
            $record = $isNew ? null : ProductRecord::query()->find($this->id);

            if (! $isNew && ! $record) {
                throw new RuntimeException('Invalid digital product ID: '.$this->id);
            }

            $record ??= new ProductRecord(['id' => $this->id]);
            $record->typeId = $this->typeId;
            $record->taxCategoryId = $this->getTaxCategoryId();
            $record->postDate = Query::prepareDateForDb($this->postDate);
            $record->expiryDate = Query::prepareDateForDb($this->expiryDate);
            $record->promotable = $this->promotable;

            // Keep the dates in sync with the element row
            $record->dateCreated = Query::prepareDateForDb($this->dateCreated ?? now());
            $record->dateUpdated = Query::prepareDateForDb($this->dateUpdated ?? now());

            $dirtyAttributes = array_keys($record->getDirty());
            $record->save();

            $this->setDirtyAttributes($dirtyAttributes);
        }

        parent::afterSave($isNew);
    }

    /**
     * Products with licenses can't be deleted.
     */
    #[Override]
    public function beforeDelete(): bool
    {
        if ($this->id && $this->getIsCanonical() && License::find()->productId($this->id)->status(null)->exists()) {
            $this->errors()->add('id', t('Couldn’t delete product with existing licenses.', category: 'digital-products'));

            return false;
        }

        return parent::beforeDelete();
    }

    private function generateSku(): void
    {
        if (($this->getSku() !== '' && ! PurchasableHelper::isTempSku($this->getSku())) || $this->typeId === null) {
            return;
        }

        $skuFormat = $this->getType()->skuFormat;

        if (! $skuFormat) {
            return;
        }

        try {
            $language = $this->getSite()->getLanguage();
            $sku = I18N::withLocale($language, $language, fn () => renderSandboxedObjectTemplate($skuFormat, $this));
        } catch (Throwable $e) {
            Log::error('Digital Products could not generate the supplied SKU format: '.$e->getMessage());

            return;
        }

        if ($sku === '') {
            return;
        }

        // Append a sequence number if another purchasable already uses the SKU
        $baseSku = $sku;

        while ($this->skuExists($sku)) {
            $sku = $baseSku.'-'.Sequence::next('sku::'.$baseSku);
        }

        $this->setSku($sku);
    }

    private function skuExists(string $sku): bool
    {
        return DB::table(CommerceTable::PURCHASABLES)
            ->where('sku', $sku)
            ->when($this->id, fn ($query) => $query->where('id', '!=', $this->getCanonicalId()))
            ->exists();
    }

    /**
     * @return array{date: string, time: string, timezone: string}
     */
    private static function dateTimeControlValue(?DateTimeInterface $value): array
    {
        return [
            'date' => $value?->format('Y-m-d') ?? '',
            'time' => $value?->format('H:i') ?? '',
            'timezone' => $value?->getTimezone()->getName() ?? Cms::timezone(),
        ];
    }
}
