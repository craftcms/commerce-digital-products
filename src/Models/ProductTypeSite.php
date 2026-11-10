<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use CraftCms\DigitalProducts\Database\Table;

/**
 * Row in `digitalproducts_producttypes_sites`.
 *
 * @property int $id
 * @property int $productTypeId
 * @property int $siteId
 * @property string|null $uriFormat
 * @property string|null $template
 * @property bool $hasUrls
 */
class ProductTypeSite extends BaseModel
{
    use HasUid;

    #[\Override]
    protected $table = Table::PRODUCT_TYPES_SITES;

    #[\Override]
    protected $casts = [
        'productTypeId' => 'integer',
        'siteId' => 'integer',
        'hasUrls' => 'boolean',
    ];
}
