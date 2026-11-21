<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Http\Controllers;

use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\DigitalProducts\Elements\License;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

/**
 * Creates a new license and sends the user to its edit screen.
 *
 * Licenses don't support drafts, so the new license is saved with the essentials scenario and a placeholder key,
 * which is replaced by a generated one once the license has a product.
 */
readonly class CreateLicenseController
{
    use RespondsWithFlash;

    public function __invoke(Request $request): Response
    {
        $site = app(RequestedSite::class)->get();
        abort_if($site === null, 403, 'User not authorized to edit content in any sites.');

        $license = new License;
        $license->siteId = $site->id;

        Gate::authorize('save', $license);

        $license->ruleset->useScenario(ElementRules::SCENARIO_ESSENTIALS);

        if (! Elements::saveElement($license)) {
            return $this->asModelFailure($license, t('Couldn’t create license.', category: 'digital-products'), 'license');
        }

        $editUrl = (string) $license->getCpEditUrl();

        $response = $this->asModelSuccess($license, t('License created.', category: 'digital-products'), 'license', [
            'cpEditUrl' => $editUrl,
        ]);

        if (! $request->wantsJson()) {
            $response->headers->set('Location', Url::urlWithParams($editUrl, ['fresh' => 1]));
        }

        return $response;
    }
}
