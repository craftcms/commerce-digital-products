<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Composer\InstalledVersions;
use CraftCms\Cms\Plugin\Plugins;
use CraftCms\Commerce\Plugin as CommercePlugin;
use Illuminate\Support\ServiceProvider;

/**
 * Points Craft at Commerce's native plugin class.
 *
 * Commerce 6's composer.json doesn't name a plugin class, so Composer's plugin installer records the legacy
 * `craft\commerce\Plugin` (which needs the Yii 2 adapter). The workbench hands Craft the same plugin list with
 * `CraftCms\Commerce\Plugin` instead, without rewriting `vendor/craftcms/plugins.php`.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->when(Plugins::class)
            ->needs('$composerPlugins')
            ->give(function () {
                $plugins = require $this->app->basePath('vendor/craftcms/plugins.php');

                $plugins['craftcms/commerce'] = [
                    ...$plugins['craftcms/commerce'],
                    'class' => CommercePlugin::class,
                    'basePath' => realpath((string) InstalledVersions::getInstallPath('craftcms/commerce')).'/src',
                ];

                return $plugins;
            });
    }
}
