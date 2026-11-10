<?php

namespace craft\digitalproducts\controllers;

use Craft;
use craft\digitalproducts\elements\License;
use craft\digitalproducts\models\Settings as SettingsModel;
use craft\digitalproducts\Plugin as DigitalProducts;
use craft\web\Controller as BaseController;
use yii\web\Response;

/**
 * Class Settings Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class SettingsController extends BaseController
{
    /**
     * @return Response
     */
    public function actionEditLicenses(): Response
    {
        $this->requireAdmin();
        return $this->renderTemplate('digital-products/settings/licenses');
    }

    /**
     * @return Response
     */
    public function actionSaveLicenseFieldLayout(): Response
    {
        $this->requireAdmin();
        $this->requirePostRequest();

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = License::class;

        if (!Craft::$app->getFields()->saveLayout($fieldLayout)) {
            Craft::$app->getSession()->setError(Craft::t('digital-products', 'Couldn\'t save license fields.'));
            return $this->renderTemplate('digital-products/settings/licenses');
        }

        // Save to project config
        $projectConfig = Craft::$app->getProjectConfig();
        $projectConfig->set('digital-products.licenseFieldLayouts', [
            $fieldLayout->uid => $fieldLayout->getConfig(),
        ], 'Save the license field layout');

        Craft::$app->getSession()->setNotice(Craft::t('digital-products', 'License fields saved.'));
        return $this->redirectToPostedUrl();
    }

    /**
     * @return Response
     */
    public function actionSaveSettings(): Response
    {
        $this->requirePostRequest();
        $postData = Craft::$app->getRequest()->getParam('settings');
        $settings = new SettingsModel($postData);

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('digital-products', 'Couldn’t save settings.'));

            return $this->renderTemplate('digital-products/settings', ['settings' => $settings]);
        }

        Craft::$app->getPlugins()->savePluginSettings(DigitalProducts::getInstance(), $settings->toArray());

        Craft::$app->getSession()->setNotice(Craft::t('digital-products', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
