<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Elements;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Data\EagerLoadPlan;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Query;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Controls\ElementSelect;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\Users;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Orders;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Database\Table;
use CraftCms\DigitalProducts\Elements\Queries\LicenseQuery;
use CraftCms\DigitalProducts\Licenses;
use CraftCms\DigitalProducts\Models\License as LicenseRecord;
use CraftCms\DigitalProducts\ProductTypes;
use CraftCms\DigitalProducts\Settings;
use CraftCms\DigitalProducts\Validation\LicenseRules;
use CraftCms\RulesetValidation\Attributes\Ruleset;
use Illuminate\Support\Facades\DB;
use Override;
use RuntimeException;

use function CraftCms\Cms\t;

/**
 * A license for a digital product, issued when an order is completed or paid, or created in the control panel.
 *
 * @property-read Product|null $product
 * @property-read Order|null $order
 * @property-read User|null $owner
 * @property-read string $licensedTo
 */
#[Ruleset(LicenseRules::class)]
class License extends Element
{
    public ?int $productId = null;

    public ?int $orderId = null;

    public ?string $licenseKey = null;

    public ?string $ownerName = null;

    public ?string $ownerEmail = null;

    public ?int $userId = null;

    private ?Product $product = null;

    private ?User $user = null;

    private ?Order $order = null;

    #[Override]
    public static function displayName(): string
    {
        return t('License', category: 'digital-products');
    }

    #[Override]
    public static function lowerDisplayName(): string
    {
        return t('license', category: 'digital-products');
    }

    #[Override]
    public static function pluralDisplayName(): string
    {
        return t('Licenses', category: 'digital-products');
    }

    #[Override]
    public static function pluralLowerDisplayName(): string
    {
        return t('licenses', category: 'digital-products');
    }

    #[Override]
    public static function hasStatuses(): bool
    {
        return true;
    }

    #[Override]
    public static function find(): LicenseQuery
    {
        return new LicenseQuery;
    }

    #[Override]
    protected static function defineSources(string $context): array
    {
        $productTypes = app(ProductTypes::class)->getAllProductTypes();

        $sources = [
            [
                'key' => '*',
                'label' => t('All product types', category: 'digital-products'),
                'criteria' => [],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
        ];

        if ($productTypes !== []) {
            $sources[] = ['heading' => t('Product Types', category: 'digital-products')];
        }

        foreach ($productTypes as $productType) {
            $key = 'productType:'.$productType->uid;

            $sources[$key] = [
                'key' => $key,
                'label' => t($productType->name, category: 'site'),
                'data' => ['handle' => $productType->handle],
                'criteria' => ['typeId' => $productType->id],
                'defaultSort' => ['dateCreated', 'desc'],
            ];
        }

        return $sources;
    }

    #[Override]
    protected static function defineFieldLayouts(?string $source): array
    {
        return [Fields::getLayoutByType(self::class)];
    }

    #[Override]
    protected static function defineTableAttributes(): array
    {
        return [
            'product' => ['label' => t('Licensed Product', category: 'digital-products')],
            'productType' => ['label' => t('Product Type', category: 'digital-products')],
            'licenseKey' => ['label' => t('License Key', category: 'digital-products')],
            'dateCreated' => ['label' => t('License Issue Date', category: 'digital-products')],
            'licensedTo' => ['label' => t('Licensed To', category: 'digital-products')],
            'orderLink' => ['label' => t('Associated Order', category: 'digital-products')],
            'id' => ['label' => t('ID')],
        ];
    }

    #[Override]
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = $source === '*' ? ['productType'] : [];

        return [...$attributes, 'product', 'dateCreated', 'licensedTo', 'orderLink'];
    }

    #[Override]
    protected static function defineSearchableAttributes(): array
    {
        return ['licensedTo', 'productName', 'licenseKey'];
    }

    #[Override]
    protected static function defineSortOptions(): array
    {
        return [
            [
                'label' => t('Owner', category: 'digital-products'),
                'orderBy' => Table::LICENSES.'.ownerEmail',
                'attribute' => 'licensedTo',
            ],
            [
                'label' => t('License issue date', category: 'digital-products'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
        ];
    }

    #[Override]
    protected static function prepElementQueryForTableAttribute(ElementQueryInterface $elementQuery, string $attribute): void
    {
        match ($attribute) {
            'product', 'productType' => $elementQuery->andWith('product'),
            'licensedTo' => $elementQuery->andWith('owner'),
            default => parent::prepElementQueryForTableAttribute($elementQuery, $attribute),
        };
    }

    #[Override]
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        $column = match ($handle) {
            'product' => 'productId',
            'order' => 'orderId',
            'owner' => 'userId',
            default => null,
        };

        if ($column === null) {
            return parent::eagerLoadingMap($sourceElements, $handle);
        }

        $map = DB::table(Table::LICENSES)
            ->select(['id as source', "$column as target"])
            ->whereIn('id', array_map(fn (self $element) => $element->id, $sourceElements))
            ->whereNotNull($column)
            ->get()
            ->map(fn (object $row) => (array) $row)
            ->all();

        return [
            'elementType' => match ($handle) {
                'product' => Product::class,
                'order' => Order::class,
                'owner' => User::class,
            },
            'map' => $map,
            // Licenses keep pointing at disabled or expired products
            'criteria' => $handle === 'product' ? ['status' => null] : [],
        ];
    }

    #[Override]
    public function setEagerLoadedElements(string $handle, array $elements, EagerLoadPlan $plan): void
    {
        $element = $elements[0] ?? null;

        match ($handle) {
            'product' => $this->product = $element instanceof Product ? $element : null,
            'owner' => $this->user = $element instanceof User ? $element : null,
            'order' => $this->order = $element instanceof Order ? $element : null,
            default => parent::setEagerLoadedElements($handle, $elements, $plan),
        };
    }

    #[Override]
    public function __toString(): string
    {
        return t('License for “{product}”', ['product' => $this->getProductName()], category: 'digital-products');
    }

    public function getName(): string
    {
        return t('License', category: 'digital-products');
    }

    #[Override]
    public function getFieldLayout(): ?FieldLayout
    {
        return Fields::getLayoutByType(self::class);
    }

    /**
     * Returns the email the license is assigned to: the owner's email, or the stored owner email.
     */
    public function getLicensedTo(): string
    {
        $owner = $this->getOwner();

        return (string) ($owner !== null ? $owner->email : $this->ownerEmail);
    }

    public function getOwner(): ?User
    {
        if ($this->user === null && $this->userId) {
            $this->user = app(Users::class)->getUserById($this->userId);
        }

        return $this->user;
    }

    public function getProduct(): ?Product
    {
        if ($this->product === null && $this->productId) {
            $this->product = Product::find()->id($this->productId)->status(null)->one();
        }

        return $this->product;
    }

    public function getOrder(): ?Order
    {
        if ($this->order === null && $this->orderId) {
            $this->order = app(Orders::class)->getOrderById($this->orderId);
        }

        return $this->order;
    }

    public function getProductType(): ?ProductType
    {
        $product = $this->getProduct();

        return $product?->typeId ? $product->getType() : null;
    }

    public function getProductName(): string
    {
        return (string) $this->getProduct();
    }

    /**
     * Returns the control panel URL of the order that issued the license.
     */
    public function getOrderEditUrl(): string
    {
        return $this->orderId ? Url::cpUrl('commerce/orders/'.$this->orderId) : '';
    }

    #[Override]
    protected function cpEditUrl(): ?string
    {
        return 'digital-products/licenses/'.$this->id;
    }

    #[Override]
    public function getPostEditUrl(): ?string
    {
        return Url::cpUrl('digital-products/licenses');
    }

    #[Override]
    protected function crumbs(): array
    {
        return [
            new ActionItem()->label(t('Digital Products', category: 'digital-products'))->href(Url::cpUrl('digital-products')),
            new ActionItem()->label(t('Licenses', category: 'digital-products'))->href(Url::cpUrl('digital-products/licenses')),
        ];
    }

    /**
     * Returns whether a real (not temporary) license key has been set.
     */
    public function hasLicenseKey(): bool
    {
        return $this->licenseKey !== null
            && $this->licenseKey !== ''
            && ! str_starts_with($this->licenseKey, Licenses::TEMP_KEY_PREFIX);
    }

    #[Override]
    protected function metadata(): array
    {
        if (! $this->hasLicenseKey()) {
            return [];
        }

        return [
            t('License Key', category: 'digital-products') => Html::tag('code', Html::encode($this->licenseKey)),
        ];
    }

    #[Override]
    protected function metaFieldsNodes(bool $static): array
    {
        $mode = $static ? ControlMode::Disabled : ControlMode::Editable;
        $productLocked = $this->id && ! $this->getIsFresh() && $this->productId !== null;

        $nodes = [
            Field::make(t('Product', category: 'digital-products'))
                ->required()
                ->control(ElementSelect::make('product')
                    ->elementType(Product::class)
                    ->criteria(['status' => null])
                    ->limit(1)
                    ->value(array_filter([$this->productId]))
                    ->mode($static || $productLocked ? ControlMode::Disabled : $mode)),
        ];

        // The key is editable until it's set; afterwards it's shown in the metadata
        if (! $this->hasLicenseKey()) {
            $nodes[] = Field::make(t('License Key', category: 'digital-products'))
                ->instructions(t('Leave blank to generate automatically.', category: 'digital-products'))
                ->control(Text::make('licenseKey')->monospace()->value(null)->mode($mode));
        }

        $nodes[] = Field::make(t('Owner', category: 'digital-products'))
            ->instructions(t('Required if Owner Email is blank.', category: 'digital-products'))
            ->control(ElementSelect::make('owner')
                ->elementType(User::class)
                ->limit(1)
                ->value(array_filter([$this->userId]))
                ->mode($mode));

        $nodes[] = Field::make(t('Owner Name', category: 'digital-products'))
            ->control(Text::make('ownerName')->value($this->ownerName)->mode($mode));

        if (! $this->userId) {
            $nodes[] = Field::make(t('Owner Email', category: 'digital-products'))
                ->instructions(t('Required if Owner is blank.', category: 'digital-products'))
                ->control(Text::make('ownerEmail')->inputType('email')->value($this->ownerEmail)->mode($mode));
        }

        return [...$nodes, ...parent::metaFieldsNodes($static)];
    }

    #[Override]
    protected function metaFieldsHtml(bool $static): string
    {
        $productLocked = $this->id && ! $this->getIsFresh() && $this->productId !== null;

        $fields = [
            FormFields::elementSelectFieldHtml([
                'label' => t('Product', category: 'digital-products'),
                'id' => 'product',
                'name' => 'product',
                'elementType' => Product::class,
                'criteria' => ['status' => null],
                'elements' => array_filter([$this->getProduct()]),
                'limit' => 1,
                'required' => true,
                'errors' => $this->errors()->get('productId'),
                'disabled' => $static || $productLocked,
            ]),
        ];

        if (! $this->hasLicenseKey()) {
            $fields[] = FormFields::textFieldHtml([
                'label' => t('License Key', category: 'digital-products'),
                'instructions' => t('Leave blank to generate automatically.', category: 'digital-products'),
                'id' => 'licenseKey',
                'name' => 'licenseKey',
                'class' => 'code',
                'errors' => $this->errors()->get('licenseKey'),
                'disabled' => $static,
            ]);
        }

        $fields[] = FormFields::elementSelectFieldHtml([
            'label' => t('Owner', category: 'digital-products'),
            'instructions' => t('Required if Owner Email is blank.', category: 'digital-products'),
            'id' => 'owner',
            'name' => 'owner',
            'elementType' => User::class,
            'elements' => array_filter([$this->getOwner()]),
            'limit' => 1,
            'errors' => $this->errors()->get('userId'),
            'disabled' => $static,
        ]);

        $fields[] = FormFields::textFieldHtml([
            'label' => t('Owner Name', category: 'digital-products'),
            'id' => 'ownerName',
            'name' => 'ownerName',
            'value' => $this->ownerName,
            'errors' => $this->errors()->get('ownerName'),
            'disabled' => $static,
        ]);

        if (! $this->userId) {
            $fields[] = FormFields::textFieldHtml([
                'label' => t('Owner Email', category: 'digital-products'),
                'instructions' => t('Required if Owner is blank.', category: 'digital-products'),
                'id' => 'ownerEmail',
                'name' => 'ownerEmail',
                'value' => $this->ownerEmail,
                'errors' => $this->errors()->get('ownerEmail'),
                'disabled' => $static,
            ]);
        }

        $fields[] = parent::metaFieldsHtml($static);

        return implode("\n", $fields);
    }

    #[Override]
    public function setAttributesFromRequest(array $values): void
    {
        // Element selects post arrays of IDs
        if (array_key_exists('product', $values)) {
            $productIds = (array) $values['product'];
            $this->productId = ($id = reset($productIds)) ? (int) $id : $this->productId;
            unset($values['product']);
        }

        if (array_key_exists('owner', $values)) {
            $userIds = (array) $values['owner'];
            $this->userId = ($id = reset($userIds)) ? (int) $id : null;
            unset($values['owner']);
        }

        foreach (['licenseKey', 'ownerEmail', 'ownerName'] as $attribute) {
            if (array_key_exists($attribute, $values)) {
                // A blank key field keeps the current (temporary) key
                if ($attribute === 'licenseKey' && ! $values[$attribute]) {
                    unset($values[$attribute]);

                    continue;
                }

                $this->$attribute = $values[$attribute] ?: null;
                unset($values[$attribute]);
            }
        }

        parent::setAttributesFromRequest($values);
    }

    #[Override]
    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'productType' => Html::encode((string) $this->getProductType()?->name),
            'licensedTo' => Html::encode($this->getLicensedTo()),
            'licenseKey' => $this->hasLicenseKey() ? Html::tag('code', Html::encode($this->licenseKey)) : '',
            'orderLink' => $this->orderId
                ? Html::a(t('View order', category: 'digital-products'), $this->getOrderEditUrl())
                : '',
            default => (string) parent::attributeHtml($attribute),
        };
    }

    #[Override]
    protected function searchKeywords(string $attribute): string
    {
        return match ($attribute) {
            'licensedTo' => $this->getLicensedTo(),
            'productName' => $this->getProductName(),
            default => parent::searchKeywords($attribute),
        };
    }

    #[Override]
    public function afterSave(bool $isNew): void
    {
        if (! $this->propagating) {
            $record = $isNew ? null : LicenseRecord::query()->find($this->id);

            if (! $isNew && ! $record) {
                throw new RuntimeException('Invalid license ID: '.$this->id);
            }

            $record ??= new LicenseRecord(['id' => $this->id]);

            if ($this->userId) {
                $user = app(Users::class)->getUserById($this->userId);
            } elseif ($this->ownerEmail) {
                $user = User::find()->email($this->ownerEmail)->status(null)->one();
            } else {
                $user = null;
            }

            // Assign the license to the user with a matching email, if the settings allow it
            if ($this->userId === null && $user && app(Settings::class)->autoAssignUserOnPurchase) {
                $this->userId = $user->id;
            }

            $record->ownerName = $user ? $user->getName() : $this->ownerName;
            $record->ownerEmail = $user ? $user->email : $this->ownerEmail;
            $record->userId = $this->userId;

            // The order is immutable once set
            if ($isNew) {
                $record->orderId = $this->orderId;
            }

            // The product and key are set once. A license created in the control panel has no product
            // until it is first saved with one, so it holds a temporary key until then.
            if ($isNew || $record->productId === null) {
                $record->productId = $this->productId;
                $record->licenseKey = $this->productId
                    ? app(Licenses::class)->generateLicenseKeyFor($this)
                    : ($this->hasLicenseKey() ? $this->licenseKey : Licenses::TEMP_KEY_PREFIX.Str::random(24));
                $this->licenseKey = $record->licenseKey;
            }

            $record->dateCreated = Query::prepareDateForDb($this->dateCreated ?? now());
            $record->dateUpdated = Query::prepareDateForDb($this->dateUpdated ?? now());

            $dirtyAttributes = array_keys($record->getDirty());
            $record->save();

            $this->setDirtyAttributes($dirtyAttributes);
        }

        parent::afterSave($isNew);
    }
}
