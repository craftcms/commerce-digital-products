<?php

namespace craft\commerce\digitalProducts;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\commerce\digitalProducts\fields\Products;
use craft\commerce\digitalProducts\plugin\Routes;
use craft\commerce\digitalProducts\plugin\Services;
use craft\commerce\elements\Order;
use craft\commerce\services\Payments;
use craft\elements\User;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Fields;
use craft\services\UserPermissions;
use craft\services\Users as UsersService;
use yii\base\Event;

/**
 * Digital Products Plugin for Craft Commerce.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 */
class Plugin extends BasePlugin
{
    // Traits
    // =========================================================================

    use Services;
    use Routes;

    // Constants
    // =========================================================================

    /**
     * @event \yii\base\Event The event that is triggered after the plugin has been initialized
     */
    const EVENT_AFTER_INIT = 'afterInit';

    // Public Methods
    // =========================================================================

    /**
     * Initialize the plugin.
     */
    public function init()
    {
        parent::init();

        $this->_setPluginComponents();
        $this->_registerCpRoutes();
        $this->_registerFieldTypes();
        $this->_registerEventHandlers();
        $this->_registerCpRoutes();
        $this->_registerPermissions();

        // Fire an 'afterInit' event
        $this->trigger(Plugin::EVENT_AFTER_INIT);
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): array
    {
        $iconPath = $this->getBasePath().DIRECTORY_SEPARATOR.'icon-mask.svg';

        if (is_file($iconPath)) {
            $iconSvg = file_get_contents($iconPath);
        } else {
            $iconSvg = false;
        }

        $navItems = [
            'label' => Craft::t('commerce-digitalproducts', 'Digital Products'),
            'url' => $this->id,
            'iconSvg' => $iconSvg
        ];

        if (Craft::$app->getUser()->checkPermission('digitalProducts-manageProducts')) {
            $navItems['subnav']['products'] = [
                'label' => Craft::t('commerce-digitalproducts', 'Products'),
                'url' => 'digitalproducts/products'
            ];
        }

        if (Craft::$app->getUser()->checkPermission('digitalProducts-manageProducts')) {
            $navItems['subnav']['productTypes'] = [
                'label' => Craft::t('commerce-digitalproducts', 'Product Types'),
                'url' => 'digitalproducts/producttypes'
            ];
        }

        if (Craft::$app->getUser()->checkPermission('digitalProducts-manageLicenses')) {
            $navItems['subnav']['licenses'] = [
                'label' => Craft::t('commerce-digitalproducts', 'Licenses'),
                'url' => 'digitalproducts/licenses'
            ];
        }

        return $navItems;
    }

    /**
     * Register Digital Product permissions
     */
    private function _registerPermissions()
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            $productTypes = [];//$this->getProductTypes()->getAllProductTypes();

            $productTypePermissions = [];

            foreach ($productTypes as $id => $productType) {
                $suffix = ':'.$id;
                $productTypePermissions['digitalProducts-manageProductType'.$suffix] = ['label' => Craft::t('commerce-digitalproducts', 'Manage “{type}” products', ['type' => $productType->name])];
            }

            $event->permissions[] = [
                'digitalProducts-manageProductTypes' => ['label' => Craft::t('commerce-digitalproducts', 'Manage product types')],
                'digitalProducts-manageProducts' => ['label' => Craft::t('commerce-digitalproducts', 'Manage products'), 'nested' => $productTypePermissions],
                'digitalProducts-manageLicenses' => ['label' => Craft::t('commerce-digitalproducts', 'Manage licenses')],
            ];
        });
    }

    /**
     * Get Settings URL
     */
    public function getSettingsUrl()
    {
        return false;
    }

    // Private Methods
    // =========================================================================

    /**
     * Register the event handlers.
     */
    private function _registerEventHandlers()
    {
        Event::on(UsersService::class, UsersService::EVENT_AFTER_ACTIVATE_USER, [$this->getLicenses(), 'handleUserActivation']);
        Event::on(User::class, User::EVENT_AFTER_DELETE, [$this->getLicenses(), 'handleUserDeletion']);
        Event::on(Order::class, Order::EVENT_AFTER_COMPLETE_ORDER, [$this->getLicenses(), 'handleCompletedOrder']);
    }

    /**
     * Register Commerce’s fields
     */
    private function _registerFieldTypes()
    {
        Event::on(Fields::className(), Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Products::class;
        });
    }

}
