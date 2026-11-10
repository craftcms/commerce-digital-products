<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Tests;

use Composer\InstalledVersions;
use CraftCms\Cms\Plugin\Plugins;
use CraftCms\Cms\Plugin\Testing\PluginTestCase;
use CraftCms\Cms\Support\File;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Plugin as CommercePlugin;
use Override;

use function Orchestra\Testbench\package_path;

/**
 * Installs Craft, then Commerce, then Digital Products, in a fresh in-memory database.
 */
abstract class TestCase extends PluginTestCase
{
    #[Override]
    public function composerInstallPlugin(): void
    {
        $plugin = Json::decode(File::get(package_path('composer.json')));
        $commercePath = (string) InstalledVersions::getInstallPath('craftcms/commerce');
        $commerce = Json::decode(File::get($commercePath.'/composer.json'));

        $this->app->when(Plugins::class)
            ->needs('$composerPlugins')
            ->give(fn () => [
                'craftcms/commerce' => [
                    ...$commerce['extra'],
                    // Commerce's composer.json doesn't name its Laravel plugin class.
                    'class' => CommercePlugin::class,
                    'basePath' => realpath($commercePath).'/src',
                    'version' => InstalledVersions::getPrettyVersion('craftcms/commerce'),
                ],
                $plugin['name'] => [
                    ...$plugin['extra'],
                    'basePath' => package_path('src'),
                    'version' => InstalledVersions::getRootPackage()['pretty_version'],
                ],
            ]);

        $this->app->forgetInstance(Plugins::class);
    }

    #[Override]
    public function installAndEnablePlugin(): void
    {
        foreach (['commerce', 'digital-products'] as $handle) {
            $plugins = $this->app->get(Plugins::class);
            $plugins->installPlugin($handle);
            $plugins->enablePlugin($handle);
            $this->app->forgetInstance(Plugins::class);
        }
    }
}
