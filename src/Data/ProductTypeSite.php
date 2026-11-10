<?php

namespace craft\digitalproducts\models;

use Craft;
use craft\base\Model;
use craft\digitalproducts\Plugin as DigitalProducts;
use craft\models\Site;
use yii\base\InvalidConfigException;

/**
 * Product type locale model class.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 */
class ProductTypeSite extends Model
{
    /**
     * @var int ID
     */
    public $id;

    /**
     * @var int Product type ID
     */
    public $productTypeId;

    /**
     * @var int Site ID
     */
    public $siteId;

    /**
     * @var bool Has Urls
     */
    public $hasUrls;

    /**
     * @var string|null URL Format
     */
    public $uriFormat;

    /**
     * @var string|null Template Path
     */
    public $template;

    /**
     * @var ProductType
     */
    private $_productType;

    /**
     * @var bool
     */
    public $uriFormatIsRequired = true;

    /**
     * @var Site|null
     */
    private $_site = null;

    /**
     * Returns the Product Type.
     *
     * @return ProductType
     * @throws InvalidConfigException if [[groupId]] is missing or invalid
     */
    public function getProductType(): ProductType
    {
        if ($this->_productType !== null) {
            return $this->_productType;
        }

        if (!$this->productTypeId) {
            throw new InvalidConfigException('Site is missing its product type ID');
        }

        if (($this->_productType = DigitalProducts::getInstance()->getProductTypes()->getProductTypeById($this->productTypeId)) === null) {
            throw new InvalidConfigException('Invalid product type ID: ' . $this->productTypeId);
        }

        return $this->_productType;
    }

    /**
     * Sets the Product Type.
     *
     * @param ProductType $productType
     */
    public function setProductType(ProductType $productType)
    {
        $this->_productType = $productType;
    }

    /**
     * Gets the associated site.
     *
     * @return Site
     */
    public function getSite(): Site
    {
        if (!$this->_site) {
            $this->_site = Craft::$app->getSites()->getSiteById($this->siteId);
        }

        return $this->_site;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->uriFormatIsRequired) {
            $rules[] = ['uriFormat', 'required'];
        }

        return $rules;
    }
}
