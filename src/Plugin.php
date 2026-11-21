<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts;

use Closure;
use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\GarbageCollection\Events\RunningGarbageCollection;
use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use CraftCms\Cms\Gql\GqlQueries;
use CraftCms\Cms\Gql\GqlTypes;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\ProjectConfig\Events\ProjectConfigRebuilt;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Site\Events\SiteDeleted;
use CraftCms\Cms\Site\Events\SiteSaved;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Twig\Variables\CraftVariable;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Number;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\User\Data\Permission;
use CraftCms\Cms\User\Events\UserActivated;
use CraftCms\Commerce\Order\Events\OrderCompleted;
use CraftCms\Commerce\Order\Events\OrderPaid;
use CraftCms\Commerce\Payment\Events\PaymentProcessing;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableAvailableForPurchaseField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasablePriceField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasablePromotableField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableSkuField;
use CraftCms\Commerce\Purchasable\PurchasableTypes;
use CraftCms\DigitalProducts\Console\Commands\MigrateToCommerceCommand;
use CraftCms\DigitalProducts\Console\Commands\ResaveDigitalProductsCommand;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Elements\License;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\FieldLayoutElements\ProductTitleField;
use CraftCms\DigitalProducts\Fields\Products as ProductsField;
use CraftCms\DigitalProducts\Gql\Interfaces\Elements\Product as GqlProductInterface;
use CraftCms\DigitalProducts\Gql\Queries\Product as GqlProductQuery;
use CraftCms\DigitalProducts\Listeners\AssignLicensesOnUserActivation;
use CraftCms\DigitalProducts\Listeners\GqlSchemaComponentsResolvingListener;
use CraftCms\DigitalProducts\Listeners\IssueLicensesOnOrderCompleted;
use CraftCms\DigitalProducts\Listeners\IssueLicensesOnOrderPaid;
use CraftCms\DigitalProducts\Listeners\PreventGuestPayment;
use CraftCms\DigitalProducts\Listeners\ProjectConfigRebuiltListener;
use CraftCms\DigitalProducts\Listeners\RunningGarbageCollectionListener;
use CraftCms\DigitalProducts\Listeners\SiteDeletedListener;
use CraftCms\DigitalProducts\Listeners\SiteSavedListener;
use CraftCms\DigitalProducts\Policies\LicensePolicy;
use CraftCms\DigitalProducts\Policies\ProductPolicy;
use CraftCms\DigitalProducts\Twig\DigitalProductsVariable;
use Illuminate\Support\Facades\Gate;
use Override;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 * Digital Products for Craft Commerce.
 *
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public const string HANDLE = 'digital-products';

    public string $schemaVersion = '6.0.0.0';

    /**
     * The newest 4.x schema, so sites upgrade through the last Craft 5 release first.
     */
    public string $minVersionRequired = '4.0.0';

    public bool $hasCpSection = true;

    public bool $hasCpSettings = true;

    protected array $elementTypes = [
        Product::class,
        License::class,
    ];

    protected array $fieldTypes = [
        ProductsField::class,
    ];

    protected array $events = [
        OrderCompleted::class => IssueLicensesOnOrderCompleted::class,
        OrderPaid::class => IssueLicensesOnOrderPaid::class,
        PaymentProcessing::class => PreventGuestPayment::class,
        UserActivated::class => AssignLicensesOnUserActivation::class,
        SiteSaved::class => SiteSavedListener::class,
        SiteDeleted::class => SiteDeletedListener::class,
        ProjectConfigRebuilt::class => ProjectConfigRebuiltListener::class,
        RunningGarbageCollection::class => RunningGarbageCollectionListener::class,
        GqlSchemaComponentsResolving::class => GqlSchemaComponentsResolvingListener::class,
    ];

    #[Override]
    public function register(): void
    {
        $this->app->singleton(Settings::class, fn () => $this->getSettings());
    }

    public function boot(): void
    {
        app(PurchasableTypes::class)->register(Product::class);

        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(License::class, LicensePolicy::class);

        app(GqlTypes::class)->register(GqlProductInterface::class);
        app(GqlQueries::class)->register(GqlProductQuery::class);
        CraftVariable::macro('digitalProducts', fn () => app(DigitalProductsVariable::class));
        $this->commands([ResaveDigitalProductsCommand::class, MigrateToCommerceCommand::class]);

        if ($this->isInstalled) {
            $this->registerProjectConfigListeners();
        }
    }

    #[Override]
    protected static function createSettings(): Settings
    {
        return new Settings;
    }

    #[Override]
    public function getSettingsResponse(): mixed
    {
        return redirect(Url::cpUrl('digital-products/settings'));
    }

    #[Override]
    public function settingsUi(UiContext $context = new UiContext): Ui
    {
        return Ui::make([
            Field::make(t('Auto-assign licenses on purchase', category: 'digital-products'), Lightswitch::make('autoAssignUserOnPurchase'))
                ->instructions(t('Whether licenses should automatically be assigned to existing users if the emails match.', category: 'digital-products')),
            Field::make(t('Auto-assign licenses on registration', category: 'digital-products'), Lightswitch::make('autoAssignLicensesOnUserRegistration'))
                ->instructions(t('Whether licenses should be automatically assigned to newly-registered users if the emails match.', category: 'digital-products')),
            Field::make(t('License key characters', category: 'digital-products'), Text::make('licenseKeyCharacters')->monospace())
                ->instructions(t('The available characters that can be used in license key generation.', category: 'digital-products'))
                ->required(),
            Field::make(t('Key length', category: 'digital-products'), Number::make('licenseKeyLength')->min(1)->max(255)->size(5))
                ->instructions(t('The length of generated license keys.', category: 'digital-products'))
                ->required(),
            Field::make(t('Require a logged in user', category: 'digital-products'), Lightswitch::make('requireLoggedInUser'))
                ->instructions(t('Whether a user must be logged in when completing an order with at least one digital product in the cart.', category: 'digital-products')),
            Field::make(t('Generate license on order paid in full', category: 'digital-products'), Lightswitch::make('generateLicenseOnOrderPaid'))
                ->instructions(t('Whether the license should be generated when the order is paid for in full. If not, the license will be generated when the order is completed.', category: 'digital-products')),
        ]);
    }

    #[Override]
    protected function cpNavIconPath(): ?string
    {
        return 'file-arrow-down';
    }

    #[Override]
    public function getCpNavItem(): NavItem|array|null
    {
        $item = parent::getCpNavItem();

        if (! $item instanceof NavItem) {
            return $item;
        }

        $user = currentUser();
        $subnav = [];

        if (app(ProductTypes::class)->getEditableProductTypes() !== []) {
            $subnav[] = new NavItem()
                ->label(t('Products', category: 'digital-products'))
                ->url('digital-products/products');
        }

        if ($user?->can('digitalProducts-manageLicenses')) {
            $subnav[] = new NavItem()
                ->label(t('Licenses', category: 'digital-products'))
                ->url('digital-products/licenses');
        }

        if ($user?->isAdmin()) {
            $subnav[] = new NavItem()
                ->label(t('Settings', category: 'app'))
                ->url('digital-products/settings');
        }

        return $item->subnav($subnav);
    }

    #[Override]
    protected function getPermissions(): array
    {
        $productTypePermissions = collect(app(ProductTypes::class)->getAllProductTypes())
            ->map(fn (ProductType $productType) => new Permission(
                key: "digitalProducts-manageProductType:$productType->uid",
                label: t('Manage “{type}” products', ['type' => t($productType->name, category: 'site')], 'digital-products'),
            ))
            ->values();

        return [
            new Permission(
                key: 'digitalProducts-manageProducts',
                label: t('Manage products', category: 'digital-products'),
                nested: $productTypePermissions,
            ),
            new Permission(
                key: 'digitalProducts-manageLicenses',
                label: t('Manage licenses', category: 'digital-products'),
            ),
            new Permission(
                key: 'digitalProducts-manageProductTypes',
                label: t('Manage product types', category: 'digital-products'),
            ),
        ];
    }

    #[Override]
    protected function getNativeFields(): ?Closure
    {
        return function (FieldLayout $fieldLayout, array $fields): array {
            if ($fieldLayout->type === Product::class) {
                $fields[] = ProductTitleField::class;
                $fields[] = PurchasableSkuField::class;
                $fields[] = PurchasablePriceField::class;
                $fields[] = PurchasableAvailableForPurchaseField::class;
                $fields[] = PurchasablePromotableField::class;
            }

            return $fields;
        };
    }

    private function registerProjectConfigListeners(): void
    {
        $projectConfig = app(ProjectConfig::class);
        $productTypes = app(ProductTypes::class);
        $licenses = app(Licenses::class);

        $projectConfig->onAdd(ProductTypes::CONFIG_PRODUCTTYPES_KEY.'.{uid}', $productTypes->handleChangedProductType(...))
            ->onUpdate(ProductTypes::CONFIG_PRODUCTTYPES_KEY.'.{uid}', $productTypes->handleChangedProductType(...))
            ->onRemove(ProductTypes::CONFIG_PRODUCTTYPES_KEY.'.{uid}', $productTypes->handleDeletedProductType(...));

        $projectConfig->onAdd(Licenses::CONFIG_FIELDLAYOUT_KEY, $licenses->handleChangedFieldLayout(...))
            ->onUpdate(Licenses::CONFIG_FIELDLAYOUT_KEY, $licenses->handleChangedFieldLayout(...))
            ->onRemove(Licenses::CONFIG_FIELDLAYOUT_KEY, $licenses->handleDeletedFieldLayout(...));
    }
}
