<?php

namespace craft\digitalproducts\controllers;

use Craft;
use craft\digitalproducts\elements\License;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\web\Controller as BaseController;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Download controller.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class DownloadController extends BaseController
{
    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    /**
     * Streams a licensed asset to the browser without exposing the underlying file URL.
     *
     * Requires a `licenseKey` request param. If the product type has a download field, the asset must be in that
     * field on the licensed product, and `assetId` is optional, defaulting to the field's first asset. Otherwise
     * `assetId` is required, and the asset can be any asset related to the licensed product.
     *
     * @throws NotFoundHttpException if the license key is invalid or the asset isn't downloadable with it.
     */
    public function actionDownload(): Response
    {
        $request = Craft::$app->getRequest();
        $licenseKey = $request->getRequiredParam('licenseKey');

        $license = License::find()->licenseKey($licenseKey)->one();
        $product = $license ? $license->getProduct() : null;

        if (!$product) {
            throw new NotFoundHttpException();
        }

        $productType = $product->getType();

        if ($productType?->downloadFieldUid) {
            // A download field that's no longer in the field layout shouldn't widen access to every related asset
            $downloadField = $productType->getDownloadField();

            if (!$downloadField) {
                throw new NotFoundHttpException();
            }

            /** @var AssetQuery $assetQuery */
            $assetQuery = clone $product->getFieldValue($downloadField->handle);
            $assetId = $request->getParam('assetId');

            if ($assetId) {
                $assetQuery->id((int)$assetId);
            }
        } else {
            $assetQuery = Asset::find()
                ->id((int)$request->getRequiredParam('assetId'))
                ->relatedTo($product);
        }

        /** @var Asset|null $asset */
        $asset = $assetQuery->one();

        if (!$asset) {
            throw new NotFoundHttpException();
        }

        return $this->response->sendStreamAsFile(
            $asset->getStream(),
            $asset->filename,
            ['mimeType' => $asset->getMimeType()]
        );
    }
}
