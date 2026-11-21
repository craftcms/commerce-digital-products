<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Policies;

use CraftCms\Cms\Element\Policies\ElementPolicy;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\DigitalProducts\Elements\License;

/**
 * Licenses can be managed by users with the `digitalProducts-manageLicenses` permission.
 */
class LicensePolicy extends ElementPolicy
{
    public function view(CraftUser $user, License $license): bool
    {
        return $user->can('digitalProducts-manageLicenses');
    }

    public function save(CraftUser $user, License $license): bool
    {
        return $user->can('digitalProducts-manageLicenses');
    }

    public function delete(CraftUser $user, License $license): bool
    {
        return $user->can('digitalProducts-manageLicenses');
    }

    public function createDrafts(CraftUser $user, License $license): bool
    {
        return false;
    }
}
