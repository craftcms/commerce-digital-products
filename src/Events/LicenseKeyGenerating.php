<?php

namespace craft\digitalproducts\events;

use craft\digitalproducts\elements\License;
use yii\base\Event;

/**
 * Class GenerateLicenseKeyEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class GenerateKeyEvent extends Event
{
    /**
     * @var License The license information
     */
    public $license;

    /**
     * @var string License key to use
     */
    public $licenseKey;
}
