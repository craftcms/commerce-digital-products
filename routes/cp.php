<?php

namespace craft\digitalproducts\plugin;

use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;

trait Routes
{
    /**
     * Control Panel routes.
     */
    public function _registerCpRoutes()
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['digital-products/producttypes/new'] = 'digital-products/product-types/edit';
                $event->rules['digital-products/producttypes/<productTypeId:\d+>'] = 'digital-products/product-types/edit';

                $event->rules['digital-products/products'] = 'digital-products/products/index';
                $event->rules['digital-products/products/<productTypeHandle:{handle}>'] = 'digital-products/products/index';
                $event->rules['digital-products/products/<productTypeHandle:{handle}>/new'] = 'digital-products/products/create';
                $event->rules['digital-products/products/<productTypeHandle:{handle}>/<elementId:\d+><slug:(?:-[^\/]*)?>'] = 'elements/edit';

                $event->rules['digital-products/licenses/new'] = 'digital-products/licenses/create';
                $event->rules['digital-products/licenses/<elementId:\d+>'] = 'elements/edit';

                $event->rules['digital-products/settings/licenses'] = 'digital-products/settings/edit-licenses';
            }
        );
    }
}
