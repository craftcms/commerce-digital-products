<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Listeners;

use CraftCms\Cms\GarbageCollection\Actions\DeletePartialElements;
use CraftCms\Cms\GarbageCollection\Events\RunningGarbageCollection;
use CraftCms\DigitalProducts\Database\Table;
use CraftCms\DigitalProducts\Elements\License;
use CraftCms\DigitalProducts\Elements\Product;

/**
 * Deletes product and license elements whose plugin rows are missing.
 */
class RunningGarbageCollectionListener
{
    public function handle(RunningGarbageCollection $event): void
    {
        foreach ([
            [Product::class, Table::PRODUCTS],
            [License::class, Table::LICENSES],
        ] as [$elementType, $table]) {
            app(DeletePartialElements::class, [
                'garbageCollection' => $event->garbageCollection,
                'elementType' => $elementType,
                'table' => $table,
            ])();
        }
    }
}
