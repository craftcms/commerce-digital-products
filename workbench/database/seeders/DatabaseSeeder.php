<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Plugin\Plugins as PluginsService;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Data\ProductTypeSite;
use CraftCms\DigitalProducts\Elements\License;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\ProductTypes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Context;
use RuntimeException;

/** Installs Craft, Commerce and Digital Products, then demo content. Safe to rerun. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! Cms::isInstalled()) {
            $status = $this->command->call('craft:install', [
                '--username' => 'admin',
                '--password' => 'password123',
                '--email' => 'admin@example.test',
                '--siteName' => config('app.name'),
                '--siteUrl' => '$APP_URL',
                '--language' => 'en-US',
                '--no-interaction' => true,
            ]);

            if ($status !== 0) {
                throw new RuntimeException('Craft installation failed.');
            }

            Context::forgetHidden('craft.info');
            Context::forgetHidden('craft.isInstalled');
        }

        $installed = false;

        foreach (['commerce', 'digital-products'] as $handle) {
            if (! Plugins::isPluginInstalled($handle)) {
                Plugins::installPlugin($handle);
                $installed = true;
            }
        }

        if ($installed) {
            // Installing doesn't boot a plugin in the same process, so load them again to register their
            // element types and project config handlers before seeding content.
            app()->forgetInstance(PluginsService::class);
            Plugins::clearResolvedInstance();
            app(PluginsService::class)->loadPlugins();
        }

        $this->seedDemoContent();
    }

    private function seedDemoContent(): void
    {
        $productTypes = app(ProductTypes::class);

        if ($productTypes->getProductTypeByHandle('software')) {
            return;
        }

        $siteId = Sites::getPrimarySite()->id;
        $productType = new ProductType(['name' => 'Software', 'handle' => 'software', 'skuFormat' => 'SW-{slug}']);
        $productType->setSiteSettings([$siteId => new ProductTypeSite([
            'siteId' => $siteId,
            'hasUrls' => true,
            'uriFormat' => 'software/{slug}',
            'template' => 'software/_product',
        ])]);
        $productTypes->saveProductType($productType)
            or throw new RuntimeException(json_encode($productType->errors()->getMessages()));

        foreach (['Pro Plugin' => 99, 'Starter Theme' => 39] as $title => $price) {
            $product = new Product;
            $product->typeId = $productType->id;
            $product->siteId = $siteId;
            $product->title = $title;
            $product->setBasePrice($price);
            Elements::saveElement($product) or throw new RuntimeException(json_encode($product->errors()->getMessages()));

            $license = new License;
            $license->productId = $product->id;
            $license->ownerEmail = 'customer@example.test';
            $license->ownerName = 'Casey Customer';
            $license->userId = Users::ensureUserByEmail('customer@example.test')->id;
            Elements::saveElement($license) or throw new RuntimeException(json_encode($license->errors()->getMessages()));
        }
    }
}
