<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Http\Controllers\Settings;

use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Plugin\Plugins;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\DigitalProducts\Plugin;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

/**
 * The plugin's general settings, stored in `plugins.digital-products.settings`.
 */
class GeneralSettingsController extends BaseSettingsController
{
    public function edit(Plugin $plugin): CpScreenResponse
    {
        $settings = $plugin->getSettings();

        $context = new UiContext(
            namespace: 'settings',
            values: ['settings' => $settings->validationData()],
            errors: $settings->errors()->getMessages(),
            mode: $this->readOnly ? ControlMode::ReadOnly : ControlMode::Editable,
        );

        return $this->cpScreenResponse()
            ->title(t('Settings', category: 'app'))
            ->crumbs($this->crumbs(t('General', category: 'app')))
            ->redirectUrl('digital-products/settings')
            ->ui($this->uiResolver->resolve($plugin->settingsUi($context), $context), props: [
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'store']),
                ],
            ]);
    }

    public function store(Request $request, Plugins $plugins, Plugin $plugin): Response
    {
        abort_if($this->readOnly, 403, 'Administrative changes are disallowed in this environment.');

        if (! $plugins->savePluginSettings($plugin, (array) $request->input('settings', []))) {
            return $this->asModelFailure($plugin->getSettings(), t('Couldn’t save settings.', category: 'app'), 'settings');
        }

        return $this->asSuccess(t('Settings saved.', category: 'app'));
    }
}
