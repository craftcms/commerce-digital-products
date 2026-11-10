<?php

namespace craft\digitalproducts\controllers;

use Craft;
use craft\digitalproducts\elements\Product;
use craft\digitalproducts\models\ProductType;
use craft\digitalproducts\models\ProductTypeSite;
use craft\digitalproducts\Plugin as DigitalProducts;
use craft\web\Controller as BaseController;
use craft\web\UrlManager;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class DigitalProducts_ProductsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 */
class ProductTypesController extends BaseController
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->requirePermission('digitalProducts-manageProductTypes');
        parent::init();
    }

    /**
     * Edit a product type.
     *
     * @param int|null $productTypeId the product type id
     * @param ProductType|null $productType the product type
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $productTypeId = null, ProductType $productType = null): Response
    {
        $variables = [
            'productTypeId' => $productTypeId,
            'productType' => $productType,
        ];

        $variables['brandNewProductType'] = false;

        if (empty($variables['productType'])) {
            if (!empty($variables['productTypeId'])) {
                $productTypeId = $variables['productTypeId'];
                $variables['productType'] = DigitalProducts::getInstance()->getProductTypes()->getProductTypeById($productTypeId);

                if (!$variables['productType']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['productType'] = new ProductType();
                $variables['brandNewProductType'] = true;
            }
        }

        $variables['title'] = $variables['productType']->id ? $variables['productType']->name : Craft::t('digital-products', 'Create a new digital product type');

        return $this->renderTemplate('digital-products/producttypes/_edit', $variables);
    }

    /**
     * Save a Product Type
     *
     * @return Response|null
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $productType = new ProductType();

        $request = Craft::$app->getRequest();

        $productType->id = $request->getBodyParam('productTypeId');
        $productType->name = $request->getBodyParam('name');
        $productType->handle = $request->getBodyParam('handle');
        $productType->skuFormat = $request->getBodyParam('skuFormat');

        // Site-specific settings
        $allSiteSettings = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $postedSettings = $request->getBodyParam('sites.' . $site->handle);

            $siteSettings = new ProductTypeSite();
            $siteSettings->siteId = $site->id;
            $siteSettings->hasUrls = !empty($postedSettings['uriFormat']);

            if ($siteSettings->hasUrls) {
                $siteSettings->uriFormat = $postedSettings['uriFormat'];
                $siteSettings->template = $postedSettings['template'];
            } else {
                $siteSettings->uriFormat = null;
                $siteSettings->template = null;
            }

            $allSiteSettings[$site->id] = $siteSettings;
        }

        $productType->setSiteSettings($allSiteSettings);

        // Set the product type field layout
        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Product::class;
        $productType->setFieldLayout($fieldLayout);

        // Save it
        if (DigitalProducts::getInstance()->getProductTypes()->saveProductType($productType)) {
            Craft::$app->getSession()->setNotice(Craft::t('digital-products', 'Product type saved.'));

            return $this->redirectToPostedUrl($productType);
        }

        Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save product type.'));

        // Send the productType back to the template
        /** @var UrlManager $urlManager */
        $urlManager = Craft::$app->getUrlManager();
        $urlManager->setRouteParams([
            'productType' => $productType,
        ]);

        return null;
    }

    /**
     * Delete a Product Type.
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $productTypeId = Craft::$app->getRequest()->getRequiredParam('id');
        DigitalProducts::getInstance()->getProductTypes()->deleteProductTypeById($productTypeId);

        return $this->asJson(['success' => true]);
    }
}
