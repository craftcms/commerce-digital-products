<?php

namespace craft\digitalproducts\elements;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\behaviors\CurrencyAttributeBehavior;
use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin as Commerce;
use craft\db\Query;
use craft\digitalproducts\elements\db\ProductQuery;
use craft\digitalproducts\models\ProductType;
use craft\digitalproducts\Plugin as DigitalProducts;
use craft\digitalproducts\records\Product as ProductRecord;
use craft\elements\actions\Delete;
use craft\elements\actions\SetStatus;
use craft\elements\db\EagerLoadPlan;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\models\FieldLayout;
use craft\validators\DateTimeValidator;
use DateTime;
use yii\base\Exception;

/**
 * Class Commerce_ProductElementType
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.elementtypes
 * @since     1.0
 */
class Product extends Purchasable
{
    public const STATUS_LIVE = 'live';
    public const STATUS_PENDING = 'pending';
    public const STATUS_EXPIRED = 'expired';

    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var int|null Product type id
     */
    public ?int $typeId = null;

    /**
     * @var int|null Tax category id
     */
    public ?int $taxCategoryId = null;

    /**
     * @var DateTime|null Post date
     */
    public ?DateTime $postDate = null;

    /**
     * @var DateTime|null Expiry date
     */
    public ?DateTime $expiryDate = null;

    /**
     * @var bool Promotable
     */
    public bool $promotable = false;

    /**
     * @var ProductType|null
     */
    private ?ProductType $_productType = null;

    /**
     * @var License[]
     */
    private ?array $_existingLicenses = null;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('digital-products', 'Digital Product');
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->title;
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['currencyAttributes'] = [
            'class' => CurrencyAttributeBehavior::class,
            'currencyAttributes' => $this->currencyAttributes(),
        ];
        return $behaviors;
    }

    /**
     * @return null|string
     */
    public function getName(): ?string
    {
        return $this->title;
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function defineSources(string $context = null): array
    {
        if ($context === 'index') {
            $productTypes = DigitalProducts::getInstance()->getProductTypes()->getEditableProductTypes();
            $editable = true;
        } else {
            $productTypes = DigitalProducts::getInstance()->getProductTypes()->getAllProductTypes();
            $editable = false;
        }

        $productTypeIds = [];

        foreach ($productTypes as $productType) {
            $productTypeIds[] = $productType->id;
        }


        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('digital-products', 'All products'),
                'criteria' => [
                    'typeId' => $productTypeIds,
                    'editable' => $editable,
                ],
                'defaultSort' => ['postDate', 'desc'],
            ],
        ];

        $sources[] = ['heading' => Craft::t('digital-products', 'Product Types')];

        foreach ($productTypes as $productType) {
            $key = 'productType:' . $productType->uid;
            $canEditProducts = Craft::$app->getUser()->checkPermission('digitalProducts-manageProductType:' . $productType->uid);

            $sources[$key] = [
                'key' => $key,
                'label' => $productType->name,
                'data' => [
                    'handle' => $productType->handle,
                    'editable' => $canEditProducts,
                ],
                'criteria' => ['typeId' => $productType->id, 'editable' => $editable],
            ];
        }

        return $sources;
    }

    /**
     * @inheritdoc
     */
    public function getStatuses(): array
    {
        return [
            self::STATUS_LIVE => Craft::t('digital-products', 'Live'),
            self::STATUS_PENDING => Craft::t('digital-products', 'Pending'),
            self::STATUS_EXPIRED => Craft::t('digital-products', 'Expired'),
            self::STATUS_DISABLED => Craft::t('digital-products', 'Disabled'),
        ];
    }


    /**
     * @param string $handle
     * @param array|License[] $elements
     * @param EagerLoadPlan $plan
     */
    public function setEagerLoadedElements(string $handle, array $elements, EagerLoadPlan $plan): void
    {
        if ($handle === 'existingLicenses') {
            $this->_existingLicenses = $elements;

            return;
        }

        parent::setEagerLoadedElements($handle, $elements, $plan);
    }

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        if ($handle === 'existingLicenses') {
            $userId = Craft::$app->getUser()->getId();

            if ($userId) {
                $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

                $map = (new Query())
                    ->select('productId as source, id as target')
                    ->from('{{%digitalproducts_licenses}}')
                    ->where(['in', 'productId', $sourceElementIds])
                    ->andWhere(['userId' => $userId])
                    ->all();

                return [
                    'elementType' => License::class,
                    'map' => $map,
                ];
            }
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    /**
     * @inheritdoc
     */
    public function getIsAvailable(): bool
    {
        return $this->getStatus() === static::STATUS_LIVE;
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): ?string
    {
        $status = parent::getStatus();

        if ($status === static::STATUS_ENABLED && $this->postDate) {
            $currentTime = DateTimeHelper::currentTimeStamp();
            $postDate = $this->postDate->getTimestamp();
            $expiryDate = $this->expiryDate ? $this->expiryDate->getTimestamp() : null;

            if ($postDate <= $currentTime && (!$expiryDate || $expiryDate > $currentTime)) {
                return static::STATUS_LIVE;
            }

            if ($postDate > $currentTime) {
                return static::STATUS_PENDING;
            }

            return static::STATUS_EXPIRED;
        }

        return $status;
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['typeId'], 'required'];
        $rules[] = [['sku'], 'string', 'max' => 255];
        $rules[] = [['postDate', 'expiryDate'], DateTimeValidator::class];

        return $rules;
    }


    /**
     * @inheritdoc
     *
     * @return ProductQuery The newly created [[ProductQuery]] instance.
     */
    public static function find(): ProductQuery
    {
        return new ProductQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'postDate';
        $attributes[] = 'expiryDate';

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public function getIsEditable(): bool
    {
        if ($this->getType()) {
            $uid = $this->getType()->uid;

            return Craft::$app->getUser()->checkPermission('digitalProducts-manageProductType:' . $uid);
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasDrafts(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function cpEditUrl(): ?string
    {
        $productType = $this->getType();

        if (!$productType) {
            return null;
        }

        return sprintf('digital-products/products/%s/%s', $productType->handle, $this->getCanonicalId());
    }

    /**
     * @inheritdoc
     */
    protected function crumbs(): array
    {
        $productType = $this->getType();

        $productTypes = DigitalProducts::getInstance()->getProductTypes()->getEditableProductTypes();
        $productTypeItems = array_map(fn(ProductType $t) => [
            'label' => Craft::t('site', $t->name),
            'url' => 'digital-products/products/' . $t->handle,
            'selected' => $productType && $t->id === $productType->id,
        ], $productTypes);

        return [
            [
                'label' => Craft::t('digital-products', 'Products'),
                'url' => 'digital-products/products',
            ],
            [
                'menu' => [
                    'label' => Craft::t('digital-products', 'Select product type'),
                    'items' => $productTypeItems,
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function metaFieldsHtml(bool $static): string
    {
        $fields = [];

        $fields[] = $this->slugFieldHtml($static);

        $fields[] = Cp::dateTimeFieldHtml([
            'status' => $this->getAttributeStatus('postDate'),
            'label' => Craft::t('digital-products', 'Post Date'),
            'id' => 'postDate',
            'name' => 'postDate',
            'value' => $this->postDate,
            'errors' => $this->getErrors('postDate'),
            'disabled' => $static,
        ]);

        $fields[] = Cp::dateTimeFieldHtml([
            'status' => $this->getAttributeStatus('expiryDate'),
            'label' => Craft::t('digital-products', 'Expiry Date'),
            'id' => 'expiryDate',
            'name' => 'expiryDate',
            'value' => $this->expiryDate,
            'errors' => $this->getErrors('expiryDate'),
            'disabled' => $static,
        ]);

        $fields[] = parent::metaFieldsHtml($static);

        $fields[] = Cp::lightswitchFieldHtml([
            'label' => Craft::t('digital-products', 'Promotable'),
            'id' => 'promotable',
            'name' => 'promotable',
            'on' => $this->promotable,
            'disabled' => $static,
        ]);

        return implode("\n", $fields);
    }

    /**
     * @inheritdoc
     */
    public function setAttributesFromRequest(array $values): void
    {
        if (isset($values['taxCategoryId'])) {
            $this->taxCategoryId = (int)$values['taxCategoryId'] ?: null;
            unset($values['taxCategoryId']);
        }

        if (array_key_exists('promotable', $values)) {
            $this->promotable = (bool)$values['promotable'];
            unset($values['promotable']);
        }

        parent::setAttributesFromRequest($values);
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?FieldLayout
    {
        $productType = $this->getType();

        return $productType ? $productType->getProductFieldLayout() : null;
    }

    /**
     * @inheritdoc
     */
    public function getUriFormat(): ?string
    {
        $productType = $this->getType();

        if ($productType === null) {
            return null;
        }

        $siteId = $this->siteId ?: Craft::$app->getSites()->currentSite->id;

        if (isset($productType->siteSettings[$siteId]) && $productType->siteSettings[$siteId]->hasUrls) {
            $productTypeSites = $productType->getSiteSettings();

            if (isset($productTypeSites[$this->siteId])) {
                return $productTypeSites[$this->siteId]->uriFormat;
            }
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public static function hasUris(): bool
    {
        return true;
    }

    /**
     * Returns the product's product type model.
     *
     * @return ProductType|null
     */
    public function getType(): ?ProductType
    {
        if ($this->_productType === null && $this->typeId) {
            $this->_productType = DigitalProducts::getInstance()->getProductTypes()->getProductTypeById($this->typeId);
        }

        return $this->_productType;
    }

    /**
     * Gets the tax category
     *
     * @return TaxCategory
     */
    public function getTaxCategory(): TaxCategory
    {
        if ($this->taxCategoryId) {
            return Commerce::getInstance()->getTaxCategories()->getTaxCategoryById($this->taxCategoryId);
        }

        return Commerce::getInstance()->getTaxCategories()->getDefaultTaxCategory();
    }

    /**
     * Return all the licenses for this product for the current user.
     *
     * @return License[]
     */
    public function getExistingLicenses(): array
    {
        if ($this->_existingLicenses === null) {
            $this->_existingLicenses = [];
            $userId = Craft::$app->getUser()->getId();

            if ($userId) {
                /** @var License[]|null $existingLicenses */
                $existingLicenses = License::find()->ownerId($userId)->all();
                $this->_existingLicenses = $existingLicenses;
            }
        }

        return $this->_existingLicenses;
    }

    /**
     * @inheritdoc
     * @since 2.4
     */
    public function getGqlTypeName(): string
    {
        return static::gqlTypeNameByContext($this->getType());
    }

    /**
     * @inheritdoc
     * @since 2.4
     */
    public static function gqlTypeNameByContext($context): string
    {
        /** @var ProductType $context */
        return $context->handle . '_Product';
    }

    /**
     * @inheritdoc
     * @since 2.4
     */
    public static function gqlScopesByContext(mixed $context): array
    {
        /** @var ProductType $context */
        return ['digitalProductTypes.' . $context->uid];
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            if (!$isNew) {
                $productRecord = ProductRecord::findOne($this->id);

                if (!$productRecord) {
                    throw new Exception('Invalid product id: ' . $this->id);
                }
            } else {
                $productRecord = new ProductRecord();
                $productRecord->id = $this->id;
            }

            $productRecord->postDate = $this->postDate;
            $productRecord->expiryDate = $this->expiryDate;
            $productRecord->typeId = $this->typeId;
            $productRecord->promotable = (bool)$this->promotable;
            $productRecord->taxCategoryId = $this->taxCategoryId;

            // Generate SKU if empty
            if (empty($this->getSku())) {
                try {
                    $productType = DigitalProducts::getInstance()->getProductTypes()->getProductTypeById($this->typeId);
                    $this->setSku(Craft::$app->getView()->renderObjectTemplate($productType->skuFormat, $this));
                } catch (\Exception $e) {
                    $this->setSku('');
                }
            }

            $productRecord->dateUpdated = $this->dateUpdated;
            $productRecord->dateCreated = $this->dateCreated;

            $dirtyAttributes = array_keys($productRecord->getDirtyAttributes());
            $productRecord->save(false);

            $this->setDirtyAttributes($dirtyAttributes);
        }

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function getPurchasableId(): ?int
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getSnapshot(): array
    {
        return $this->getAttributes();
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return $this->title;
    }

    /**
     * @inheritdoc
     */
    public function getTaxCategoryId(): int
    {
        return $this->taxCategoryId;
    }

    /**
     * @inheritdoc
     */
    public function hasFreeShipping(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function getIsShippable(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getIsPromotable(): bool
    {
        return $this->promotable;
    }

    /**
     * @inheritdoc
     */
    protected function route(): array|string|null
    {
        // Make sure the product type is set to have URLs for this site
        $siteId = Craft::$app->getSites()->currentSite->id;
        $productTypeSiteSettings = $this->getType()->getSiteSettings();

        if (!isset($productTypeSiteSettings[$siteId]) || !$productTypeSiteSettings[$siteId]->hasUrls) {
            return null;
        }

        return [
            'templates/render', [
                'template' => (string)$productTypeSiteSettings[$siteId]->template,
                'variables' => [
                    'product' => $this,
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('digital-products', 'Title')],
            'type' => ['label' => Craft::t('digital-products', 'Type')],
            'slug' => ['label' => Craft::t('digital-products', 'Slug')],
            'sku' => ['label' => Craft::t('digital-products', 'SKU')],
            'price' => ['label' => Craft::t('digital-products', 'Price')],
            'postDate' => ['label' => Craft::t('digital-products', 'Post Date')],
            'expiryDate' => ['label' => Craft::t('digital-products', 'Expiry Date')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        if ($source === '*') {
            $attributes[] = 'type';
        }

        $attributes[] = 'postDate';
        $attributes[] = 'expiryDate';

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['title'];
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        /* @var $productType ProductType */
        $productType = $this->getType();

        switch ($attribute) {
            case 'type':
                return ($productType ? Craft::t('site', Html::encode($productType->name)) : '');

            case 'taxCategory':
                $taxCategory = $this->getTaxCategory();

                return Craft::t('site', Html::encode($taxCategory->name));

            case 'price':
                $code = Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

                return Craft::$app->getLocale()->getFormatter()->asCurrency($this->basePrice, strtoupper($code));

            case 'promotable':
                return ($this->$attribute ? '<span data-icon="check" title="' . Craft::t('digital-products', 'Yes') . '"></span>' : '');

            default:
                return parent::attributeHtml($attribute);
        }
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('digital-products', 'Title'),
            [
                'label' => Craft::t('digital-products', 'Post Date'),
                'orderBy' => 'postDate',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('digital-products', 'Expiry Date'),
                'orderBy' => 'expiryDate',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('digital-products', 'Price'),
                'orderBy' => 'purchasables_stores.basePrice',
                'defaultDir' => 'asc',
            ],
        ];
    }

    /**
     * @inheritdoc
     * @since 2.4
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = parent::defineActions($source);

        // Get the section(s) we need to check permissions on
        if ($source == '*') {
            $productTypes = DigitalProducts::getInstance()->getProductTypes()->getEditableProductTypes();
        } elseif (preg_match('/^productType:(\d+)$/', $source, $matches)) {
            $productType = DigitalProducts::getInstance()->getProductTypes()->getProductTypeById((int)$matches[1]);

            if ($productType) {
                $productTypes = [$productType];
            }
        } elseif (preg_match('/^productType:(.+)$/', $source, $matches)) {
            $productType = DigitalProducts::getInstance()->getProductTypes()->getProductTypeByUid($matches[1]);

            if ($productType) {
                $productTypes = [$productType];
            }
        }

        if (!empty($productTypes)) {
            $userSession = Craft::$app->getUser();
            $canManage = false;

            foreach ($productTypes as $productType) {
                $canManage = $userSession->checkPermission('digitalProducts-manageProductType:' . $productType->uid);
            }

            if ($canManage) {
                // Allow deletion
                $deleteAction = Craft::$app->getElements()->createAction([
                    'type' => Delete::class,
                    'confirmationMessage' => Craft::t('digital-products', 'Are you sure you want to delete the selected products?'),
                    'successMessage' => Craft::t('digital-products', 'Products deleted.'),
                ]);
                $actions[] = $deleteAction;
                $actions[] = SetStatus::class;
            }
        }

        return $actions;
    }

    /**
     * @inheritdoc
     */
    public function canView(User $user): bool
    {
        return $this->_canManageProductType($user);
    }

    /**
     * @inheritdoc
     */
    public function canSave(User $user): bool
    {
        return $this->_canManageProductType($user);
    }

    /**
     * @inheritdoc
     */
    public function canDelete(User $user): bool
    {
        return $this->_canManageProductType($user);
    }

    /**
     * @param User $user
     * @return bool
     */
    private function _canManageProductType(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }

        try {
            $productType = $this->getType();
        } catch (\Exception) {
            return false;
        }

        return $user->can('digitalProducts-manageProductType:' . $productType->uid);
    }
}
