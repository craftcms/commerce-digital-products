<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use CraftCms\DigitalProducts\Database\Table;

/**
 * Row in `digitalproducts_producttypes`.
 *
 * @property int $id
 * @property int|null $fieldLayoutId
 * @property string $name
 * @property string $handle
 * @property string|null $skuFormat
 * @property string $uid
 */
class ProductType extends BaseModel
{
    use HasUid;

    #[\Override]
    protected $table = Table::PRODUCT_TYPES;

    #[\Override]
    protected $casts = [
        'fieldLayoutId' => 'integer',
    ];
}
