<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Elements\Queries;

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\User\Elements\User;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Database\Table;
use CraftCms\DigitalProducts\Elements\License;
use CraftCms\DigitalProducts\Elements\Product;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Override;
use Tpetry\QueryExpressions\Language\Alias;

/**
 * Queries licenses.
 *
 * @extends ElementQuery<License>
 *
 * @method License|null one($db = null)
 * @method License[] all($db = null)
 */
class LicenseQuery extends ElementQuery
{
    #[Override]
    protected string $table = Table::LICENSES;

    #[Override]
    protected array $defaultOrderBy = [
        'elements.dateCreated' => SORT_DESC,
        'elements.id' => SORT_DESC,
    ];

    /** Matches the owner email or the owning user's email. */
    public ?string $email = null;

    public mixed $ownerEmail = null;

    public mixed $userEmail = null;

    public mixed $ownerId = null;

    public mixed $productId = null;

    public mixed $typeId = null;

    public mixed $orderId = null;

    public mixed $licenseKey = null;

    public mixed $licenseDate = null;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct(License::class, $config);

        $this->query->leftJoin(new Alias(Table::PRODUCTS, Table::PRODUCTS), Table::PRODUCTS.'.id', '=', Table::LICENSES.'.productId');
        $this->query->leftJoin(new Alias(CraftTable::USERS, 'license_users'), 'license_users.id', '=', Table::LICENSES.'.userId');

        $this->query->addSelect([
            Table::LICENSES.'.productId',
            Table::LICENSES.'.orderId',
            Table::LICENSES.'.licenseKey',
            Table::LICENSES.'.ownerName',
            Table::LICENSES.'.ownerEmail',
            Table::LICENSES.'.userId',
        ]);

        $this->beforeQuery(static function (self $query) {
            if ($query->typeId === []) {
                throw new QueryAbortedException;
            }

            if ($query->email) {
                $email = $query->email;
                $query->where(fn (Builder $q) => $q
                    ->where(Table::LICENSES.'.ownerEmail', $email)
                    ->orWhere('license_users.email', $email));
            }

            if ($query->ownerEmail) {
                $query->whereParam(Table::LICENSES.'.ownerEmail', $query->ownerEmail);
            }

            if ($query->userEmail) {
                $query->whereParam('license_users.email', $query->userEmail);
            }

            if ($query->ownerId) {
                $query->whereParam(Table::LICENSES.'.userId', $query->ownerId);
            }

            if ($query->productId) {
                $query->whereParam(Table::LICENSES.'.productId', $query->productId);
            }

            if ($query->typeId) {
                $query->whereParam(Table::PRODUCTS.'.typeId', $query->typeId);
            }

            if ($query->orderId) {
                $query->whereParam(Table::LICENSES.'.orderId', $query->orderId);
            }

            if ($query->licenseKey) {
                $query->whereParam(Table::LICENSES.'.licenseKey', $query->licenseKey);
            }

            if ($query->licenseDate) {
                $query->whereDateParam('elements.dateCreated', $query->licenseDate);
            }
        });
    }

    public function email(?string $value): static
    {
        $this->email = $value;

        return $this;
    }

    public function ownerEmail(mixed $value): static
    {
        $this->ownerEmail = $value;

        return $this;
    }

    public function userEmail(mixed $value): static
    {
        $this->userEmail = $value;

        return $this;
    }

    /**
     * Narrows the query results to licenses owned by a user, by {@see User} or username.
     */
    public function owner(User|string|null $value): static
    {
        if ($value instanceof User) {
            $this->ownerId = $value->id;
        } elseif ($value !== null) {
            $this->ownerId = DB::table(CraftTable::USERS)->whereParam('username', $value)->pluck('id')->all() ?: [0];
        } else {
            $this->ownerId = null;
        }

        return $this;
    }

    public function ownerId(mixed $value): static
    {
        $this->ownerId = $value;

        return $this;
    }

    /**
     * Narrows the query results to licenses for a product, by {@see Product} or SKU.
     */
    public function product(Product|array|string|null $value): static
    {
        if ($value instanceof Product) {
            $this->productId = $value->id;
        } elseif ($value !== null) {
            $this->productId = Product::find()->sku($value)->status(null)->ids() ?: [0];
        } else {
            $this->productId = null;
        }

        return $this;
    }

    public function productId(mixed $value): static
    {
        $this->productId = $value;

        return $this;
    }

    /**
     * Narrows the query results to licenses for products of a type, by {@see ProductType} or handle.
     */
    public function type(mixed $value): static
    {
        if ($value instanceof ProductType) {
            $this->typeId = $value->id;
        } elseif ($value !== null) {
            $this->typeId = DB::table(Table::PRODUCT_TYPES)->whereParam('handle', $value)->pluck('id')->all();
        } else {
            $this->typeId = null;
        }

        return $this;
    }

    public function typeId(mixed $value): static
    {
        $this->typeId = $value;

        return $this;
    }

    public function orderId(mixed $value): static
    {
        $this->orderId = $value;

        return $this;
    }

    public function licenseKey(mixed $value): static
    {
        $this->licenseKey = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the license issue date.
     */
    public function licenseDate(mixed $value): static
    {
        $this->licenseDate = $value instanceof DateTimeInterface ? $value->format(DateTimeInterface::W3C) : $value;

        return $this;
    }

    public function before(DateTimeInterface|string $value): static
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format(DateTimeInterface::W3C);
        }

        $this->licenseDate = Arr::wrap($this->licenseDate);
        $this->licenseDate[] = '<'.$value;

        return $this;
    }

    public function after(DateTimeInterface|string $value): static
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format(DateTimeInterface::W3C);
        }

        $this->licenseDate = Arr::wrap($this->licenseDate);
        $this->licenseDate[] = '>='.$value;

        return $this;
    }
}
