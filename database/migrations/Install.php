<?php

namespace craft\digitalproducts\migrations;

use craft\db\Migration;
use craft\digitalproducts\db\Table;
use craft\helpers\Db;

/**
 * Installation Migration
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropForeignKeys();
        $this->dropTables();

        return true;
    }

    /**
     * Creates the tables for Digital Products
     */
    protected function createTables(): void
    {
        $this->archiveTableIfExists(Table::LICENSES);
        $this->createTable(Table::LICENSES, [
            'id' => $this->primaryKey(),
            'productId' => $this->integer()->null(),
            'orderId' => $this->integer()->null(),
            'licenseKey' => $this->string()->notNull(),
            'ownerName' => $this->string(),
            'ownerEmail' => $this->string()->null(),
            'userId' => $this->integer()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::PRODUCTS);
        $this->createTable(Table::PRODUCTS, [
            'id' => $this->primaryKey(),
            'typeId' => $this->integer()->notNull(),
            'taxCategoryId' => $this->integer()->notNull(),
            'postDate' => $this->dateTime(),
            'expiryDate' => $this->dateTime(),
            'promotable' => $this->boolean()->notNull(),
            'sku' => $this->string()->notNull(),
            'price' => $this->decimal(14, 4)->notNull()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::PRODUCT_TYPES);
        $this->createTable(Table::PRODUCT_TYPES, [
            'id' => $this->primaryKey(),
            'fieldLayoutId' => $this->integer(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'skuFormat' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::PRODUCT_TYPES_SITES);
        $this->createTable(Table::PRODUCT_TYPES_SITES, [
            'id' => $this->primaryKey(),
            'productTypeId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'uriFormat' => $this->text(),
            'template' => $this->string(500),
            'hasUrls' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    /**
     * Drop the tables
     */
    protected function dropTables(): void
    {
        $this->dropTable(Table::LICENSES);
        $this->dropTable(Table::PRODUCTS);
        $this->dropTable(Table::PRODUCT_TYPES);
        $this->dropTable(Table::PRODUCT_TYPES_SITES);
    }

    /**
     * Creates the indexes.
     */
    protected function createIndexes(): void
    {
        $this->createIndex(null, Table::LICENSES, 'licenseKey', true);
        $this->createIndex(null, Table::LICENSES, 'orderId', false);
        $this->createIndex(null, Table::LICENSES, 'productId', false);

        $this->createIndex(null, Table::PRODUCTS, 'sku', true);
        $this->createIndex(null, Table::PRODUCTS, 'typeId', false);
        $this->createIndex(null, Table::PRODUCTS, 'taxCategoryId', false);

        $this->createIndex(null, Table::PRODUCT_TYPES, 'handle', true);
        $this->createIndex(null, Table::PRODUCT_TYPES, 'fieldLayoutId', false);

        $this->createIndex(null, Table::PRODUCT_TYPES_SITES, ['productTypeId', 'siteId'], true);
        $this->createIndex(null, Table::PRODUCT_TYPES_SITES, 'siteId', false);
    }

    /**
     * Adds the foreign keys.
     */
    protected function addForeignKeys(): void
    {
        $this->addForeignKey(null, Table::LICENSES, 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey(null, Table::LICENSES, 'orderId', '{{%commerce_orders}}', 'id', 'SET NULL', null);
        $this->addForeignKey(null, Table::LICENSES, 'productId', Table::PRODUCTS, 'id', 'RESTRICT', null);
        $this->addForeignKey(null, Table::LICENSES, 'userId', '{{%users}}', 'id', 'SET NULL', null);

        $this->addForeignKey(null, Table::PRODUCTS, 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey(null, Table::PRODUCTS, 'taxCategoryId', '{{%commerce_taxcategories}}', 'id', 'RESTRICT', null);
        $this->addForeignKey(null, Table::PRODUCTS, 'typeId', Table::PRODUCT_TYPES, 'id', 'CASCADE', null);

        $this->addForeignKey(null, Table::PRODUCT_TYPES, 'fieldLayoutId', '{{%fieldlayouts}}', 'id', 'SET NULL', null);

        $this->addForeignKey(null, Table::PRODUCT_TYPES_SITES, 'siteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCT_TYPES_SITES, 'productTypeId', Table::PRODUCT_TYPES, 'id', 'CASCADE', null);
    }

    /**
     * Adds the foreign keys.
     */
    protected function dropForeignKeys(): void
    {
        Db::dropForeignKeyIfExists(Table::LICENSES, ['id']);
        Db::dropForeignKeyIfExists(Table::LICENSES, ['orderId']);
        Db::dropForeignKeyIfExists(Table::LICENSES, ['productId']);
        Db::dropForeignKeyIfExists(Table::LICENSES, ['userId']);
        Db::dropForeignKeyIfExists(Table::PRODUCTS, ['id']);
        Db::dropForeignKeyIfExists(Table::PRODUCTS, ['taxCategoryId']);
        Db::dropForeignKeyIfExists(Table::PRODUCTS, ['typeId']);
        Db::dropForeignKeyIfExists(Table::PRODUCT_TYPES, ['fieldLayoutId']);
        Db::dropForeignKeyIfExists(Table::PRODUCT_TYPES_SITES, ['siteId']);
        Db::dropForeignKeyIfExists(Table::PRODUCT_TYPES_SITES, ['productTypeId']);
    }
}
