<?php

Mage::log('Adding posiosId to category and product', null, "lightspeed.log");

$installer = $this;
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
$installer->startSetup();

$setup->addAttribute('catalog_category', 'posiosId', array(
    'group'         => 'General Information',
    'input'         => 'text',
    'type'          => 'int',
    'label'         => 'posiosId',
    'backend'       => '',
    'visible'       => true,
    'required'      => false,
    'visible_on_front' => true,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
));

$setup->addAttribute('catalog_product', 'posiosId', array(
    'group'         => 'General',
    'input'         => 'text',
    'type'          => 'int',
    'label'         => 'posiosId',
    'backend'       => '',
    'visible'       => true,
    'required'      => false,
    'visible_on_front' => true,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
));

$entityTypeId     = $setup->getEntityTypeId('customer');
$attributeSetId   = $setup->getDefaultAttributeSetId($entityTypeId);
$attributeGroupId = $setup->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

$setup->addAttribute('customer', 'posiosId', array(
    'input'         => 'text',
    'type'          => 'text',
    'label'         => 'posiosId',
    'backend'       => '',
    'visible'       => true,
    'required'      => false,
    'visible_on_front' => true,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
));

$setup->addAttributeToGroup(
    $entityTypeId,
    $attributeSetId,
    $attributeGroupId,
    'posiosId',
    '999'  //sort_order
);

$oAttribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'posiosId');
$oAttribute->setData('used_in_forms', array('adminhtml_customer'));

$oAttribute->save();

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
Mage::log('Added posiosId to category and product', null, "lightspeed.log");
