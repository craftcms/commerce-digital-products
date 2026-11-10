<?php

namespace craft\digitalproducts\elements\db;

use craft\base\Element;
use craft\db\Query;
use craft\digitalproducts\elements\License;
use craft\digitalproducts\elements\Product;
use craft\digitalproducts\models\ProductType;
use craft\elements\db\ElementQuery;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use DateTime;
use DateTimeInterface;
use yii\db\Connection;

/**
 * LicenseQuery represents a SELECT SQL statement for products in a way that is independent of DBMS.
 *
 * @method License[]|array all($db = null)
 * @method License|array|false one($db = null)
 * @method License|array|false nth(int $n, Connection $db = null)
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class LicenseQuery extends ElementQuery
{
    /**
     * @var string|null Either owner or user email on the license
     */
    public ?string $email = null;

    /**
     * @var string|null Email of the license owner
     */
    public ?string $ownerEmail = null;

    /**
     * @var string|null Email of the user that owns the license
     */
    public ?string $userEmail = null;

    /**
     * @var int|int[]|null The user id for the user that the license belongs to.
     */
    public array|int|null $ownerId = null;

    /**
     * @var int|int[]|null The product id for the product that is licensed
     */
    public array|int|null $productId = null;

    /**
     * @var int|int[]|null The product type id for the product that is licensed
     */
    public array|int|null $typeId = null;

    /**
     * @var int|string|null The license date on the license
     */
    public int|string|null $licenseDate = null;

    /**
     * @var int|null The id of the order that the license must be a part of.
     */
    public ?int $orderId = null;

    /**
     * @var string|null The license key.
     */
    public ?string $licenseKey = null;

    /**
     * @inheritdoc
     */
    public function __construct(string $elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = Element::STATUS_ENABLED;
        }

        parent::__construct($elementType, $config);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'owner':
                $this->owner($value);
                break;
            case 'product':
                $this->product($value);
                break;
            case 'type':
                $this->type($value);
                break;
            case 'before':
                $this->before($value);
                break;
            case 'after':
                $this->after($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * Sets the [[email]] property.
     *
     * @param string|null $value The property value
     * @return static self reference
     */
    public function email(?string $value): LicenseQuery
    {
        $this->email = $value;
        return $this;
    }

    /**
     * Sets the [[ownerEmail]] property.
     *
     * @param string|null $value The property value
     * @return static self reference
     */
    public function ownerEmail(?string $value): LicenseQuery
    {
        $this->ownerEmail = $value;
        return $this;
    }

    /**
     * Sets the [[userEmail]] property.
     *
     * @param string|null $value The property value
     * @return static self reference
     */
    public function userEmail(?string $value): LicenseQuery
    {
        $this->userEmail = $value;
        return $this;
    }

    /**
     * Sets the [[productId]] property based on a given product or the sku.
     *
     * @param User|string|null $value
     * @return static self reference
     */
    public function owner(User|string|null $value): LicenseQuery
    {
        if ($value instanceof User) {
            $this->ownerId = $value->id;
        } elseif ($value !== null) {
            $this->ownerId = (new Query())
                ->select(['id'])
                ->from(['{{%users}}'])
                ->where(Db::parseParam('username', $value))
                ->column();
        } else {
            $this->ownerId = null;
        }

        return $this;
    }

    /**
     * Sets the [[productId]] property based on a given product or SKU(s).
     *
     * @param Product|string|string[]|null $value
     * @return static self reference
     */
    public function product(Product|array|string|null $value): LicenseQuery
    {
        if ($value instanceof Product) {
            $this->productId = $value->id;
        } elseif ($value !== null) {
            $this->productId = (new Query())
                ->select(['id'])
                ->from(['{{%digitalproducts_products}}'])
                ->where(Db::parseParam('sku', $value))
                ->column();
        } else {
            $this->productId = null;
        }

        return $this;
    }

    /**
     * Sets the [[typeId]] property based on a given product types(s)’s handle(s).
     *
     * @param string|string[]|ProductType|null $value The property value
     * @return static self reference
     */
    public function type(mixed $value): LicenseQuery
    {
        if ($value instanceof ProductType) {
            $this->typeId = $value->id;
        } elseif ($value !== null) {
            $this->typeId = (new Query())
                ->select(['id'])
                ->from(['{{%digitalproducts_producttypes}}'])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->typeId = null;
        }

        return $this;
    }

    /**
     * Sets the [[postDate]] property to only allow products whose Post Date is before the given value.
     *
     * @param DateTime|string $value The property value
     * @return static self reference
     */
    public function before(DateTime|string $value): LicenseQuery
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->dateCreated = ArrayHelper::toArray($this->dateCreated);
        $this->dateCreated[] = '<' . $value;

        return $this;
    }

    /**
     * Sets the [[postDate]] property to only allow products whose Post Date is after the given value.
     *
     * @param DateTime|string $value The property value
     * @return static self reference
     */
    public function after(DateTime|string $value): LicenseQuery
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->dateCreated = ArrayHelper::toArray($this->dateCreated);
        $this->dateCreated[] = '>=' . $value;

        return $this;
    }

    /**
     * Sets the [[typeId]] property.
     *
     * @param int|int[] $value The property value
     * @return static self reference
     */
    public function typeId(array|int|null $value): LicenseQuery
    {
        $this->typeId = $value;
        return $this;
    }

    /**
     * Sets the [[ownerId]] property.
     *
     * @param int|int[] $value The property value
     * @return static self reference
     */
    public function ownerId(array|int|null $value): LicenseQuery
    {
        $this->ownerId = $value;
        return $this;
    }

    /**
     * Sets the [[productId]] property.
     *
     * @param int|int[] $value The property value
     * @return static self reference
     */
    public function productId(array|int|null $value): LicenseQuery
    {
        $this->productId = $value;
        return $this;
    }

    /**
     * Sets the [[orderId]] property.
     *
     * @param int|int[] $value The property value
     * @return static self reference
     */
    public function orderId(array|int|null $value): LicenseQuery
    {
        $this->orderId = $value;
        return $this;
    }

    /**
     * Sets the [[licenseKey]] property.
     *
     * @param string|string[] $value The property value
     * @return static self reference
     */
    public function licenseKey(array|string|null $value): LicenseQuery
    {
        $this->licenseKey = $value;
        return $this;
    }

    /**
     * Sets the [[licenseDate]] property.
     *
     * @param DateTime|string $value The property value
     * @return static self reference
     */
    public function licenseDate(DateTime|string $value): LicenseQuery
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTimeInterface::W3C);
        }

        $this->licenseDate = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        // See if 'type' were set to invalid handles
        if ($this->typeId === []) {
            return false;
        }

        $this->joinElementTable('digitalproducts_licenses');
        $this->subQuery->leftJoin('{{%digitalproducts_products}} digitalproducts_products', '[[digitalproducts_licenses.productId]] = [[digitalproducts_products.id]]');
        $this->subQuery->leftJoin('{{%users}} users', '[[digitalproducts_licenses.userId]] = [[users.id]]');

        $this->query->select([
            'digitalproducts_licenses.id',
            'digitalproducts_licenses.productId',
            'digitalproducts_licenses.licenseKey',
            'digitalproducts_licenses.ownerName',
            'digitalproducts_licenses.ownerEmail',
            'digitalproducts_licenses.userId',
            'digitalproducts_licenses.orderId',
        ]);

        if ($this->email) {
            $this->subQuery->andWhere(['or', ['digitalproducts_licenses.ownerEmail' => $this->email], ['users.email' => $this->email]]);
        }

        if ($this->ownerEmail) {
            $this->subQuery->andWhere(Db::parseParam('digitalproducts_licenses.ownerEmail', $this->ownerEmail));
        }

        if ($this->userEmail) {
            $this->subQuery->andWhere(Db::parseParam('users.email', $this->userEmail));
        }

        if ($this->ownerId) {
            $this->subQuery->andWhere(Db::parseParam('digitalproducts_licenses.userId', $this->ownerId));
        }

        if ($this->productId) {
            $this->subQuery->andWhere(Db::parseParam('digitalproducts_products.id', $this->productId));
        }

        if ($this->typeId) {
            $this->subQuery->andWhere(Db::parseParam('digitalproducts_products.typeId', $this->typeId));
        }

        if ($this->licenseDate) {
            $this->subQuery->andWhere(Db::parseDateParam('digitalproducts_products.dateCreated', $this->licenseDate));
        }

        if ($this->orderId) {
            $this->subQuery->andWhere(Db::parseParam('digitalproducts_licenses.orderId', $this->orderId));
        }

        if ($this->licenseKey) {
            $this->subQuery->andWhere(Db::parseParam('digitalproducts_licenses.licenseKey', $this->licenseKey));
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status): mixed
    {
        return match ($status) {
            Element::STATUS_ENABLED => [
                'elements.enabled' => '1',
            ],
            License::STATUS_DISABLED => [
                'elements.disabled' => '1',
            ],
            default => parent::statusCondition($status),
        };
    }
}
