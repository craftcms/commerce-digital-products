<?php

namespace craft\digitalproducts\controllers;

use Craft;
use craft\digitalproducts\elements\License;
use craft\elements\Asset;
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
     * Requires `licenseKey` and `assetId` request params. The asset must be related
     * to the product associated with the license.
     *
     * @throws NotFoundHttpException if the license key is invalid or the asset is not
     *   related to the licensed product.
     */
    public function actionDownload(): Response
    {
        $request = Craft::$app->getRequest();
        $licenseKey = $request->getRequiredParam('licenseKey');
        $assetId = (int)$request->getRequiredParam('assetId');

        $license = License::find()->licenseKey($licenseKey)->one();

        if (!$license) {
            throw new NotFoundHttpException();
        }

        /** @var Asset|null $asset */
        $asset = Asset::find()
            ->id($assetId)
            ->relatedTo($license->getProduct())
            ->one();

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
