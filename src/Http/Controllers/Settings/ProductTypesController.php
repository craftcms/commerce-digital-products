<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Http\Controllers\Settings;

use CraftCms\Cms\Cp\SelectOptions;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Ui\Controls\FieldLayoutDesigner;
use CraftCms\Cms\Ui\Controls\Handle;
use CraftCms\Cms\Ui\Controls\Table as TableControl;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Heading;
use CraftCms\Cms\Ui\Nodes\HiddenField;
use CraftCms\Cms\Ui\Nodes\Separator;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Data\ProductTypeSite;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\ProductTypes;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

/**
 * Lists, creates, edits and deletes digital product types.
 */
class ProductTypesController extends BaseSettingsController
{
    public function index(ProductTypes $productTypes): CpScreenResponse
    {
        $rows = array_map(fn (ProductType $productType) => [
            'id' => $productType->id,
            'name' => [
                'label' => t($productType->name, category: 'site'),
                'url' => $productType->getCpEditUrl(),
            ],
            'handle' => $productType->handle,
        ], $productTypes->getAllProductTypes());

        $ui = Ui::make([
            Table::make('product-types')
                ->columns([
                    ['key' => 'name', 'label' => t('Name'), 'sortable' => true],
                    ['key' => 'handle', 'label' => t('Handle')],
                ])
                ->rows(array_values($rows))
                ->emptyMessage(t('No product types exist yet.', category: 'digital-products'))
                ->createAction(
                    $this->readOnly ? null : t('New product type', category: 'digital-products'),
                    $this->readOnly ? null : cp_url('digital-products/producttypes/new'),
                )
                ->createActionInPageHeader()
                ->when(! $this->readOnly, fn (Table $table) => $table->deletable(action([self::class, 'destroy']))),
        ]);

        return $this->cpScreenResponse()
            ->title(t('Product Types', category: 'digital-products'))
            ->crumbs($this->crumbs(t('Product Types', category: 'digital-products')))
            ->ui($ui);
    }

    public function create(): CpScreenResponse
    {
        return $this->editScreen(new ProductType, true);
    }

    public function edit(ProductTypes $productTypes, int $productTypeId): CpScreenResponse
    {
        $productType = $productTypes->getProductTypeById($productTypeId);
        abort_if($productType === null, 404);

        return $this->editScreen($productType, false);
    }

    private function editScreen(ProductType $productType, bool $brandNew): CpScreenResponse
    {
        $title = $brandNew ? t('Create a new product type', category: 'digital-products') : (string) $productType->name;
        $values = $this->initialValues($productType, $brandNew);

        $ui = $this->uiResolver->resolve(
            $this->buildUi($brandNew),
            new UiContext(
                values: $values,
                mode: $this->readOnly ? ControlMode::ReadOnly : ControlMode::Editable,
            ),
        );

        return $this->cpScreenResponse(subnav: false)
            ->title($title)
            ->crumbs($this->crumbs(t('Product Types', category: 'digital-products'), $title))
            ->redirectUrl('digital-products/producttypes')
            ->ui($ui, props: [
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'store']),
                ],
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function initialValues(ProductType $productType, bool $brandNew): array
    {
        $siteSettings = $productType->getSiteSettings();
        $siteRows = [];

        foreach (Sites::getAllSites() as $site) {
            $settings = $siteSettings[$site->id] ?? null;

            $siteRows[$site->handle] = [
                'name' => $site->getName(),
                'enabled' => $brandNew || $settings !== null,
                'uriFormat' => $settings->uriFormat ?? '',
                'template' => $settings->template ?? '',
            ];
        }

        $fieldLayout = $productType->getFieldLayout();

        return [
            'productTypeId' => $productType->id,
            'name' => $productType->name,
            'handle' => $productType->handle,
            'skuFormat' => $productType->skuFormat ?? '',
            'sites' => $siteRows,
            'fieldLayout' => [
                'id' => $fieldLayout->id,
                'uid' => $fieldLayout->uid,
                ...($fieldLayout->getConfig() ?? []),
            ],
        ];
    }

    private function buildUi(bool $brandNew): Ui
    {
        $handle = Handle::make('handle');

        if ($brandNew) {
            $handle->source('name');
        }

        $siteColumns = ['name' => ['heading' => t('Site'), 'type' => 'heading']];

        if (Sites::isMultiSite()) {
            $siteColumns['enabled'] = ['heading' => t('Enabled'), 'type' => 'lightswitch'];
        }

        $siteColumns['uriFormat'] = [
            'heading' => t('Product URI Format', category: 'digital-products'),
            'type' => 'singleline',
            'info' => t('What product URIs should look like for the site. Leave blank if products don’t have URLs.', category: 'digital-products'),
        ];
        $siteColumns['template'] = [
            'heading' => t('Template'),
            'type' => 'template',
            'options' => SelectOptions::getTemplateSuggestions(),
            'info' => t('Which template should be loaded when a product’s URL is requested.', category: 'digital-products'),
        ];

        return Ui::make()
            ->addTab(t('Settings'), [
                HiddenField::make('productTypeId'),
                Field::make(t('Name'), Text::make('name')->autofocus())
                    ->instructions(t('What this product type will be called in the control panel.', category: 'digital-products'))
                    ->required(),
                Field::make(t('Handle'), $handle)
                    ->instructions(t('How you’ll refer to this product type in the templates.', category: 'digital-products'))
                    ->required(),
                Field::make(t('Automatic SKU Format', category: 'digital-products'), Text::make('skuFormat')->monospace())
                    ->instructions(t('What the unique auto-generated SKUs should look like, when a SKU field is submitted without a value. You can include tags that output properties, such as {ex1} or {ex2}', [
                        'ex1' => Html::code('{slug}'),
                        'ex2' => Html::code('{myCustomField}'),
                    ], 'digital-products')),
                Separator::make('site-settings-separator'),
                Heading::make('site-settings-heading', t('Site Settings'))
                    ->description(t('Choose which sites this product type should be available in, and configure the site-specific settings.', category: 'digital-products')),
                Field::make(control: TableControl::make('sites')->keyed()->columns($siteColumns)),
            ])
            ->addTab(t('Product Fields', category: 'digital-products'), [
                Field::make(null, FieldLayoutDesigner::make('fieldLayout')
                    ->elementType(Product::class)
                    ->withCardViewDesigner()),
            ]);
    }

    public function store(Request $request, ProductTypes $productTypes): Response
    {
        abort_if($this->readOnly, 403, 'Administrative changes are disallowed in this environment.');

        $productTypeId = $request->integer('productTypeId') ?: null;

        if ($productTypeId) {
            $productType = $productTypes->getProductTypeById($productTypeId);
            abort_if($productType === null, 400, "Invalid product type ID: $productTypeId");
        } else {
            $productType = new ProductType;
        }

        $productType->name = $request->input('name');
        $productType->handle = $request->input('handle');
        $productType->skuFormat = $request->input('skuFormat') ?: null;

        $allSiteSettings = [];

        foreach (Sites::getAllSites() as $site) {
            $postedSettings = $request->input('sites.'.$site->handle) ?? [];

            if (Sites::isMultiSite() && empty($postedSettings['enabled'])) {
                continue;
            }

            $siteSettings = new ProductTypeSite;
            $siteSettings->siteId = $site->id;
            $siteSettings->hasUrls = ! empty($postedSettings['uriFormat']);
            $siteSettings->uriFormat = $siteSettings->hasUrls ? $postedSettings['uriFormat'] : null;
            $siteSettings->template = $siteSettings->hasUrls ? ($postedSettings['template'] ?? null) : null;

            $allSiteSettings[$site->id] = $siteSettings;
        }

        $productType->setSiteSettings($allSiteSettings);

        $fieldLayout = Fields::assembleLayoutFromPost();
        $fieldLayout->type = Product::class;
        $productType->setFieldLayout($fieldLayout);

        if (! $productTypes->saveProductType($productType)) {
            return $this->asModelFailure($productType, t('Couldn’t save product type.', category: 'digital-products'), 'productType');
        }

        return $this->asSuccess(t('Product type saved.', category: 'digital-products'));
    }

    public function destroy(Request $request, ProductTypes $productTypes): Response
    {
        abort_if($this->readOnly, 403, 'Administrative changes are disallowed in this environment.');

        $productTypeId = $request->integer('id');
        abort_if(! $productTypeId, 400, 'Missing product type ID');

        $productTypes->deleteProductTypeById($productTypeId);

        return $this->asSuccess();
    }
}
