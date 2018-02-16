<?php
$installer = $this;
$installer->startSetup();

// Creating 'parent' attribute
$installer->addAttribute('catalog_product', 'parent', array(
        'type'              => 'varchar',
        'backend'           => '',
        'frontend'          => '',
        'label'             => 'Parent',
        'input'             => 'text',
        'class'             => '',
        'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'visible'           => true,
        'required'          => false,
        'user_defined'      => false,
        'searchable'        => false,
        'filterable'        => false,
        'comparable'        => false,
        'visible_on_front'  => false,
        'visible_in_advanced_search' => false,
        'unique'            => false,
    )
);

// Creating 'children' attribute
$installer->addAttribute('catalog_product', 'children', array(
    'type'              => 'varchar',
    'backend'           => '',
    'frontend'          => '',
    'label'             => 'Children',
    'input'             => 'text',
    'class'             => '',
    'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'           => true,
    'required'          => false,
    'user_defined'      => false,
    'searchable'        => false,
    'filterable'        => false,
    'comparable'        => false,
    'visible_on_front'  => false,
    'visible_in_advanced_search' => false,
    'unique'            => false,
));

// Creating 'relation_type' attribute
$installer->addAttribute('catalog_product', 'relation_type', array(
    'input'    => 'select',
    'type'     => 'int',
    'source'   => 'eav/entity_attribute_source_boolean',
    'label'    => 'Relation type',
    'visible'  => true,
    'required' => false,
    'global'   => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'default'  => '0'
));

// Creating 'is_parent' attribute
$installer->addAttribute('catalog_product', 'is_parent', array(
    'input'    => 'select',
    'type'     => 'int',
    'source'   => 'eav/entity_attribute_source_boolean',
    'label'    => 'Is parent',
    'visible'  => true,
    'required' => false,
    'global'   => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'default'  => '0'
));

$installer->endSetup();