<?php

/*$installer = $this;
$installer->startSetup();

$table = $installer->getConnection()->newTable($installer->getTable('lightspeed/log'))
    ->addColumn('log_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
        'nullable' => false,
        'primary' => true,
        'identity' => true,
    ), 'ID')
    ->addColumn('date', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        'nullable' => false,
    ), 'Date')
    ->addColumn('resource', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => false,
    ), 'Resource')
    ->addColumn('json', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => false,
        'length' => 2000,
    ), 'Timestamp')
    ->addColumn('exception', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => true,
    ), 'Exception');

$installer->getConnection()->createTable($table);
$installer->endSetup();*/