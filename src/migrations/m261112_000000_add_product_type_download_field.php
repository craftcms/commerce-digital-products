<?php

namespace craft\digitalproducts\migrations;

use craft\db\Migration;
use craft\digitalproducts\db\Table;

/**
 * Adds the download field setting to product types.
 */
class m261112_000000_add_product_type_download_field extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Table::PRODUCT_TYPES, 'downloadFieldUid')) {
            $this->addColumn(Table::PRODUCT_TYPES, 'downloadFieldUid', $this->char(36)->after('skuFormat'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropColumn(Table::PRODUCT_TYPES, 'downloadFieldUid');

        return true;
    }
}
