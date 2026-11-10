<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts;

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Jobs\ResaveElements;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\ProjectConfig\Events\ConfigEvent;
use CraftCms\Cms\ProjectConfig\ProjectConfigHelper;
use CraftCms\Cms\Site\Events\SiteDeleted;
use CraftCms\Cms\Site\Events\SiteSaved;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Cms\View\TemplateResolver;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Data\ProductTypeSite;
use CraftCms\DigitalProducts\Database\Table;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\Events\ProductTypeSaved;
use CraftCms\DigitalProducts\Events\ProductTypeSaving;
use CraftCms\DigitalProducts\Models\ProductType as ProductTypeRecord;
use CraftCms\DigitalProducts\Models\ProductTypeSite as ProductTypeSiteRecord;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 * Digital product type service.
 *
 * Product types are stored in project config at `digital-products.productTypes.{uid}`; the
 * database tables are kept in sync by {@see handleChangedProductType()} and {@see handleDeletedProductType()}.
 */
#[Singleton]
class ProductTypes
{
    public const string CONFIG_PRODUCTTYPES_KEY = 'digital-products.productTypes';

    /** @var ProductType[]|null */
    private ?array $allProductTypes = null;

    /** @var array<int, ProductTypeSite[]> */
    private array $siteSettingsByProductTypeId = [];

    /**
     * @return ProductType[] All product types.
     */
    public function getAllProductTypes(): array
    {
        return $this->allProductTypes ??= ProductTypeRecord::query()
            ->orderBy('name')
            ->get()
            ->map(fn (ProductTypeRecord $record) => $this->toData($record))
            ->all();
    }

    /**
     * @return int[]
     */
    public function getAllProductTypeIds(): array
    {
        return array_map(fn (ProductType $productType) => $productType->id, $this->getAllProductTypes());
    }

    /**
     * @return ProductType[] The product types the current user can manage products in.
     */
    public function getEditableProductTypes(): array
    {
        $ids = $this->getEditableProductTypeIds();

        return array_values(array_filter(
            $this->getAllProductTypes(),
            fn (ProductType $productType) => in_array($productType->id, $ids, true),
        ));
    }

    /**
     * @return int[] The IDs of the product types the current user can manage products in.
     */
    public function getEditableProductTypeIds(): array
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return $this->getAllProductTypeIds();
        }

        $user = currentUser();

        if (! $user) {
            return [];
        }

        $ids = [];

        foreach ($this->getAllProductTypes() as $productType) {
            if ($user->can('digitalProducts-manageProductType:'.$productType->uid)) {
                $ids[] = $productType->id;
            }
        }

        return $ids;
    }

    public function getProductTypeById(int $productTypeId): ?ProductType
    {
        return collect($this->getAllProductTypes())->firstWhere('id', $productTypeId);
    }

    public function getProductTypeByHandle(string $handle): ?ProductType
    {
        return collect($this->getAllProductTypes())->firstWhere('handle', $handle);
    }

    public function getProductTypeByUid(string $uid): ?ProductType
    {
        return collect($this->getAllProductTypes())->firstWhere('uid', $uid);
    }

    /**
     * @return ProductTypeSite[]
     */
    public function getProductTypeSites(int $productTypeId): array
    {
        return $this->siteSettingsByProductTypeId[$productTypeId] ??= ProductTypeSiteRecord::query()
            ->where('productTypeId', $productTypeId)
            ->get()
            ->map(fn (ProductTypeSiteRecord $record) => new ProductTypeSite([
                'id' => $record->id,
                'productTypeId' => $record->productTypeId,
                'siteId' => $record->siteId,
                'hasUrls' => (bool) $record->hasUrls,
                'uriFormat' => $record->uriFormat,
                'template' => $record->template,
            ]))
            ->all();
    }

    /**
     * Saves a product type to project config.
     *
     * @throws Throwable
     */
    public function saveProductType(ProductType $productType, bool $runValidation = true): bool
    {
        $isNew = ! $productType->id;

        event(new ProductTypeSaving($productType, $isNew));

        if ($runValidation && ! $productType->validate()) {
            Log::info('Product type not saved due to validation error.');

            return false;
        }

        if ($isNew) {
            $productType->uid = (string) Str::uuid();
        } else {
            $productType->uid = ProductTypeRecord::query()->whereKey($productType->id)->value('uid')
                ?? throw new RuntimeException("No product type exists with the ID '$productType->id'");
        }

        ProjectConfig::set(self::CONFIG_PRODUCTTYPES_KEY.'.'.$productType->uid, $productType->getConfig());

        if ($isNew) {
            $productType->id = DB::table(Table::PRODUCT_TYPES)->idByUid($productType->uid);
        }

        return true;
    }

    /**
     * Applies a product type's project config to the database.
     *
     * @throws Throwable
     */
    public function handleChangedProductType(ConfigEvent $event): void
    {
        ProjectConfigHelper::ensureAllSitesProcessed();
        ProjectConfigHelper::ensureAllFieldsProcessed();

        $productTypeUid = $event->tokenMatches[0];
        $data = $event->newValue;
        $siteSettingData = $data['siteSettings'] ?? [];

        DB::beginTransaction();

        try {
            $record = ProductTypeRecord::query()->where('uid', $productTypeUid)->first() ?? new ProductTypeRecord;
            $isNew = ! $record->exists;

            $record->uid = $productTypeUid;
            $record->name = $data['name'];
            $record->handle = $data['handle'];
            $record->skuFormat = $data['skuFormat'] ?? null;

            if (! empty($data['fieldLayouts']) && ! empty($config = reset($data['fieldLayouts']))) {
                $layout = FieldLayout::createFromConfig($config);
                $layout->id = $record->fieldLayoutId;
                $layout->type = Product::class;
                $layout->uid = key($data['fieldLayouts']);
                Fields::saveLayout($layout, false);
                $record->fieldLayoutId = $layout->id;
            } elseif ($record->fieldLayoutId) {
                Fields::deleteLayoutById($record->fieldLayoutId);
                $record->fieldLayoutId = null;
            }

            $record->save();

            $oldSiteSettingsRecords = $isNew ? [] : ProductTypeSiteRecord::query()
                ->where('productTypeId', $record->id)
                ->get()
                ->keyBy('siteId')
                ->all();

            $siteIdMap = DB::table(CraftTable::SITES)->idsByUids(array_keys($siteSettingData));

            foreach ($siteSettingData as $siteUid => $siteSettings) {
                $siteId = $siteIdMap[$siteUid];
                $siteSettingsRecord = $oldSiteSettingsRecords[$siteId] ?? new ProductTypeSiteRecord([
                    'productTypeId' => $record->id,
                    'siteId' => $siteId,
                ]);

                $siteSettingsRecord->hasUrls = (bool) $siteSettings['hasUrls'];
                $siteSettingsRecord->uriFormat = $siteSettingsRecord->hasUrls ? $siteSettings['uriFormat'] : null;
                $siteSettingsRecord->template = $siteSettingsRecord->hasUrls ? $siteSettings['template'] : null;
                $siteSettingsRecord->save();
            }

            // Drop site settings that are no longer used
            foreach ($oldSiteSettingsRecords as $siteId => $siteSettingsRecord) {
                if (! in_array($siteId, $siteIdMap, false)) {
                    $siteSettingsRecord->delete();
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->allProductTypes = null;
        unset($this->siteSettingsByProductTypeId[$record->id]);

        // Resave the existing products so their URIs, SKUs and search keywords stay in sync
        if (! $isNew) {
            foreach (array_values($siteIdMap) as $siteId) {
                dispatch(new ResaveElements(
                    elementType: Product::class,
                    criteria: [
                        'siteId' => $siteId,
                        'typeId' => $record->id,
                        'status' => null,
                    ],
                    description: t('Resaving {type} products ({site})', [
                        'type' => $record->name,
                        'site' => Sites::getSiteById($siteId)?->getName(),
                    ], category: 'digital-products'),
                ));
            }
        }

        $productType = $this->getProductTypeById($record->id);

        if ($productType) {
            event(new ProductTypeSaved($productType, $isNew));
        }
    }

    public function deleteProductTypeById(int $id): bool
    {
        $productType = $this->getProductTypeById($id);

        if (! $productType) {
            return false;
        }

        ProjectConfig::remove(self::CONFIG_PRODUCTTYPES_KEY.'.'.$productType->uid);

        return true;
    }

    /**
     * Deletes a product type (and its products) when it is removed from project config.
     *
     * @throws Throwable
     */
    public function handleDeletedProductType(ConfigEvent $event): void
    {
        $record = ProductTypeRecord::query()->where('uid', $event->tokenMatches[0])->first();

        if (! $record) {
            return;
        }

        DB::beginTransaction();

        try {
            $products = Product::find()
                ->typeId($record->id)
                ->status(null)
                ->site('*')
                ->unique()
                ->limit(null)
                ->all();

            foreach ($products as $product) {
                Elements::deleteElement($product);
            }

            if ($record->fieldLayoutId) {
                Fields::deleteLayoutById($record->fieldLayoutId);
            }

            $record->delete();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->allProductTypes = null;
        unset($this->siteSettingsByProductTypeId[$record->id]);
    }

    /**
     * Removes a deleted site from every product type's site settings.
     */
    public function pruneDeletedSite(SiteDeleted $event): void
    {
        $productTypes = ProjectConfig::get(self::CONFIG_PRODUCTTYPES_KEY);

        if (! is_array($productTypes)) {
            return;
        }

        foreach (array_keys($productTypes) as $productTypeUid) {
            ProjectConfig::remove(self::CONFIG_PRODUCTTYPES_KEY.'.'.$productTypeUid.'.siteSettings.'.$event->site->uid);
        }
    }

    /**
     * Copies the old primary site's settings to a newly-created site.
     */
    public function afterSaveSiteHandler(SiteSaved $event): void
    {
        if (! $event->isNew || ! $event->oldPrimarySiteId || ProjectConfig::isApplyingExternalChanges()) {
            return;
        }

        $oldPrimarySiteUid = DB::table(CraftTable::SITES)->uidById($event->oldPrimarySiteId);
        $productTypes = ProjectConfig::get(self::CONFIG_PRODUCTTYPES_KEY);

        if (! is_array($productTypes)) {
            return;
        }

        foreach ($productTypes as $productTypeUid => $config) {
            $primarySiteSettings = $config['siteSettings'][$oldPrimarySiteUid] ?? null;

            if ($primarySiteSettings === null) {
                continue;
            }

            ProjectConfig::set(
                self::CONFIG_PRODUCTTYPES_KEY.'.'.$productTypeUid.'.siteSettings.'.$event->site->uid,
                $primarySiteSettings,
            );
        }
    }

    public function isProductTypeTemplateValid(ProductType $productType, int $siteId): bool
    {
        $siteSettings = $productType->getSiteSettings()[$siteId] ?? null;

        if (! $siteSettings?->hasUrls || ! $siteSettings->template) {
            return false;
        }

        return app(TemplateResolver::class)->exists($siteSettings->template, TemplateMode::Site);
    }

    /**
     * Clears the memoized product types.
     */
    public function reset(): void
    {
        $this->allProductTypes = null;
        $this->siteSettingsByProductTypeId = [];
    }

    private function toData(ProductTypeRecord $record): ProductType
    {
        return new ProductType([
            'id' => $record->id,
            'fieldLayoutId' => $record->fieldLayoutId,
            'name' => $record->name,
            'handle' => $record->handle,
            'skuFormat' => $record->skuFormat,
            'uid' => $record->uid,
        ]);
    }
}
