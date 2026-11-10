<?php

use CraftCms\Cms\Asset\Data\Volume;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Asset\Folders;
use CraftCms\Cms\Asset\Volumes;
use CraftCms\Cms\Field\Assets as AssetsField;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\FieldLayout\LayoutElements\CustomField;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\DigitalProducts\Elements\License;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\FieldLayoutElements\ProductTitleField;
use CraftCms\DigitalProducts\ProductTypes;
use Illuminate\Support\Facades\Storage;

function createAsset(string $filename, string $contents): Asset
{
    $volume = app(Volumes::class)->getVolumeByHandle('downloads');

    if (! $volume) {
        $volume = new Volume(['name' => 'Downloads', 'handle' => 'downloads', 'fsHandle' => 'downloads', 'hasUrls' => false]);
        app(Volumes::class)->saveVolume($volume) or throw new RuntimeException(json_encode($volume->errors()->getMessages()));
    }

    $path = tempnam(sys_get_temp_dir(), 'dp');
    file_put_contents($path, $contents);

    $asset = new Asset([
        'volumeId' => $volume->id,
        'folderId' => app(Folders::class)->getRootFolderByVolumeId($volume->id)->id,
        'filename' => $filename,
        'tempFilePath' => $path,
        'title' => $filename,
    ]);
    Elements::saveElement($asset) or throw new RuntimeException(json_encode($asset->errors()->getMessages()));

    return $asset;
}

beforeEach(function () {
    // Volumes validate their disk against the filesystems config; the fake replaces it with a temporary disk.
    config()->set('filesystems.disks.downloads', ['driver' => 'local', 'root' => storage_path('framework/testing/disks/downloads')]);
    Storage::fake('downloads');

    $field = new AssetsField(['name' => 'Files', 'handle' => 'files']);
    Fields::saveField($field) or throw new RuntimeException(json_encode($field->errors()->getMessages()));

    $productType = createProductType();
    $productType->setFieldLayout(FieldLayout::createFromConfig([
        'type' => Product::class,
        'tabs' => [[
            'name' => 'Content',
            'elements' => [
                ['type' => ProductTitleField::class],
                ['type' => CustomField::class, 'fieldUid' => $field->uid],
            ],
        ]],
    ]));
    app(ProductTypes::class)->saveProductType($productType) or throw new RuntimeException(json_encode($productType->errors()->getMessages()));

    $this->file = createAsset('manual.txt', 'Digital product contents');
    $this->otherFile = createAsset('other.txt', 'Not for sale');

    $product = Product::find()->id(createProduct($productType)->id)->one();
    $product->setFieldValue('files', [$this->file->id]);
    Elements::saveElement($product) or throw new RuntimeException(json_encode($product->errors()->getMessages()));

    $this->license = new License;
    $this->license->productId = $product->id;
    $this->license->ownerEmail = 'customer@example.test';
    Elements::saveElement($this->license) or throw new RuntimeException(json_encode($this->license->errors()->getMessages()));
});

it('streams an asset related to the licensed product', function () {
    $response = $this->get('/actions/digital-products/download/download?'.http_build_query([
        'licenseKey' => $this->license->licenseKey,
        'assetId' => $this->file->id,
    ]));

    $response->assertOk()
        ->assertDownload('manual.txt');

    expect($response->streamedContent())->toBe('Digital product contents');
});

it('accepts the parameters in a POST body', function () {
    $this->post('/actions/digital-products/download/download', [
        'licenseKey' => $this->license->licenseKey,
        'assetId' => $this->file->id,
    ])->assertOk()->assertDownload('manual.txt');
});

it('returns 404 for an unknown license key', function () {
    $this->get('/actions/digital-products/download/download?'.http_build_query([
        'licenseKey' => 'nope',
        'assetId' => $this->file->id,
    ]))->assertNotFound();
});

it('returns 404 for an asset that is not related to the product', function () {
    $this->get('/actions/digital-products/download/download?'.http_build_query([
        'licenseKey' => $this->license->licenseKey,
        'assetId' => $this->otherFile->id,
    ]))->assertNotFound();
});
