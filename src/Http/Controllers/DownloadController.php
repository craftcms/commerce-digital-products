<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Http\Controllers;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\DigitalProducts\Elements\License;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams an asset related to a licensed product to whoever holds the license key.
 *
 * Reached at `actions/digital-products/download/download?licenseKey=…&assetId=…`. The route
 * is anonymous on purpose: the license key is the credential.
 */
readonly class DownloadController
{
    public function __invoke(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'licenseKey' => ['required', 'string'],
            'assetId' => ['required', 'integer'],
        ]);

        $license = License::find()->licenseKey($validated['licenseKey'])->one();
        $product = $license?->getProduct();

        abort_if($product === null, 404);

        $asset = Asset::find()
            ->id((int) $validated['assetId'])
            ->relatedTo($product)
            ->one();

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
