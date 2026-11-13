<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use CraftCms\DigitalProducts\Database\Table;

/**
 * Row in `digitalproducts_products`. `id` is the product element's ID.
 *
 * The SKU and price live in Commerce's purchasable tables.
 *
 * @property int $id
 * @property int $typeId
 * @property int $taxCategoryId
 * @property string|null $postDate
 * @property string|null $expiryDate
 * @property bool $promotable
 * @property string $dateCreated
 * @property string $dateUpdated
 */
class Product extends BaseModel
{
    use HasUid;

    #[\Override]
    protected $table = Table::PRODUCTS;

    public $timestamps = false;

    /** `id` is a foreign key to `elements.id`, not an auto-increment column. */
    public $incrementing = false;

    #[\Override]
    protected $casts = [
        'typeId' => 'integer',
        'taxCategoryId' => 'integer',
        'promotable' => 'boolean',
    ];
}
