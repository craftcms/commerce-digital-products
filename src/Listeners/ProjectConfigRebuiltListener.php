<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Listeners;

use CraftCms\Cms\ProjectConfig\Events\ProjectConfigRebuilt;
use CraftCms\DigitalProducts\Support\ProjectConfigData;

/**
 * Rebuilds the `digital-products` project config from the database.
 */
class ProjectConfigRebuiltListener
{
    public function handle(ProjectConfigRebuilt $event): void
    {
        $event->config['digital-products'] = ProjectConfigData::rebuildProjectConfig();
    }
}
