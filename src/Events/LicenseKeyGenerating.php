<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Events;

use CraftCms\DigitalProducts\Elements\License;

/**
 * Dispatched before a license key is generated for a new license. Replaces `License::EVENT_GENERATE_LICENSE_KEY`.
 *
 * Set `$licenseKey` to supply the key yourself; otherwise one is generated
 * from the `licenseKeyCharacters` and `licenseKeyLength` settings.
 */
class LicenseKeyGenerating
{
    public function __construct(
        public License $license,
        public ?string $licenseKey = null,
    ) {}
}
