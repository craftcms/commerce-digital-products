<?php

namespace craft\digitalproducts\elements;

use Craft;
use craft\base\Element;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\db\Query;
use craft\digitalproducts\elements\db\LicenseQuery;
use craft\digitalproducts\events\GenerateKeyEvent;
use craft\digitalproducts\models\ProductType;
use craft\digitalproducts\Plugin as DigitalProducts;
use craft\digitalproducts\records\License as LicenseRecord;
use craft\elements\db\EagerLoadPlan;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use yii\base\InvalidConfigException;

/**
 * Class Commerce_LicenseElementType
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @since     1.0
 */
class License extends Element
{
    /**
     * @event GenerateKeyEvent The event that is triggered after a payment request is being built
     */
    public const EVENT_GENERATE_LICENSE_KEY = 'beforeGenerateLicenseKey';

    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var int|null Product id
     */
    public ?int $productId = null;

    /**
     * @var int|null Order id
     */
    public ?int $orderId = null;

    /**
     * @var string|null the license key
     */
    public ?string $licenseKey = null;

    /**
     * @var string|null License owner name
     */
    public ?string $ownerName = null;

    /**
     * @var string|null License owner email
     */
    public ?string $ownerEmail = null;

    /**
     * @var int|null License owner user id
     */
    public ?int $userId = null;

    /**
     * @var string|null
     */
    private ?string $_licensedTo = null;

    /**
     * @var Product|null
     */
    private ?Product $_product = null;

    /**
     * @var User|null
     */
    private ?User $_user = null;

    /**
     * @var Order|null
     */
    private ?Order $_order = null;

    /**
     * @return string
     */
    public function __toString(): string
    {
        return Craft::t('digital-products', 'License for “{product}”', ['product' => $this->getProductName()]);
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('digital-products', 'License');
    }

    /**
     * Return the email tied to the license.
     *
     * @return string
     */
    public function getLicensedTo(): string
    {
        if (null === $this->_licensedTo) {
            $this->_licensedTo = '';

            if (null !== $this->userId && null === $this->_user) {
                $this->_user = Craft::$app->getUsers()->getUserById($this->userId);
            }

            if ($this->_user) {
                $this->_licensedTo = $this->_user->email;
            } else {
                $this->_licensedTo = $this->ownerEmail;
            }
        }

        return $this->_licensedTo;
    }

    /**
     * Return the product tied to the license.
     *
     * @return Product|null
     */
    public function getProduct(): ?Product
    {
        if ($this->_product === null) {
            /** @var Product|null $_product */
            $_product = Product::find()
                ->id($this->productId)
                ->status(null)
                ->one();
            $this->_product = $_product;
        }

        return $this->_product;
    }

    /**
     * Return the order tied to the license.
     *
     * @return null|Order
     * @throws InvalidConfigException
     */
    public function getOrder(): ?Order
    {
        if ($this->_order === null && $this->orderId) {
            $this->_order = Commerce::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    /**
     * Return the product type for the product tied to the license.
     *
     * @return ProductType|null
     */
    public function getProductType(): ?ProductType
    {
        return $this->getProduct()?->getType();
    }

    /**
     * @return string
     */
    public function getProductName(): string
    {
        return (string)$this->getProduct();
    }

    /**
     * @inheritdoc
     */
    protected function cpEditUrl(): ?string
    {
        return 'digital-products/licenses/' . $this->id;
    }

    /**
     * @inheritdoc
     */
    protected function crumbs(): array
    {
        return [
            [
                'label' => Craft::t('digital-products', 'Licenses'),
                'url' => 'digital-products/licenses',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function metadata(): array
    {
        if (!$this->licenseKey) {
            return [];
        }

        return [
            Craft::t('digital-products', 'License Key') => Cp::renderTemplate('_includes/forms/copytextbtn.twig', [
                'value' => $this->licenseKey,
                'class' => 'code',
            ]),
        ];
    }

    /**
     * @inheritdoc
     */
    protected function metaFieldsHtml(bool $static): string
    {
        $fields = [];

        // Product (immutable after creation)
        $fields[] = Cp::elementSelectFieldHtml([
            'label' => Craft::t('digital-products', 'Product'),
            'id' => 'product',
            'name' => 'product',
            'elementType' => Product::class,
            'elements' => $this->productId ? Product::find()->id($this->productId)->status(null)->all() : [],
            'limit' => 1,
            'required' => true,
            'errors' => $this->getErrors('productId'),
            'disabled' => $static || ($this->id && !$this->getIsFresh() && $this->productId !== null),
        ]);

        // License key — editable only on new licenses; existing keys are shown in metadata()
        if (!$this->licenseKey) {
            $fields[] = Cp::textFieldHtml([
                'label' => Craft::t('digital-products', 'License Key'),
                'instructions' => Craft::t('digital-products', 'Leave blank to generate automatically.'),
                'id' => 'licenseKey',
                'name' => 'licenseKey',
                'class' => 'code',
                'value' => $this->licenseKey,
                'errors' => $this->getErrors('licenseKey'),
                'disabled' => $static,
            ]);
        }

        // Owner (user)
        $fields[] = Cp::elementSelectFieldHtml([
            'label' => Craft::t('digital-products', 'Owner'),
            'id' => 'owner',
            'name' => 'owner',
            'elementType' => User::class,
            'elements' => $this->userId ? User::find()->id($this->userId)->all() : [],
            'limit' => 1,
            'errors' => $this->getErrors('userId'),
            'disabled' => $static,
            'instructions' => Craft::t('digital-products', 'Required if Owner Email is blank.'),
        ]);

        // Owner name
        $fields[] = Cp::textFieldHtml([
            'label' => Craft::t('digital-products', 'Owner Name'),
            'id' => 'ownerName',
            'name' => 'ownerName',
            'value' => $this->ownerName,
            'errors' => $this->getErrors('ownerName'),
            'disabled' => $static,
        ]);

        // Owner email — only shown when no owner user is set
        if (!$this->userId) {
            $fields[] = Cp::textFieldHtml([
                'label' => Craft::t('digital-products', 'Owner Email'),
                'id' => 'ownerEmail',
                'name' => 'ownerEmail',
                'value' => $this->ownerEmail,
                'errors' => $this->getErrors('ownerEmail'),
                'required' => true,
                'disabled' => $static,
                'instructions' => Craft::t('digital-products', 'Required if Owner is blank.'),
            ]);
        }

        $fields[] = parent::metaFieldsHtml($static);

        return implode("\n", $fields);
    }

    /**
     * @inheritdoc
     */
    public function setAttributesFromRequest(array $values): void
    {
        // Element selects post as arrays of IDs
        if (isset($values['product'])) {
            $productIds = $values['product'];
            $this->productId = is_array($productIds) ? (reset($productIds) ?: null) : ($productIds ?: null);
            unset($values['product']);
        }

        if (isset($values['owner'])) {
            $userIds = $values['owner'];
            $this->userId = is_array($userIds) ? (reset($userIds) ?: null) : ($userIds ?: null);
            unset($values['owner']);
        }

        // These string properties are not covered by validation rules so setAttributes() would drop them
        if (array_key_exists('licenseKey', $values)) {
            $this->licenseKey = $values['licenseKey'] ?: null;
            unset($values['licenseKey']);
        }

        if (array_key_exists('ownerEmail', $values)) {
            $this->ownerEmail = $values['ownerEmail'] ?: null;
            unset($values['ownerEmail']);
        }

        if (array_key_exists('ownerName', $values)) {
            $this->ownerName = $values['ownerName'] ?: null;
            unset($values['ownerName']);
        }

        parent::setAttributesFromRequest($values);
    }

    /**
     * Get the link for editing the order that purchased this license.
     *
     * @return string
     */
    public function getOrderEditUrl(): string
    {
        if ($this->orderId) {
            return UrlHelper::cpUrl('commerce/orders/' . $this->orderId);
        }

        return '';
    }

    /**
     * @return null|string
     */
    public function getName(): ?string
    {
        return Craft::t('digital-products', 'License');
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
    public static function defineSources(string $context = null): array
    {
        $productTypes = DigitalProducts::getInstance()->getProductTypes()->getAllProductTypes();

        $productTypeIds = [];

        foreach ($productTypes as $productType) {
            $productTypeIds[] = $productType->id;
        }

        $sources = [
            '*' => [
                'label' => Craft::t('digital-products', 'All product types'),
                'criteria' => ['typeId' => $productTypeIds],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
        ];

        $sources[] = ['heading' => Craft::t('digital-products', 'Product Types')];

        foreach ($productTypes as $productType) {
            $key = 'productType:' . $productType->id;

            $sources[$key] = [
                'key' => $key,
                'label' => $productType->name,
                'data' => [
                    'handle' => $productType->handle,
                ],
                'criteria' => ['typeId' => $productType->id],
            ];
        }

        return $sources;
    }

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|false|null
    {
        $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

        if ($handle === 'product') {
            $map = (new Query())
                ->select('id as source, productId as target')
                ->from('{{%digitalproducts_licenses}}')
                ->where(['in', 'id', $sourceElementIds])
                ->all();

            return [
                'elementType' => Product::class,
                'map' => $map,
            ];
        }

        if ($handle === 'order') {
            $map = (new Query())
                ->select('id as source, orderId as target')
                ->from('{{%digitalproducts_licenses}}')
                ->where(['in', 'id', $sourceElementIds])
                ->andWhere(['not', ['orderId' => null]])
                ->all();

            return [
                'elementType' => Order::class,
                'map' => $map,
            ];
        }

        if ($handle === 'owner') {
            $map = (new Query())
                ->select('id as source, userId as target')
                ->from('{{%digitalproducts_licenses}}')
                ->where(['in', 'id', $sourceElementIds])
                ->andWhere(['not', ['userId' => null]])
                ->all();

            return [
                'elementType' => User::class,
                'map' => $map,
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    /**
     * @param string $handle
     * @param array|Product[]|User[]|Order[] $elements
     * @param EagerLoadPlan $plan
     */
    public function setEagerLoadedElements(string $handle, array $elements, EagerLoadPlan $plan): void
    {
        if ($handle === 'product') {
            $this->_product = $elements[0] ?? null;
            return;
        }

        if ($handle === 'owner') {
            $this->_user = $elements[0] ?? null;
            return;
        }

        if ($handle === 'order') {
            $this->_order = $elements[0] ?? null;
            return;
        }

        parent::setEagerLoadedElements($handle, $elements, $plan);
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules[] = [['productId'], 'required', 'on' => self::SCENARIO_LIVE];
        $rules[] = [
            'userId',
            'required',
            'message' => Craft::t('digital-products', 'A license must have either an email or an owner assigned to it.'),
            'when' => function($model) {
                return empty($model->ownerEmail);
            },
            'on' => self::SCENARIO_LIVE,
        ];

        return $rules;
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
    public function getFieldLayout(): ?FieldLayout
    {
        return Craft::$app->getFields()->getLayoutByType(self::class);
    }

    /**
     * @inheritdoc
     * @return LicenseQuery The newly created [[LicenseQuery]] instance.
     */
    public static function find(): LicenseQuery
    {
        return new LicenseQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            if (!$isNew) {
                $licenseRecord = LicenseRecord::findOne($this->id);

                if (!$licenseRecord) {
                    throw new InvalidConfigException('Invalid license id: ' . $this->id);
                }
            } else {
                $licenseRecord = new LicenseRecord();
                $licenseRecord->id = $this->id;
            }

            if ($this->userId) {
                $user = Craft::$app->getUsers()->getUserById($this->userId);
            } elseif ($this->ownerEmail) {
                $user = User::find()->email($this->ownerEmail)->one();
            } else {
                $user = null;
            }

            // Assign the license to a user if config allows for it, user id is left null and email matches
            if (DigitalProducts::getInstance()->getSettings()->autoAssignUserOnPurchase && $this->userId === null && $user) {
                $this->userId = $user->id;
            }

            $licenseRecord->ownerName = $user ? $user->name : $this->ownerName;
            $licenseRecord->ownerEmail = $user ? $user->email : $this->ownerEmail;
            $licenseRecord->userId = $this->userId;

            // Some properties of the license are immutable once set
            if ($isNew) {
                $licenseRecord->orderId = $this->orderId;
            }

            if ($isNew || $licenseRecord->productId === null) {
                $licenseRecord->productId = $this->productId;
                $licenseRecord->licenseKey = $this->generateKey();
            }

            $licenseRecord->dateUpdated = $this->dateUpdated;
            $licenseRecord->dateCreated = $this->dateCreated;

            $dirtyAttributes = array_keys($licenseRecord->getDirtyAttributes());
            $licenseRecord->save(false);

            $this->setDirtyAttributes($dirtyAttributes);
        }

        parent::afterSave($isNew);
    }

    /**
     * Generate a new license key.
     *
     * @return string
     */
    protected function generateKey(): string
    {
        // Use a manually-provided key if one was set before save
        if ($this->licenseKey) {
            return $this->licenseKey;
        }

        $generateKeyEvent = new GenerateKeyEvent(['license' => $this]);

        // Raising the 'beforeGenerateLicenseKey' event
        if ($this->hasEventHandlers(self::EVENT_GENERATE_LICENSE_KEY)) {
            $this->trigger(self::EVENT_GENERATE_LICENSE_KEY, $generateKeyEvent);
        }

        // If a plugin provided the license key - use that.
        if ($generateKeyEvent->licenseKey !== null) {
            return $generateKeyEvent->licenseKey;
        }

        do {
            $licenseKey = DigitalProducts::getInstance()->getLicenses()->generateLicenseKey();
        } while (!DigitalProducts::getInstance()->getLicenses()->isLicenseKeyUnique($licenseKey));

        return $licenseKey;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'product' => ['label' => Craft::t('digital-products', 'Licensed Product')],
            'productType' => ['label' => Craft::t('digital-products', 'Product Type')],
            'dateCreated' => ['label' => Craft::t('digital-products', 'License Issue Date')],
            'licensedTo' => ['label' => Craft::t('digital-products', 'Licensed To')],
            'orderLink' => ['label' => Craft::t('digital-products', 'Associated Order')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        if ($source === '*') {
            $attributes[] = 'productType';
        }

        $attributes[] = 'product';
        $attributes[] = 'dateCreated';
        $attributes[] = 'licensedTo';
        $attributes[] = 'orderLink';


        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['licensedTo', 'productName'];
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'productType':
                return $this->getProductType();

            case 'licensedTo':
                return $this->getLicensedTo();

            case 'orderLink':
                $url = $this->getOrderEditUrl();

                return $url ? '<a href="' . $url . '">' . Craft::t('digital-products', 'View order') . '</a>' : '';

            default:
                {
                    return parent::attributeHtml($attribute);
                }
        }
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'slug' => Craft::t('digital-products', 'Product name'),
            'ownerEmail' => Craft::t('digital-products', 'Owner'),
            [
                'label' => Craft::t('digital-products', 'License issue date'),
                'orderBy' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function prepElementQueryForTableAttribute(ElementQueryInterface $elementQuery, string $attribute): void
    {
        /** @var ElementQuery $elementQuery */
        if ($attribute === 'product') {
            $with = $elementQuery->with ?: [];
            $with[] = 'product';
            $elementQuery->with = $with;
            return;
        }

        if ($attribute === 'licensedTo') {
            $with = $elementQuery->with ?: [];
            $with[] = 'owner';
            $elementQuery->with = $with;
            return;
        }

        parent::prepElementQueryForTableAttribute($elementQuery, $attribute);
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
            $productType = $this->getProductType();
        } catch (\Exception) {
            return false;
        }

        return $user->can('digitalProducts-manageLicenses');
    }
}
