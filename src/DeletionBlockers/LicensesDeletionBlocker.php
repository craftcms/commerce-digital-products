<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\DeletionBlockers;

use CraftCms\Cms\Element\DeletionBlockers\BaseDeletionBlocker;
use CraftCms\Cms\Element\ElementCollection;
use CraftCms\DigitalProducts\Elements\License;

use function CraftCms\Cms\t;

/**
 * Tells the control panel that digital products with licenses can't be deleted.
 */
class LicensesDeletionBlocker extends BaseDeletionBlocker
{
    private readonly int $licenseCount;

    public function __construct(ElementCollection $elements, bool $hardDelete, array $config = [])
    {
        parent::__construct($elements, $hardDelete, $config);

        $this->licenseCount = License::find()
            ->productId($this->elements->ids()->all())
            ->status(null)
            ->count();
    }

    public function isActive(): bool
    {
        return $this->licenseCount > 0;
    }

    public function getSummary(): string
    {
        return t('{count, number} {count, plural, =1{license has} other{licenses have}} been issued for the selected {numProducts, plural, =1{product} other{products}}. Products with licenses can’t be deleted.', [
            'count' => $this->licenseCount,
            'numProducts' => $this->elements->count(),
        ], category: 'digital-products');
    }

    public function getDetails(): ?string
    {
        return $this->elementChipsHtml(License::find()
            ->productId($this->elements->ids()->all())
            ->status(null));
    }

    public function getActions(): array
    {
        return [];
    }
}
