<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Policies;

use CraftCms\Cms\Element\Policies\ElementPolicy;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\ProductTypes;

/**
 * Products can be viewed, saved and deleted by users with the
 * `digitalProducts-manageProductType:{uid}` permission for their product type.
 */
class ProductPolicy extends ElementPolicy
{
    public function view(CraftUser $user, Product $product): bool
    {
        return $this->canManage($user, $product);
    }

    public function save(CraftUser $user, Product $product): bool
    {
        return $this->canManage($user, $product);
    }

    public function delete(CraftUser $user, Product $product): bool
    {
        return $this->canManage($user, $product);
    }

    public function deleteForSite(CraftUser $user, Product $product): bool
    {
        return $this->canManage($user, $product);
    }

    public function duplicate(CraftUser $user, Product $product): bool
    {
        return $this->canManage($user, $product);
    }

    public function duplicateAsDraft(CraftUser $user, Product $product): bool
    {
        return $this->canManage($user, $product);
    }

    public function copy(CraftUser $user, Product $product): bool
    {
        return $this->canManage($user, $product);
    }

    public function createDrafts(CraftUser $user, Product $product): bool
    {
        return $this->canManage($user, $product);
    }

    private function canManage(CraftUser $user, Product $product): bool
    {
        $productType = $product->typeId ? app(ProductTypes::class)->getProductTypeById($product->typeId) : null;

        return $productType !== null && $user->can("digitalProducts-manageProductType:$productType->uid");
    }
}
