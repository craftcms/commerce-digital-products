<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use CraftCms\DigitalProducts\Database\Table;

/**
 * Row in `digitalproducts_licenses`. `id` is the license element's ID.
 *
 * @property int $id
 * @property int|null $productId
 * @property int|null $orderId
 * @property string $licenseKey
 * @property string|null $ownerName
 * @property string|null $ownerEmail
 * @property int|null $userId
 * @property string $dateCreated
 * @property string $dateUpdated
 */
class License extends BaseModel
{
    use HasUid;

    #[\Override]
    protected $table = Table::LICENSES;

    public $timestamps = false;

    /** `id` is a foreign key to `elements.id`, not an auto-increment column. */
    public $incrementing = false;

    #[\Override]
    protected $casts = [
        'productId' => 'integer',
        'orderId' => 'integer',
        'userId' => 'integer',
    ];
}
