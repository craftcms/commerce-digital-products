<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Console\Commands;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Database\Migrator;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Cms\Support\File;
use CraftCms\Cms\Support\Str;
use CraftCms\Commerce\License\Elements\License as CommerceLicense;
use CraftCms\Commerce\License\Licenses as CommerceLicenses;
use CraftCms\Commerce\Plugin as Commerce;
use CraftCms\Commerce\Product\Fields\Variants as VariantsField;
use CraftCms\Commerce\Product\ProductType\Data\ProductType as CommerceProductType;
use CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite as CommerceProductTypeSite;
use CraftCms\Commerce\Product\ProductType\ProductTypes as CommerceProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Elements\License;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\FieldLayoutElements\ProductTitleField;
use CraftCms\DigitalProducts\Fields\Products as ProductsField;
use CraftCms\DigitalProducts\Licenses;
use CraftCms\DigitalProducts\ProductTypes;
use CraftCms\DigitalProducts\Settings;
use Illuminate\Console\Command;
use Override;

/**
 * Moves digital products into Commerce, which issues licenses from variants.
 *
 * Product types can only be created where admin changes are allowed, so this runs in development. It creates the
 * Commerce product types and other project config changes, then writes a content migration that moves the products
 * and licenses. Other environments get both by running `craft up`: project config is applied before content
 * migrations run.
 */
class MigrateToCommerceCommand extends Command
{
    use CraftCommand;

    /** Digital Products settings => Commerce settings. */
    public const array SETTINGS = [
        'autoAssignUserOnPurchase' => 'autoAssignLicensesOnPurchase',
        'autoAssignLicensesOnUserRegistration' => 'autoAssignLicensesOnUserRegistration',
        'licenseKeyCharacters' => 'licenseKeyCharacters',
        'licenseKeyLength' => 'licenseKeyLength',
        'requireLoggedInUser' => 'requireLoggedInUserForLicenses',
        'generateLicenseOnOrderPaid' => 'generateLicenseOnOrderPaid',
    ];

    public const string MIGRATION_NAME = 'migrate_digital_products_to_commerce';

    #[Override]
    protected $signature = 'digital-products:migrate-to-commerce
        {--force : Migrate without asking for confirmation.}
    ';

    #[Override]
    protected $description = 'Moves digital product types, digital products and licenses into Commerce product types, variants and licenses.';

    public function handle(ProjectConfig $projectConfig, ProductTypes $productTypes, Migrator $migrator): int
    {
        if (! class_exists(CommerceLicenses::class)) {
            $this->components->error('This version of Commerce doesn’t support licenses. Update Commerce, then run this command again.');

            return self::FAILURE;
        }

        if ($projectConfig->readOnly) {
            $this->components->error('Run this command in an environment where admin changes are allowed, such as your development environment. It creates product types, which can only be changed there, and writes a migration that moves your products and licenses when you run `craft up` in other environments.');

            return self::FAILURE;
        }

        $pluginTypes = $productTypes->getAllProductTypes();

        if ($pluginTypes === [] && ! app(CommerceLicenses::class)->hasPendingPluginData()) {
            $this->components->info('There are no digital products to migrate.');

            return self::SUCCESS;
        }

        foreach ($pluginTypes as $pluginType) {
            $existing = app(CommerceProductTypes::class)->getProductTypeByHandle($pluginType->handle);

            if ($existing && $existing->uid !== $pluginType->uid) {
                $this->components->error("A Commerce product type already uses the handle “{$pluginType->handle}”. Rename one of them and run this command again.");

                return self::FAILURE;
            }
        }

        if (! $this->option('force') && ! $this->confirm('Move all digital products and licenses into Commerce? Back up your database first.', true)) {
            $this->components->warn('Nothing was migrated.');

            return self::SUCCESS;
        }

        $this->components->task('Creating product types', function () use ($pluginTypes, $projectConfig) {
            foreach ($pluginTypes as $pluginType) {
                $projectConfig->set(
                    CommerceProductTypes::CONFIG_PRODUCTTYPES_KEY.'.'.$pluginType->uid,
                    $this->commerceProductType($pluginType)->getConfig(),
                );
            }
        });

        $this->components->task('Moving the license field layout, fields, permissions and settings', function () use ($projectConfig) {
            $this->migrateProjectConfig($projectConfig);
        });

        $migrationPath = database_path(sprintf('migrations/%s_%s.php', now('UTC')->format('Y_m_d_His'), self::MIGRATION_NAME));

        $this->components->task('Generating content migration', function () use ($migrationPath) {
            File::ensureDirectoryExists(dirname($migrationPath));
            File::copy(dirname(__DIR__, 3).'/stubs/migrate-to-commerce.php.stub', $migrationPath);
        });

        $this->components->twoColumnDetail('Running content migrations');
        $migrator->track('content')->run();

        $this->components->success(sprintf(<<<'EOD'
            Digital products were moved into Commerce. Commit <options=bold>`%s`</>
            and your project config changes, and run <options=bold>`craft up`</> in other environments.
            Update your templates as described in the Digital Products readme. Once every environment
            has run the migration, uninstall the Digital Products plugin.
            EOD,
            Str::after($migrationPath, base_path('/')),
        ));

        return self::SUCCESS;
    }

    /**
     * Builds a product type that allows licenses with the digital product type’s UID, so permissions and relation
     * field sources keep working. Its variant field layout keeps the digital product field layout’s UIDs, so
     * applying it takes over that layout and the custom field values stored under it.
     */
    private function commerceProductType(ProductType $pluginType): CommerceProductType
    {
        $productType = new CommerceProductType;
        $productType->uid = $pluginType->uid;
        $productType->name = $pluginType->name;
        $productType->handle = $pluginType->handle;
        $productType->skuFormat = $pluginType->skuFormat;
        $productType->hasLicenses = true;
        $productType->licenseDownloadFieldUid = $pluginType->downloadFieldUid;
        $productType->maxVariants = 1;
        $productType->hasVariantTitleField = false;
        $productType->variantTitleFormat = '{product.title}';
        $productType->descriptionFormat = '{product.title}';

        $productType->setSiteSettings(array_map(fn ($site) => new CommerceProductTypeSite([
            'siteId' => $site->siteId,
            'hasUrls' => $site->hasUrls,
            'uriFormat' => $site->uriFormat,
            'template' => $site->template,
        ]), $pluginType->getSiteSettings()));

        $pluginLayout = $pluginType->getFieldLayout();
        $config = $pluginLayout->getConfig() ?? [];

        // Variant titles come from their product
        foreach ($config['tabs'] ?? [] as $i => $tab) {
            $config['tabs'][$i]['elements'] = array_values(array_filter(
                $tab['elements'] ?? [],
                fn (array $element) => ($element['type'] ?? null) !== ProductTitleField::class,
            ));
        }

        $variantLayout = FieldLayout::createFromConfig([...$config, 'type' => Variant::class]);
        $variantLayout->uid = $pluginLayout->uid ?? (string) Str::uuid();
        $productType->setVariantFieldLayout($variantLayout);

        return $productType;
    }

    /**
     * Moves the license field layout, element sources, relation fields, user group permissions and settings.
     * The `digital-products` config is left alone: removing it would delete the digital products before the
     * content migration moves them. Uninstalling the plugin removes it.
     */
    private function migrateProjectConfig(ProjectConfig $projectConfig): void
    {
        if ($licenseLayouts = $projectConfig->get(Licenses::CONFIG_FIELDLAYOUT_KEY)) {
            $projectConfig->set(CommerceLicenses::FIELD_LAYOUT_CONFIG_KEY, $licenseLayouts);
        }

        $sourcesPath = ProjectConfig::PATH_ELEMENT_SOURCES;

        if (($sources = $projectConfig->get("$sourcesPath.".License::class)) !== null) {
            $projectConfig->set("$sourcesPath.".CommerceLicense::class, $sources);
            $projectConfig->remove("$sourcesPath.".License::class);
        }

        $projectConfig->remove("$sourcesPath.".Product::class);

        // Handlers only run for changes made at the paths they listen to
        foreach ($projectConfig->get('fields') ?? [] as $fieldUid => $field) {
            if (($field['type'] ?? null) === ProductsField::class) {
                $projectConfig->set("fields.$fieldUid", [...$field, 'type' => VariantsField::class]);
            }
        }

        foreach ($projectConfig->get('users.groups') ?? [] as $groupUid => $group) {
            $permissions = $group['permissions'] ?? [];
            $migrated = array_values(array_unique(array_merge([], ...array_map(
                fn (string $name) => self::commercePermissions($name) ?? [$name],
                $permissions,
            ))));

            if ($migrated !== $permissions) {
                $projectConfig->set("users.groups.$groupUid.permissions", $migrated);
            }
        }

        $settings = app(Settings::class);
        Plugins::savePluginSettings(app(Commerce::class), collect(self::SETTINGS)
            ->mapWithKeys(fn (string $new, string $old) => [$new => $settings->$old])
            ->all());
    }

    /**
     * Returns the Commerce permissions that replace a Digital Products permission, or null for other permissions.
     * Managing a digital product type becomes viewing, creating, saving and deleting products of the product type
     * with the same UID. The content migration has a copy of this for permissions granted to individual users.
     *
     * @return string[]|null
     */
    public static function commercePermissions(string $name): ?array
    {
        $name = Str::lower($name);

        if ($name === 'digitalproducts-managelicenses') {
            return ['commerce-managelicenses'];
        }

        if (str_starts_with($name, 'digitalproducts-manageproducttype:')) {
            $uid = Str::after($name, ':');

            return array_map(fn (string $action) => "commerce-{$action}producttype:$uid", ['view', 'create', 'save', 'delete']);
        }

        return str_starts_with($name, 'digitalproducts-') ? [] : null;
    }
}
