<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\FieldLayoutElements;

use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\TitleField;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\DigitalProducts\Elements\Product;
use InvalidArgumentException;
use Override;

/**
 * The Title field in digital product field layouts.
 */
class ProductTitleField extends TitleField
{
    #[Override]
    protected function uiControl(FieldLayoutElementContext $context): ?Control
    {
        if ($context->element !== null && ! $context->element instanceof Product) {
            throw new InvalidArgumentException(sprintf('%s can only be used in digital product field layouts.', self::class));
        }

        return parent::uiControl($context);
    }
}
