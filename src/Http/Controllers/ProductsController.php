<?php

namespace craft\digitalproducts\controllers;

use Craft;
use craft\base\Element;
use craft\digitalproducts\elements\License;
use craft\digitalproducts\elements\Product;
use craft\digitalproducts\Plugin as DigitalProducts;
use craft\digitalproducts\web\assets\cp\Bundle;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use craft\web\Controller as BaseController;
use Throwable;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class DigitalProducts_ProductsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 */
class ProductsController extends BaseController
{
    /**
     * @inheritdoc
     */
    protected int|bool|array $allowAnonymous = ['actionViewSharedProduct'];

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->requirePermission('digitalProducts-manageProducts');
        parent::init();
    }

    /**
     * Index of digital products
     */
    public function actionIndex(): Response
    {
        $editableProductTypes = DigitalProducts::getInstance()->getProductTypes()->getEditableProductTypes();
        $this->getView()->registerAssetBundle(Bundle::class);
        return $this->renderTemplate('digital-products/products/index', compact('editableProductTypes'));
    }

    /**
     * Create a new Product and redirect to its edit page.
     *
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    public function actionCreate(string $productTypeHandle): ?Response
    {
        $productType = DigitalProducts::getInstance()->getProductTypes()->getProductTypeByHandle($productTypeHandle);

        if (!$productType) {
            throw new BadRequestHttpException("Invalid product type handle: $productTypeHandle");
        }

        $this->requirePermission('digitalProducts-manageProductType:' . $productType->uid);

        $site = Cp::requestedSite();
        if (!$site) {
            throw new ForbiddenHttpException('User not authorized to edit content in any sites.');
        }

        $product = Craft::createObject(Product::class);
        $product->typeId = $productType->id;
        $product->siteId = $site->id;

        $user = static::currentUser();
        if (!Craft::$app->getElements()->canSave($product, $user)) {
            throw new ForbiddenHttpException('User not authorized to create a product.');
        }

        $product->setScenario(Element::SCENARIO_ESSENTIALS);
        $success = Craft::$app->getDrafts()->saveElementAsDraft($product, $user->id, markAsSaved: false);

        if (!$success) {
            return $this->asModelFailure($product, Craft::t('digital-products', 'Couldn\'t create product.'), 'product');
        }

        $editUrl = $product->getCpEditUrl();

        $response = $this->asModelSuccess($product, Craft::t('digital-products', 'Product created.'), 'product', [
            'cpEditUrl' => $this->request->getIsCpRequest() ? $editUrl : null,
        ]);

        if (!$this->request->getAcceptsJson()) {
            $response->redirect(UrlHelper::urlWithParams($editUrl, ['fresh' => 1]));
        }

        return $response;
    }

    /**
     * Delete a product.
     *
     * @throws BadRequestHttpException
     * @throws Exception if no product found
     * @throws ForbiddenHttpException
     * @throws Throwable
     */
    public function actionDeleteProduct(): ?Response
    {
        $this->requirePostRequest();

        $productId = Craft::$app->getRequest()->getRequiredParam('productId');
        /** @var Product|null $product */
        $product = Product::find()
            ->id($productId)
            ->status(null)
            ->one();

        if (!$product) {
            throw new Exception(Craft::t('digital-products', 'No product exists with the ID "{id}".', ['id' => $productId]));
        }

        $productType = $product->getType();
        $this->requirePermission('digitalProducts-manageProductType:' . $productType->uid);

        $licensesExist = License::find()->product($product)->exists();

        if ($licensesExist || !Craft::$app->getElements()->deleteElement($product)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson(['success' => false]);
            }

            $error = $licensesExist
                ? Craft::t('digital-products', 'Couldn\'t delete product with existing licenses.')
                : Craft::t('digital-products', 'Couldn\'t delete product.');

            Craft::$app->getSession()->setError($error);

            return null;
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson(['success' => true]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('digital-products', 'Product deleted.'));

        return $this->redirectToPostedUrl($product);
    }

    /**
     * Previews a product.
     *
     * @throws BadRequestHttpException
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws Throwable
     */
    public function actionPreviewProduct(): Response
    {
        $this->requirePostRequest();

        $productId = Craft::$app->getRequest()->getBodyParam('productId');
        $siteId = Craft::$app->getRequest()->getBodyParam('siteId');

        /** @var Product|null $product */
        $product = Craft::$app->getElements()->getElementById($productId, Product::class, $siteId);

        if (!$product) {
            throw new BadRequestHttpException('Invalid product ID: ' . $productId);
        }

        $this->requirePermission('digitalProducts-manageProductType:' . $product->getType()->uid);

        return $this->_showProduct($product);
    }

    /**
     * Redirects the client to a URL for viewing a disabled product on the front end.
     *
     * @throws BadRequestHttpException
     * @throws ServerErrorHttpException
     */
    public function actionShareProduct(int $productId, int $siteId = null): Response
    {
        /** @var Product|null $product */
        $product = Craft::$app->getElements()->getElementById($productId, Product::class, $siteId);

        if (!$product) {
            throw new BadRequestHttpException('Invalid product ID: ' . $productId);
        }

        if (!DigitalProducts::getInstance()->getProductTypes()->isProductTypeTemplateValid($product->getType(), $product->siteId)) {
            throw new ServerErrorHttpException('Product type has an invalid template path');
        }

        $this->requirePermission('digitalProducts-manageProductType:' . $product->getType()->uid);

        $token = Craft::$app->getTokens()->createToken([
            'action' => 'digital-products/products/viewSharedProduct',
            'params' => ['productId' => $productId, 'site' => $product->getSite()],
        ]);

        $url = UrlHelper::urlWithToken($product->getUrl(), $token);

        return $this->redirect($url);
    }

    /**
     * Shows a product/draft/version based on a token.
     *
     * @throws Exception if product not found
     */
    public function actionViewSharedProduct(int $productId, ?int $siteId = null): Response
    {
        $this->requireToken();

        /** @var Product|null $product */
        $product = Craft::$app->getElements()->getElementById($productId, Product::class, $siteId);

        if (!$product) {
            throw new Exception('Product not found.');
        }

        return $this->_showProduct($product);
    }

    /**
     * Displays a product.
     *
     * @throws Exception if product type is not found
     */
    private function _showProduct(Product $product): Response
    {
        $productType = $product->getType();

        if (!$productType) {
            throw new ServerErrorHttpException('Product type not found.');
        }

        $siteSettings = $productType->getSiteSettings();

        if (!isset($siteSettings[$product->siteId]) || !$siteSettings[$product->siteId]->hasUrls) {
            throw new ServerErrorHttpException('The product ' . $product->id . ' doesn\'t have a URL for the site ' . $product->siteId . '.');
        }

        $site = Craft::$app->getSites()->getSiteById($product->siteId);

        if (!$site) {
            throw new ServerErrorHttpException('Invalid site ID: ' . $product->siteId);
        }

        Craft::$app->language = $site->language;

        if ($product->id) {
            Craft::$app->getElements()->setPlaceholderElement($product);
        }

        $this->getView()->getTwig()->disableStrictVariables();

        return $this->renderTemplate($siteSettings[$product->siteId]->template, [
            'product' => $product,
        ]);
    }
}
