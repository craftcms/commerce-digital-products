<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Http\Controllers\Settings;

use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Ui\Controls\FieldLayoutDesigner;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\DigitalProducts\Elements\License;
use CraftCms\DigitalProducts\Licenses;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

/**
 * Edits the license field layout, stored at `digital-products.licenseFieldLayouts`.
 */
class LicenseFieldsController extends BaseSettingsController
{
    public function edit(): CpScreenResponse
    {
        $fieldLayout = Fields::getLayoutByType(License::class);

        $ui = Ui::make([
            Field::make(null, FieldLayoutDesigner::make('fieldLayout')
                ->elementType(License::class)
                ->withCardViewDesigner()),
        ]);

        return $this->cpScreenResponse()
            ->title(t('License Fields', category: 'digital-products'))
            ->crumbs($this->crumbs(t('License Fields', category: 'digital-products')))
            ->redirectUrl('digital-products/settings/licenses')
            ->ui($ui, new UiContext(
                values: [
                    'fieldLayout' => [
                        'id' => $fieldLayout->id,
                        'uid' => $fieldLayout->uid,
                        ...($fieldLayout->getConfig() ?? []),
                    ],
                ],
                mode: $this->readOnly ? ControlMode::ReadOnly : ControlMode::Editable,
            ), [
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'store']),
                ],
            ]);
    }

    public function store(): Response
    {
        abort_if($this->readOnly, 403, 'Administrative changes are disallowed in this environment.');

        $fieldLayout = Fields::assembleLayoutFromPost();
        $fieldLayout->type = License::class;
        $fieldLayout->reservedFieldHandles = [
            'licenseKey',
            'order',
            'owner',
            'ownerEmail',
            'ownerName',
            'product',
        ];

        if (! $fieldLayout->validate()) {
            return $this->asFailure(t('Couldn’t save license fields.', category: 'digital-products'));
        }

        $current = ProjectConfig::get(Licenses::CONFIG_FIELDLAYOUT_KEY);
        $uid = $current ? array_key_first($current) : (string) Str::uuid();

        ProjectConfig::set(Licenses::CONFIG_FIELDLAYOUT_KEY, [$uid => $fieldLayout->getConfig()]);

        return $this->asSuccess(t('License fields saved.', category: 'digital-products'));
    }
}
