<?php

namespace craft\digitalproducts\models;

use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;
use craft\digitalproducts\elements\Product;
use craft\digitalproducts\Plugin as DigitalProducts;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;

/**
 * Product type model.
 *
 * @method null setFieldLayout(FieldLayout $fieldLayout)
 * @method FieldLayout getFieldLayout()
 * @property string $cpEditUrl
 * @property ProductTypeSite[] $siteSettings
 * @property mixed $productFieldLayout
 * @mixin FieldLayoutBehavior
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 */
class ProductType extends Model
{
    /**
     * @var int ID
     */
    public $id;

    /**
     * @var int Field layout id
     */
    public $fieldLayoutId;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var string Handle
     */
    public $handle;

    /**
     * @var string SKU format
     */
    public $skuFormat;

    /**
     * @var string|null UID
     */
    public $uid;

    /**
     * @var ProductTypeSite[]
     */
    private $_siteSettings;

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        return $this->handle;
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('digital-products/producttypes/' . $this->id);
    }

    /**
     * Returns the product types's site-specific settings.
     *
     * @return ProductTypeSite[]
     */
    public function getSiteSettings(): array
    {
        if ($this->_siteSettings !== null) {
            return $this->_siteSettings;
        }

        if (!$this->id) {
            return [];
        }

        $this->setSiteSettings(ArrayHelper::index(DigitalProducts::getInstance()->getProductTypes()->getProductTypeSites($this->id), 'siteId'));

        return $this->_siteSettings;
    }

    /**
     * Sets the product type's site-specific settings.
     *
     * @param ProductTypeSite[] $siteSettings
     */
    public function setSiteSettings(array $siteSettings)
    {
        $this->_siteSettings = $siteSettings;

        foreach ($this->_siteSettings as $settings) {
            $settings->setProductType($this);
        }
    }

    /**
     * @return FieldLayout
     */
    public function getProductFieldLayout(): FieldLayout
    {
        /** @var FieldLayoutBehavior $behavior */
        $behavior = $this->getBehavior('productFieldLayout');
        return $behavior->getFieldLayout();
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return [
            'productFieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => Product::class,
                'idAttribute' => 'fieldLayoutId',
            ],
        ];
    }
}
