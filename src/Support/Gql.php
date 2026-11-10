<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\digitalproducts\helpers;

use craft\helpers\Gql as GqlHelper;

/**
 * Class Commerce Gql
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.4
 */
class Gql extends GqlHelper
{
    /**
     * Return true if active schema can query products.
     *
     * @return bool
     */
    public static function canQueryProducts(): bool
    {
        $allowedEntities = self::extractAllowedEntitiesFromSchema();
        return isset($allowedEntities['digitalProductTypes']);
    }
}
