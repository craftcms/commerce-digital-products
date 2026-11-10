<?php

namespace craft\digitalproducts\controllers;

use Craft;
use craft\base\Element;
use craft\digitalproducts\elements\License;

use craft\digitalproducts\elements\Product;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use craft\web\Controller as BaseController;
use craft\web\UrlManager;
use Throwable;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Class DigitalProducts_LicensesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 */
class LicensesController extends BaseController
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->requirePermission('digitalProducts-manageLicenses');

        parent::init();
    }

    /**
     * Create a new License and redirect to its edit page.
     */
    public function actionCreate(): ?Response
    {
        $site = Cp::requestedSite();
        if (!$site) {
            throw new ForbiddenHttpException('User not authorized to edit content in any sites.');
        }

        $license = Craft::createObject(License::class);
        $license->siteId = $site->id;

        $user = static::currentUser();
        if (!Craft::$app->getElements()->canSave($license, $user)) {
            throw new ForbiddenHttpException('User not authorized to create a license.');
        }

        $license->setScenario(Element::SCENARIO_ESSENTIALS);
        if (!Craft::$app->getElements()->saveElement($license)) {
            return $this->asModelFailure($license, Craft::t('digital-products', 'Couldn\'t create license.'), 'license');
        }

        $editUrl = $license->getCpEditUrl();

        $response = $this->asModelSuccess($license, Craft::t('digital-products', 'License created.'), 'license', [
            'cpEditUrl' => $this->request->getIsCpRequest() ? $editUrl : null,
        ]);

        if (!$this->request->getAcceptsJson()) {
            $response->redirect(UrlHelper::urlWithParams($editUrl, ['fresh' => 1]));
        }

        return $response;
    }

    /**
     * Create or edit a License
     *
     * @param int|null $licenseId the license id
     * @param License|null $license the license
     * @return Response
     */
    public function actionEdit(int $licenseId = null, License $license = null): Response
    {
        if ($license === null) {
            if ($licenseId === null) {
                $license = new License();
            } else {
                /** @var License|null $license */
                $license = Craft::$app->getElements()->getElementById($licenseId, License::class);

                if (!$license) {
                    $license = new License();
                }
            }
        }

        $variables['title'] = $license->id ? $license->__toString() : Craft::t('digital-products', 'Create a new License');
        $variables['license'] = $license;
        $variables['userElementType'] = User::class;
        $variables['productElementType'] = Product::class;

        return $this->renderTemplate('digital-products/licenses/_edit', $variables);
    }

    /**
     * Save a License.
     *
     * @return Response|null
     * @throws Exception if a non existing license id provided
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws MissingComponentException
     * @throws BadRequestHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        /** @var int|string $licenseId */
        $licenseId = $request->getBodyParam('licenseId');

        if ($licenseId) {
            /** @var License|null $license */
            $license = Craft::$app->getElements()->getElementById($licenseId, License::class);

            if (!$license) {
                throw new Exception(Craft::t('digital-products','No license with the ID “{id}”', ['id' => $licenseId]));
            }
        } else {
            $license = new License();
        }

        $productIds = $request->getBodyParam('product');
        $userIds = $request->getBodyParam('owner');

        if (is_array($productIds) && !empty($productIds)) {
            $license->productId = reset($productIds);
        }

        if (is_array($userIds) && !empty($userIds)) {
            $license->userId = reset($userIds);
        }

        $license->id = $request->getBodyParam('licenseId');
        $license->enabled = (bool)$request->getBodyParam('enabled');
        $license->ownerName = $request->getBodyParam('ownerName');
        $license->ownerEmail = $request->getBodyParam('ownerEmail');

        // Save it
        if (!Craft::$app->getElements()->saveElement($license)) {
            Craft::$app->getSession()->setError(Craft::t('digital-products', 'Couldn’t save license.'));
            /** @var UrlManager $urlManager */
            $urlManager = Craft::$app->getUrlManager();
            $urlManager->setRouteParams(['license' => $license]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('digital-products', 'License saved.'));
        return $this->redirectToPostedUrl($license);
    }

    /**
     * Delete a License.
     *
     * @return Response|null
     * @throws Exception if a non existing license id provided
     * @throws Throwable
     * @throws MissingComponentException
     * @throws BadRequestHttpException
     */
    public function actionDelete(): ?Response
    {
        $this->requirePostRequest();

        /** @var int|string $licenseId */
        $licenseId = Craft::$app->getRequest()->getRequiredBodyParam('licenseId');
        /** @var License|null $license */
        $license = Craft::$app->getElements()->getElementById($licenseId, License::class);

        if (!$license) {
            throw new Exception(Craft::t('digital-products','No license with the ID “{id}”', ['id' => $licenseId]));
        }

        if (Craft::$app->getElements()->deleteElement($license)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson(['success' => true]);
            }

            Craft::$app->getSession()->setNotice(Craft::t('digital-products', 'License deleted.'));
            return $this->redirectToPostedUrl($license);
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson(['success' => false]);
        }

        Craft::$app->getSession()->setError(Craft::t('digital-products', 'Couldn’t delete license.'));

        /** @var UrlManager $urlManager */
        $urlManager = Craft::$app->getUrlManager();
        $urlManager->setRouteParams(['license' => $license]);

        return null;
    }
}
