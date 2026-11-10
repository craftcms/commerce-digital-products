<?php

namespace craft\digitalproducts\services;

use Craft;
use craft\db\Query;
use craft\digitalproducts\elements\Product;
use craft\digitalproducts\events\ProductTypeEvent;
use craft\digitalproducts\models\ProductType;
use craft\digitalproducts\models\ProductTypeSite;
use craft\digitalproducts\records\ProductType as ProductTypeRecord;
use craft\digitalproducts\records\ProductTypeSite as ProductTypeSiteRecord;
use craft\events\ConfigEvent;
use craft\events\DeleteSiteEvent;
use craft\events\SiteEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\queue\jobs\ResaveElements;
use Throwable;
use yii\base\Component;
use yii\base\Exception;

/**
 * Product Type.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 */
class ProductTypes extends Component
{
    /**
     * @event ProductTypeEvent The event that is triggered before a category group is saved.
     */
    public const EVENT_BEFORE_SAVE_PRODUCTTYPE = 'beforeSaveProductType';

    /**
     * @event ProductTypeEvent The event that is triggered after a product type is saved.
     */
    public const EVENT_AFTER_SAVE_PRODUCTTYPE = 'afterSaveProductType';

    /**
     * @var bool
     */
    private $_fetchedAllProductTypes = false;

    /**
     * @var ProductType[]
     */
    private $_productTypesById;

    /**
     * @var ProductType[]
     */
    private $_productTypesByHandle;

    /**
     * @var int[]
     */
    private $_allProductTypeIds;

    /**
     * @var int[]
     */
    private $_editableProductTypeIds;

    /**
     * @var ProductTypeSite[][]
     */
    private $_siteSettingsByProductId = [];

    public const CONFIG_PRODUCTTYPES_KEY = 'digital-products.productTypes';

    /**
     * Returns all editable product types.
     *
     * @return ProductType[] An array of all the editable product types.
     */
    public function getEditableProductTypes(): array
    {
        $editableProductTypeIds = $this->getEditableProductTypeIds();
        $editableProductTypes = [];

        foreach ($this->getAllProductTypes() as $productType) {
            if (in_array($productType->id, $editableProductTypeIds, false)) {
                $editableProductTypes[] = $productType;
            }
        }

        return $editableProductTypes;
    }

    /**
     * Returns all of the product type IDs that are editable by the current user.
     *
     * @return array An array of all the editable product types’ IDs.
     */
    public function getEditableProductTypeIds(): array
    {
        if (null === $this->_editableProductTypeIds) {
            $this->_editableProductTypeIds = [];
            $allProductTypes = $this->getAllProductTypes();

            foreach ($allProductTypes as $productType) {
                if (Craft::$app->getUser()->checkPermission('digitalProducts-manageProductType:' . $productType->uid)) {
                    $this->_editableProductTypeIds[] = $productType->id;
                }
            }
        }

        return $this->_editableProductTypeIds;
    }

    /**
     * Returns all of the product type IDs.
     *
     * @return array An array of all the product types’ IDs.
     */
    public function getAllProductTypeIds(): array
    {
        if (null === $this->_allProductTypeIds) {
            $this->_allProductTypeIds = [];
            $productTypes = $this->getAllProductTypes();

            foreach ($productTypes as $productType) {
                $this->_allProductTypeIds[] = $productType->id;
            }
        }

        return $this->_allProductTypeIds;
    }

    /**
     * Returns all product types.
     *
     * @return ProductType[] An array of all product types.
     */
    public function getAllProductTypes(): array
    {
        if (!$this->_fetchedAllProductTypes) {
            $results = $this->_createProductTypeQuery()->all();

            foreach ($results as $result) {
                $this->_memoizeProductType(new ProductType($result));
            }

            $this->_fetchedAllProductTypes = true;
        }

        return $this->_productTypesById ?: [];
    }

    /**
     * Returns a product type by its UID.
     *
     * @param string $uid the product type's UID
     * @return ProductType|null either the product type or `null`
     * @since 2.4
     */
    public function getProductTypeByUid(string $uid)
    {
        return ArrayHelper::firstWhere($this->getAllProductTypes(), 'uid', $uid, true);
    }

    /**
     * Get a product type by it's handle.
     *
     * @param string $handle The product type's handle.
     * @return ProductType|null The product type or `null`.
     */
    public function getProductTypeByHandle($handle)
    {
        if (isset($this->_productTypesByHandle[$handle])) {
            return $this->_productTypesByHandle[$handle];
        }

        if ($this->_fetchedAllProductTypes) {
            return null;
        }

        $result = $this->_createProductTypeQuery()
            ->where(['handle' => $handle])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeProductType(new ProductType($result));

        return $this->_productTypesByHandle[$handle];
    }

    /**
     * Get an array of product type site settings for a product type by it's id.
     *
     * @param int $productTypeId The product type id.
     * @return array The product type settings.
     */
    public function getProductTypeSites($productTypeId): array
    {
        if (!isset($this->_siteSettingsByProductId[$productTypeId])) {
            $rows = (new Query())
                ->select([
                    'id',
                    'productTypeId',
                    'siteId',
                    'uriFormat',
                    'template',
                    'hasUrls',
                ])
                ->from('{{%digitalproducts_producttypes_sites}}')
                ->where(['productTypeId' => $productTypeId])
                ->all();

            $this->_siteSettingsByProductId[$productTypeId] = [];

            foreach ($rows as $row) {
                $this->_siteSettingsByProductId[$productTypeId][] = new ProductTypeSite($row);
            }
        }

        return $this->_siteSettingsByProductId[$productTypeId];
    }

    /**
     * Save a product type.
     *
     * @param ProductType $productType The product type model.
     * @param bool $runValidation If validation should be ran.
     *
     * @return bool Whether the product type was saved successfully.
     * @throws Throwable if reasons
     */
    public function saveProductType(ProductType $productType, bool $runValidation = true): bool
    {
        if ($runValidation && !$productType->validate()) {
            Craft::info('Product type not saved due to validation error.', __METHOD__);

            return false;
        }

        $isNewProductType = !$productType->id;

        // Fire a 'beforeSaveProductType' event
        $this->trigger(self::EVENT_BEFORE_SAVE_PRODUCTTYPE, new ProductTypeEvent([
            'productType' => $productType,
            'isNew' => $isNewProductType,
        ]));

        if (!$isNewProductType) {
            $productTypeRecord = ProductTypeRecord::findOne($productType->id);

            if (!$productTypeRecord) {
                throw new Exception("No product type exists with the ID '{$productType->id}'");
            }

            $productTypeUid = $productTypeRecord->uid;
        } else {
            $productTypeUid = StringHelper::UUID();
        }

        $projectConfig = Craft::$app->getProjectConfig();

        $configData = [
            'name' => $productType->name,
            'handle' => $productType->handle,
            'skuFormat' => $productType->skuFormat,
            'siteSettings' => [],
        ];

        $fieldLayout = $productType->getFieldLayout();
        $fieldLayoutConfig = $fieldLayout->getConfig();

        if ($fieldLayoutConfig) {
            if (empty($fieldLayout->id)) {
                $layoutUid = StringHelper::UUID();
                $fieldLayout->uid = $layoutUid;
            } else {
                $layoutUid = Db::uidById('{{%fieldlayouts}}', $fieldLayout->id);
            }

            $configData['fieldLayouts'] = [
                $layoutUid => $fieldLayoutConfig,
            ];
        }

        // Get the site settings
        $allSiteSettings = $productType->getSiteSettings();

        // Make sure they're all there
        foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
            if (!isset($allSiteSettings[$siteId])) {
                throw new Exception('Tried to save a product type that is missing site settings');
            }
        }

        foreach ($allSiteSettings as $siteId => $settings) {
            $siteUid = Db::uidById('{{%sites}}', $siteId);
            $configData['siteSettings'][$siteUid] = [
                'hasUrls' => $settings['hasUrls'],
                'uriFormat' => $settings['uriFormat'],
                'template' => $settings['template'],
            ];
        }

        $configPath = self::CONFIG_PRODUCTTYPES_KEY . '.' . $productTypeUid;
        $projectConfig->set($configPath, $configData);

        if ($isNewProductType) {
            $productType->id = Db::idByUid('{{%digitalproducts_producttypes}}', $productTypeUid);
        }

        // Might as well update our cache of the product type while we have it.
        $this->_productTypesById[$productType->id] = $productType;

        // Fire an 'afterSaveProductType' event
        $this->trigger(self::EVENT_AFTER_SAVE_PRODUCTTYPE, new ProductTypeEvent([
            'productType' => $productType,
            'isNew' => $isNewProductType,
        ]));

        return true;
    }

    /**
     * Handle product type change
     *
     * @param ConfigEvent $event
     */
    public function handleChangedProductType(ConfigEvent $event)
    {
        ProjectConfigHelper::ensureAllSitesProcessed();
        ProjectConfigHelper::ensureAllFieldsProcessed();

        $productTypeUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $siteSettingData = $data['siteSettings'];
            $productTypeRecord = $this->_getProductTypeRecord($productTypeUid);

            $productTypeRecord->uid = $productTypeUid;
            $productTypeRecord->name = $data['name'];
            $productTypeRecord->handle = $data['handle'];
            $productTypeRecord->skuFormat = $data['skuFormat'];

            if (!empty($data['fieldLayouts'])) {
                $fields = Craft::$app->getFields();

                // Delete the field layout
                if ($productTypeRecord->fieldLayoutId) {
                    $fields->deleteLayoutById($productTypeRecord->fieldLayoutId);
                }

                //Create the new layout
                $layout = FieldLayout::createFromConfig(reset($data['fieldLayouts']));
                $layout->type = Product::class;
                $layout->uid = key($data['fieldLayouts']);
                $fields->saveLayout($layout);
                $productTypeRecord->fieldLayoutId = $layout->id;
            } else {
                $productTypeRecord->fieldLayoutId = null;
            }

            $isNewProductType = $productTypeRecord->getIsNewRecord();

            // Save the product type
            $productTypeRecord->save(false);

            // Update the site settings
            // -----------------------------------------------------------------

            $allOldSiteSettingsRecords = [];

            if (!$isNewProductType) {
                // Get the old product type site settings
                $allOldSiteSettingsRecords = ProductTypeSiteRecord::find()
                    ->where(['productTypeId' => $productTypeRecord->id])
                    ->indexBy('siteId')
                    ->all();
            }

            $siteIdMap = Db::idsByUids('{{%sites}}', array_keys($siteSettingData));

            foreach ($siteSettingData as $siteUid => $siteSettings) {
                /** @var int $siteId */
                $siteId = $siteIdMap[$siteUid];

                // Was this already selected?
                if (!$isNewProductType && isset($allOldSiteSettingsRecords[$siteId])) {
                    /** @var ProductTypeSiteRecord $siteSettingsRecord */
                    $siteSettingsRecord = $allOldSiteSettingsRecords[$siteId];
                } else {
                    $siteSettingsRecord = new ProductTypeSiteRecord();
                    $siteSettingsRecord->productTypeId = $productTypeRecord->id;
                    $siteSettingsRecord->siteId = $siteId;
                }

                $siteSettingsRecord->hasUrls = (bool)$siteSettings['hasUrls'];
                $siteSettingsRecord->uriFormat = $siteSettings['uriFormat'];
                $siteSettingsRecord->template = $siteSettings['template'];

                $siteSettingsRecord->save(false);
            }

            if (!$isNewProductType) {
                // Drop any site settings that are no longer being used, as well as the associated product/element
                // site rows
                $affectedSiteUids = array_keys($siteSettingData);

                /** @noinspection PhpUndefinedVariableInspection */
                foreach ($allOldSiteSettingsRecords as $siteId => $siteSettingsRecord) {
                    $siteUid = array_search($siteId, $siteIdMap, false);
                    if (!in_array($siteUid, $affectedSiteUids, false)) {
                        $siteSettingsRecord->delete();
                    }
                }
            }

            // Finally, deal with the existing products...
            // -----------------------------------------------------------------

            if (!$isNewProductType) {
                // Resave products for each site
                $sitesService = Craft::$app->getSites();
                foreach ($siteSettingData as $siteUid => $siteSettings) {
                    Craft::$app->getQueue()->push(new ResaveElements([
                        'description' => Craft::t('app', 'Resaving {type} products ({site})', [
                            'type' => $productTypeRecord->name,
                            'site' => $sitesService->getSiteByUid($siteUid)->name,
                        ]),
                        'elementType' => Product::class,
                        'criteria' => [
                            'siteId' => $siteIdMap[$siteUid],
                            'typeId' => $productTypeRecord->id,
                            'status' => null,
                        ],
                    ]));
                }
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    /**
     * Delete a product type by it's id.
     *
     * @param int $id The product type's id.
     * @return bool Whether the product type was deleted successfully.
     * @throws Throwable if reasons
     */
    public function deleteProductTypeById(int $id): bool
    {
        $productType = $this->getProductTypeById($id);

        Craft::$app->getProjectConfig()->remove(self::CONFIG_PRODUCTTYPES_KEY . '.' . $productType->uid);

        return true;
    }

    /**
     * Handle a product type getting deleted
     *
     * @param ConfigEvent $event
     */
    public function handleDeletedProductType(ConfigEvent $event)
    {
        $productTypeUid = $event->tokenMatches[0];
        $productTypeRecord = $this->_getProductTypeRecord($productTypeUid);

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $productQuery = Product::find()
                ->typeId($productTypeRecord->id)
                ->anyStatus()
                ->limit(null);

            $elementsService = Craft::$app->getElements();

            foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
                foreach ($productQuery->siteId($siteId)->each() as $product) {
                    $elementsService->deleteElement($product);
                }
            }

            Craft::$app->getFields()->deleteLayoutById($productTypeRecord->fieldLayoutId);

            // Delete the section.
            Craft::$app->getDb()->createCommand()
                ->delete('{{%digitalproducts_producttypes}}', ['id' => $productTypeRecord->id])
                ->execute();

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    /**
     * Prune a deleted site from category group site settings.
     *
     * @param DeleteSiteEvent $event
     */
    public function pruneDeletedSite(DeleteSiteEvent $event)
    {
        $siteUid = $event->site->uid;

        $projectConfig = Craft::$app->getProjectConfig();
        $productTypes = $projectConfig->get(self::CONFIG_PRODUCTTYPES_KEY);

        // Loop through the product types and prune the UID from field layouts.
        if (is_array($productTypes)) {
            foreach ($productTypes as $productTypeUid => $productType) {
                $projectConfig->remove(self::CONFIG_PRODUCTTYPES_KEY . '.' . $productTypeUid . '.siteSettings.' . $siteUid);
            }
        }
    }

    /**
     * Adds a new product type setting row when a Site is added to Craft.
     *
     * @param SiteEvent $event The event that triggered this.
     */
    public function afterSaveSiteHandler(SiteEvent $event)
    {
        if ($event->isNew) {
            $primarySiteSettings = (new Query())
                ->select([
                    'productTypes.uid productTypeUid',
                    'producttypes_sites.uriFormat',
                    'producttypes_sites.template',
                    'producttypes_sites.hasUrls',
                ])
                ->from(['{{%digitalproducts_producttypes_sites}} producttypes_sites'])
                ->innerJoin(['{{%digitalproducts_producttypes}} productTypes'], '[[producttypes_sites.productTypeId]] = [[productTypes.id]]')
                ->where(['siteId' => $event->oldPrimarySiteId])
                ->one();

            if ($primarySiteSettings) {
                $newSiteSettings = [
                    'uriFormat' => $primarySiteSettings['uriFormat'],
                    'template' => $primarySiteSettings['template'],
                    'hasUrls' => $primarySiteSettings['hasUrls'],
                ];

                Craft::$app->getProjectConfig()->set(self::CONFIG_PRODUCTTYPES_KEY . '.' . $primarySiteSettings['productTypeUid'] . '.siteSettings.' . $event->site->uid, $newSiteSettings);
            }
        }
    }

    /**
     * Get a product's type by id.
     *
     * @param int $productTypeId The product type's id.
     * @return ProductType|null Either the product type or `null`.
     */
    public function getProductTypeById(int $productTypeId)
    {
        if (isset($this->_productTypesById[$productTypeId])) {
            return $this->_productTypesById[$productTypeId];
        }

        if ($this->_fetchedAllProductTypes) {
            return null;
        }

        $result = $this->_createProductTypeQuery()
            ->where(['id' => $productTypeId])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeProductType(new ProductType($result));

        return $this->_productTypesById[$productTypeId];
    }

    /**
     * Returns whether a product type’s products have URLs, and if the template path is valid.
     *
     * @param ProductType $productType The product type for which to validate the template.
     * @param int $siteId
     * @return bool Whether the template is valid.
     */
    public function isProductTypeTemplateValid(ProductType $productType, int $siteId): bool
    {
        $siteSettings = $productType->getSiteSettings();

        if (isset($siteSettings[$siteId]) && $siteSettings[$siteId]->hasUrls) {
            // Set Craft to the site template mode
            $view = Craft::$app->getView();
            $oldTemplateMode = $view->getTemplateMode();
            $view->setTemplateMode($view::TEMPLATE_MODE_SITE);

            // Does the template exist?
            $templateExists = Craft::$app->getView()->doesTemplateExist((string)$siteSettings[$siteId]->template);

            // Restore the original template mode
            $view->setTemplateMode($oldTemplateMode);

            if ($templateExists) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add new product type setting rows when a Site is added to Craft.
     *
     * @param SiteEvent $event The event that triggered this.
     */
    public function addSiteHandler(SiteEvent $event)
    {
        if ($event->isNew) {
            $allSiteSettings = (new Query())
                ->select(['productTypeId', 'uriFormat', 'template', 'hasUrls'])
                ->from(['{{%digitalproducts_producttypes_sites}}'])
                ->where(['siteId' => Craft::$app->getSites()->getPrimarySite()->id])
                ->all();

            if (!empty($allSiteSettings)) {
                $newSiteSettings = [];

                foreach ($allSiteSettings as $siteSettings) {
                    $newSiteSettings[] = [
                        $siteSettings['productTypeId'],
                        $event->site->id,
                        $siteSettings['uriFormat'],
                        $siteSettings['template'],
                        $siteSettings['hasUrls'],
                    ];
                }

                Craft::$app->getDb()->createCommand()
                    ->batchInsert(
                        '{{%digitalproducts_producttypes_sites}}',
                        ['productTypeId', 'siteId', 'uriFormat', 'template', 'hasUrls'],
                        $newSiteSettings)
                    ->execute();
            }
        }
    }

    /**
     * Memoize a product type
     *
     * @param ProductType $productType The product type to memoize.
     */
    private function _memoizeProductType(ProductType $productType)
    {
        $this->_productTypesById[$productType->id] = $productType;
        $this->_productTypesByHandle[$productType->handle] = $productType;
    }

    /**
     * Returns a Query object prepped for retrieving purchasables.
     *
     * @return Query The query object.
     */
    private function _createProductTypeQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'fieldLayoutId',
                'name',
                'handle',
                'skuFormat',
                'uid',
            ])
            ->from(['{{%digitalproducts_producttypes}}']);
    }

    /**
     * Gets a section's record by uid.
     *
     * @param string $uid
     * @return ProductTypeRecord
     */
    private function _getProductTypeRecord(string $uid): ProductTypeRecord
    {
        return ProductTypeRecord::findOne(['uid' => $uid]) ?? new ProductTypeRecord();
    }
}
