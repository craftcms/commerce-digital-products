<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Http\Controllers;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\Queries\AssetQuery;
use CraftCms\DigitalProducts\Elements\License;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a licensed asset to whoever holds the license key.
 *
 * Reached at `actions/digital-products/download/download?licenseKey=…[&assetId=…]`. The route is anonymous on
 * purpose: the license key is the credential. If the product type has a download field, the asset must be in that
 * field on the licensed product, and `assetId` defaults to the field's first asset. Otherwise `assetId` is required
 * and any asset related to the product can be downloaded.
 */
readonly class DownloadController
{
    public function __invoke(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'licenseKey' => ['required', 'string'],
            'assetId' => ['nullable', 'integer'],
        ]);

        $license = License::find()->licenseKey($validated['licenseKey'])->one();
        $product = $license?->getProduct();

        abort_if($product === null, 404);

        $productType = $product->getType();
        $assetId = isset($validated['assetId']) ? (int) $validated['assetId'] : null;

        if ($productType->downloadFieldUid) {
            // A download field that's no longer in the field layout must not widen access to every related asset
            $downloadField = $productType->getDownloadField();
            abort_if($downloadField === null, 404);

            /** @var AssetQuery $assetQuery */
            $assetQuery = clone $product->getFieldValue($downloadField->handle);

            if ($assetId) {
                $assetQuery->id($assetId);
            }
        } else {
            abort_if($assetId === null, 400, 'Missing assetId.');
            $assetQuery = Asset::find()->id($assetId)->relatedTo($product);
        }

        $asset = $assetQuery->one();

        abort_if($asset === null, 404);

        return response()->streamDownload(function () use ($asset) {
            $stream = $asset->getStream();
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $asset->getFilename(), array_filter([
            'Content-Type' => $asset->getMimeType(),
            'Content-Length' => $asset->size,
        ]));
    }
}
