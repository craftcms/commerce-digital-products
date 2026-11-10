<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Http\Controllers;

use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Element\Drafts;
use CraftCms\Cms\Element\ElementHelper;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Site\Sites;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Users;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\ProductTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

/**
 * Creates a new digital product as an unpublished draft and sends the user to its edit screen.
 */
readonly class CreateProductController
{
    use RespondsWithFlash;

    public function __construct(
        private Request $request,
        private ProductTypes $productTypes,
        private Sites $sites,
    ) {}

    public function __invoke(string $productTypeHandle, Drafts $drafts, Users $users): Response
    {
        $productType = $this->productTypes->getProductTypeByHandle($productTypeHandle);
        abort_if($productType === null, 404, "Invalid product type handle: $productTypeHandle");

        $user = $this->request->craftUser();
        abort_if(! $user, 401);

        $siteIds = $this->sites->isMultiSite()
            ? array_values(array_intersect($productType->getSiteIds(), $this->sites->getEditableSiteIds()->all()))
            : [$this->sites->getPrimarySite()->id];
        abort_if($siteIds === [], 403, 'User not permitted to edit content in any sites supported by this product type');

        $siteId = $this->request->integer('siteId') ?: app(RequestedSite::class)->get()?->id;
        if (! in_array($siteId, $siteIds, true)) {
            $siteId = $siteIds[0];
        }

        $product = new Product;
        $product->siteId = $siteId;
        $product->typeId = $productType->id;
        $product->enabled = true;

        Gate::forUser($users->getUserById($user->getCraftUserId()))->authorize('save', $product);

        $product->title = $this->request->input('title');
        $product->slug = $this->request->input('slug') ?: ElementHelper::tempSlug();

        // Pause time so the post date matches the date created
        DateTimeHelper::pause();

        try {
            $product->postDate = now();
            $product->ruleset->useScenario(ElementRules::SCENARIO_ESSENTIALS);
            $success = $drafts->saveElementAsDraft($product, $user->getCraftUserId(), markAsSaved: false);
        } finally {
            DateTimeHelper::resume();
        }

        if (! $success) {
            return $this->asModelFailure($product, mb_ucfirst(t('Couldn’t create {type}.', [
                'type' => Product::lowerDisplayName(),
            ])), 'product');
        }

        $editUrl = (string) $product->getCpEditUrl();

        $response = $this->asModelSuccess($product, t('{type} created.', [
            'type' => Product::displayName(),
        ]), 'product', ['cpEditUrl' => $editUrl]);

        if (! $this->request->wantsJson()) {
            $response->headers->set('Location', Url::urlWithParams($editUrl, ['fresh' => 1]));
        }

        return $response;
    }
}
