<?php

namespace craft\digitalproducts\migrations;

use craft\commerce\db\Table as CommerceTable;
use craft\db\Migration;
use craft\db\Query;
use craft\digitalproducts\db\Table;

/**
 * m260526_000000_drop_sku_price_columns migration.
 */
class m260526_000000_drop_sku_price_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $columns = [];
        if ($this->db->columnExists(Table::PRODUCTS, 'price')) {
            $columns[] = 'price';
        }
        if ($this->db->columnExists(Table::PRODUCTS, 'sku')) {
            $columns[] = 'sku';
        }

        if (!empty($columns)) {
            $columns[] = 'id';
            $products = (new Query())
                ->select($columns)
                ->from(Table::PRODUCTS)
                ->all();

            foreach ($products as $product) {
                if (isset($product['price'])) {
                    $this->update(
                        CommerceTable::PURCHASABLES_STORES,
                        ['basePrice' => $product['price']],
                        ['purchasableId' => $product['id']],
                        [],
                        false,
                    );
                }

                if (isset($product['sku'])) {
                    $this->update(
                        CommerceTable::PURCHASABLES,
                        ['sku' => $product['sku']],
                        ['id' => $product['id']],
                        [],
                        false,
                    );
                }
            }
        }

        if ($this->db->columnExists(Table::PRODUCTS, 'price')) {
            $this->dropColumn(Table::PRODUCTS, 'price');
        }

        if ($this->db->columnExists(Table::PRODUCTS, 'sku')) {
            $this->dropColumn(Table::PRODUCTS, 'sku');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260526_000000_drop_sku_price_columns cannot be reverted.\n";
        return false;
    }
}
