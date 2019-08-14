<?php

$installer = $this;
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
$installer->startSetup();

$setup->addAttribute('order', 'posiosId', array(
    'type'          => 'varchar',
    'visible'       => true,
    'required'      => false,
    'is_user_defined' => false,
    'note'           => 'posios id',
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
));

$installer->getConnection()->addColumn($installer->getTable('sales_flat_order'), 'posiosId','VARCHAR(255) NULL DEFAULT NULL');
$installer->getConnection()->addColumn($installer->getTable('sales_flat_order_grid'), 'posiosId','VARCHAR(255) NULL DEFAULT NULL');


$installer->endSetup();