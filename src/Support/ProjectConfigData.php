<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\digitalproducts\helpers;

use Craft;
use craft\db\Query;
use craft\digitalproducts\elements\License;

/**
 * Class ProjectConfigData
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.2
 */
class ProjectConfigData
{
    // Project config rebuild methods
    // -------------------------------------------------------------------------

    /**
     * Return a rebuilt project config array
     *
     * @return array
     */
    public static function rebuildProjectConfig(): array
    {
        $output = [];
        $output['productTypes'] = self::_getProductTypeData();

        $licenseFieldLayout = self::_getLicenseFieldLayoutData();
        if ($licenseFieldLayout !== null) {
            $output['licenseFieldLayouts'] = $licenseFieldLayout;
        }

        return $output;
    }

    /**
     * Return license field layout data for project config.
     *
     * @return array|null
     */
    private static function _getLicenseFieldLayoutData(): ?array
    {
        $layout = Craft::$app->getFields()->getLayoutByType(License::class);
        if ($layout->id) {
            return [$layout->uid => $layout->getConfig()];
        }
        return null;
    }

    /**
     * Return product type data config array.
     *
     * @return array
     */
    private static function _getProductTypeData(): array
    {
        $productTypeRows = (new Query())
            ->select([
                'fieldLayoutId',
                'name',
                'handle',
                'skuFormat',
                'uid',
            ])
            ->from(['{{%digitalproducts_producttypes}} productTypes'])
            ->all();

        $typeData = [];

        foreach ($productTypeRows as $productTypeRow) {
            $rowUid = $productTypeRow['uid'];

            if (!empty($productTypeRow['fieldLayoutId'])) {
                $layout = Craft::$app->getFields()->getLayoutById($productTypeRow['fieldLayoutId']);

                if ($layout) {
                    $productTypeRow['fieldLayouts'] = [$layout->uid => $layout->getConfig()];
                }
            }

            unset($productTypeRow['uid'], $productTypeRow['fieldLayoutId']);

            $productTypeRow['siteSettings'] = [];
            $typeData[$rowUid] = $productTypeRow;
        }

        $productTypeSiteRows = (new Query())
            ->select([
                'producttypes_sites.hasUrls',
                'producttypes_sites.uriFormat',
                'producttypes_sites.template',
                'sites.uid AS siteUid',
                'producttypes.uid AS typeUid',
            ])
            ->from(['{{%digitalproducts_producttypes_sites}} producttypes_sites'])
            ->innerJoin('{{%sites}} sites', '[[sites.id]] = [[producttypes_sites.siteId]]')
            ->innerJoin('{{%digitalproducts_producttypes}} producttypes', '[[producttypes.id]] = [[producttypes_sites.productTypeId]]')
            ->all();

        foreach ($productTypeSiteRows as $productTypeSiteRow) {
            $typeUid = $productTypeSiteRow['typeUid'];
            $siteUid = $productTypeSiteRow['siteUid'];
            unset($productTypeSiteRow['siteUid'], $productTypeSiteRow['typeUid']);

            $productTypeSiteRow['hasUrls'] = (bool)$productTypeSiteRow['hasUrls'];

            $typeData[$typeUid]['siteSettings'][$siteUid] = $productTypeSiteRow;
        }

        return $typeData;
    }
}
