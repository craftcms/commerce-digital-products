<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Support;

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\DigitalProducts\Database\Table;
use CraftCms\DigitalProducts\Elements\License;
use Illuminate\Support\Facades\DB;

/**
 * Builds the `digital-products` project config from the database, for project config rebuilds.
 */
class ProjectConfigData
{
    /**
     * @return array<string, mixed>
     */
    public static function rebuildProjectConfig(): array
    {
        $output = ['productTypes' => self::productTypeData()];

        $licenseLayout = Fields::getLayoutByType(License::class, false);

        if ($licenseLayout?->id) {
            $output['licenseFieldLayouts'] = [$licenseLayout->uid => $licenseLayout->getConfig()];
        }

        return $output;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function productTypeData(): array
    {
        $data = [];

        foreach (DB::table(Table::PRODUCT_TYPES)->get() as $row) {
            $config = [
                'name' => $row->name,
                'handle' => $row->handle,
                'skuFormat' => $row->skuFormat,
                'siteSettings' => [],
            ];

            if ($row->fieldLayoutId && ($layout = Fields::getLayoutById((int) $row->fieldLayoutId))) {
                $config['fieldLayouts'] = [$layout->uid => $layout->getConfig()];
            }

            $data[$row->uid] = $config;
        }

        $siteRows = DB::table(Table::PRODUCT_TYPES_SITES, 'producttypes_sites')
            ->join(CraftTable::SITES.' as sites', 'sites.id', '=', 'producttypes_sites.siteId')
            ->join(Table::PRODUCT_TYPES.' as producttypes', 'producttypes.id', '=', 'producttypes_sites.productTypeId')
            ->select([
                'producttypes_sites.hasUrls',
                'producttypes_sites.uriFormat',
                'producttypes_sites.template',
                'sites.uid as siteUid',
                'producttypes.uid as typeUid',
            ])
            ->get();

        foreach ($siteRows as $row) {
            $data[$row->typeUid]['siteSettings'][$row->siteUid] = [
                'hasUrls' => (bool) $row->hasUrls,
                'uriFormat' => $row->uriFormat,
                'template' => $row->template,
            ];
        }

        return $data;
    }
}
