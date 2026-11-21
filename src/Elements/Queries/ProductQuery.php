<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Elements\Queries;

use Closure;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Cms\Support\Arr;
use CraftCms\Commerce\Purchasable\Queries\PurchasableQuery;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Database\Table;
use CraftCms\DigitalProducts\Elements\Product;
use CraftCms\DigitalProducts\ProductTypes;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Override;
use Tpetry\QueryExpressions\Language\Alias;

use function CraftCms\Cms\currentUser;

/**
 * Queries digital products.
 *
 * @extends PurchasableQuery<Product>
 *
 * @method Product|null one($db = null)
 * @method Product[] all($db = null)
 */
class ProductQuery extends PurchasableQuery
{
    #[Override]
    protected array $defaultOrderBy = [
        Table::PRODUCTS.'.postDate' => SORT_DESC,
        'elements.id' => SORT_DESC,
    ];

    /** Whether to only return products the current user can manage. */
    public ?bool $editable = null;

    public mixed $typeId = null;

    public mixed $postDate = null;

    public mixed $expiryDate = null;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        // Default status
        if (! isset($config['status'])) {
            $config['status'] = [Product::STATUS_LIVE];
        }

        parent::__construct(Product::class, $config);

        $this->query->join(new Alias(Table::PRODUCTS, Table::PRODUCTS), Table::PRODUCTS.'.id', '=', 'elements.id');

        $this->query->addSelect([
            Table::PRODUCTS.'.typeId',
            Table::PRODUCTS.'.postDate',
            Table::PRODUCTS.'.expiryDate',
        ]);

        $this->beforeQuery(static function (self $query) {
            $query->normalizeTypeId();

            // See if `type` was set to an invalid handle
            if ($query->typeId === []) {
                throw new QueryAbortedException;
            }

            if ($query->typeId) {
                $query->whereIn(Table::PRODUCTS.'.typeId', $query->typeId);
            }

            if (isset($query->postDate)) {
                $query->whereDateParam(Table::PRODUCTS.'.postDate', $query->postDate);
            }

            if (isset($query->expiryDate)) {
                $query->whereDateParam(Table::PRODUCTS.'.expiryDate', $query->expiryDate);
            }

            if ($query->editable) {
                if (! currentUser()) {
                    throw new QueryAbortedException;
                }

                $query->whereIn(Table::PRODUCTS.'.typeId', app(ProductTypes::class)->getEditableProductTypeIds());
            }
        });
    }

    /**
     * Narrows the query results to products the current user can manage.
     */
    public function editable(?bool $value = true): static
    {
        $this->editable = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the product types, by handle or {@see ProductType}.
     */
    public function type(mixed $value): static
    {
        if (is_string($value) && ($productType = app(ProductTypes::class)->getProductTypeByHandle($value))) {
            $value = $productType;
        }

        if ($value instanceof ProductType) {
            $this->typeId = [$value->id];
        } elseif ($value !== null) {
            $this->typeId = DB::table(Table::PRODUCT_TYPES)
                ->whereParam('handle', $value)
                ->pluck('id')
                ->all();
        } else {
            $this->typeId = null;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the product type IDs.
     */
    public function typeId(mixed $value): static
    {
        $this->typeId = $value;

        return $this;
    }

    public function postDate(mixed $value): static
    {
        $this->postDate = $value;

        return $this;
    }

    public function expiryDate(mixed $value): static
    {
        $this->expiryDate = $value;

        return $this;
    }

    /**
     * Narrows the query results to products posted before the given date.
     */
    public function before(DateTimeInterface|string $value): static
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format(DateTimeInterface::W3C);
        }

        $this->postDate = Arr::wrap($this->postDate);
        $this->postDate[] = '<'.$value;

        return $this;
    }

    /**
     * Narrows the query results to products posted on or after the given date.
     */
    public function after(DateTimeInterface|string $value): static
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format(DateTimeInterface::W3C);
        }

        $this->postDate = Arr::wrap($this->postDate);
        $this->postDate[] = '>='.$value;

        return $this;
    }

    #[Override]
    protected function statusCondition(string $status): Closure
    {
        // Treat “now” as the end of the current minute to keep queries cacheable
        $currentTime = now()->endOfMinute()->setTimezone('UTC');

        return match ($status) {
            Product::STATUS_LIVE => fn (Builder $query) => $query
                ->whereBool('elements.enabled', true)
                ->whereBool('elements_sites.enabled', true)
                ->where(Table::PRODUCTS.'.postDate', '<=', $currentTime)
                ->where(fn (Builder $query) => $query
                    ->whereNull(Table::PRODUCTS.'.expiryDate')
                    ->orWhere(Table::PRODUCTS.'.expiryDate', '>', $currentTime)),
            Product::STATUS_PENDING => fn (Builder $query) => $query
                ->whereBool('elements.enabled', true)
                ->whereBool('elements_sites.enabled', true)
                ->where(Table::PRODUCTS.'.postDate', '>', $currentTime),
            Product::STATUS_EXPIRED => fn (Builder $query) => $query
                ->whereBool('elements.enabled', true)
                ->whereBool('elements_sites.enabled', true)
                ->whereNotNull(Table::PRODUCTS.'.expiryDate')
                ->where(Table::PRODUCTS.'.expiryDate', '<=', $currentTime),
            default => parent::statusCondition($status),
        };
    }

    #[Override]
    protected function cacheTags(): array
    {
        return array_map(fn ($typeId) => "digitalProductType:$typeId", Arr::wrap($this->typeId));
    }

    private function normalizeTypeId(): void
    {
        if (empty($this->typeId)) {
            $this->typeId = is_array($this->typeId) ? [] : null;
        } elseif (is_numeric($this->typeId)) {
            $this->typeId = [(int) $this->typeId];
        } elseif (! is_array($this->typeId) || ! Arr::isNumeric($this->typeId)) {
            $this->typeId = DB::table(Table::PRODUCT_TYPES)
                ->whereParam('id', $this->typeId)
                ->pluck('id')
                ->all();
        }
    }
}
