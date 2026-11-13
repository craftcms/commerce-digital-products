<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Http\Controllers\Settings;

use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Ui\UiResolver;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

/**
 * The Digital Products settings screens: General, License Fields and Product Types.
 */
abstract class BaseSettingsController
{
    use RespondsWithFlash;

    protected bool $readOnly;

    public function __construct(
        protected GeneralConfig $generalConfig,
        protected UiResolver $uiResolver,
    ) {
        $this->readOnly = ! $generalConfig->allowAdminChanges;
    }

    /**
     * @return NavItem[]
     */
    protected function subnav(): array
    {
        $path = request()->craftPath();

        return [
            new NavItem()
                ->label(t('General', category: 'app'))
                ->url(cp_url('digital-products/settings'))
                ->selected($path === 'digital-products/settings'),
            new NavItem()
                ->label(t('License Fields', category: 'digital-products'))
                ->url(cp_url('digital-products/settings/licenses'))
                ->selected($path === 'digital-products/settings/licenses'),
            new NavItem()
                ->label(t('Product Types', category: 'digital-products'))
                ->url(cp_url('digital-products/producttypes'))
                ->selected(str_starts_with($path, 'digital-products/producttypes')),
        ];
    }

    /**
     * “Digital Products / Settings[ / ...$trail]”, where the last crumb never links.
     *
     * @return list<array{label: string, href: ?string}>
     */
    protected function crumbs(string ...$trail): array
    {
        $crumbs = [
            ['label' => t('Digital Products', category: 'digital-products'), 'href' => cp_url('digital-products')],
            ['label' => t('Settings', category: 'app'), 'href' => cp_url('digital-products/settings')],
            ...array_map(fn (string $label) => ['label' => $label, 'href' => null], $trail),
        ];

        $crumbs[array_key_last($crumbs)]['href'] = null;

        return $crumbs;
    }

    protected function cpScreenResponse(bool $subnav = true): CpScreenResponse
    {
        return new CpScreenResponse()
            ->selectedSubnavItem('settings')
            ->subnav($subnav ? $this->subnav() : null);
    }
}
