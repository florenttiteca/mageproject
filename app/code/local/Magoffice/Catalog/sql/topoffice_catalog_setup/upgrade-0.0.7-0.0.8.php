<?php
$installer = $this;
$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$attributeName = 'origin_price_ttc';
$attributeLabel = 'Prix origine TTC';
$setup->addAttribute('catalog_product', $attributeName, array(
    'group'                      => 'Prices',
    'input'                      => 'text',
    'type'                       => 'decimal',
    'label'                      => $attributeLabel,
    'frontend'                   => '',
    'backend'                    => '',
    'visible'                    => 1,
    'required'                   => 0,
    'user_defined'               => 1,
    'searchable'                 => 0,
    'filterable'                 => 0,
    'comparable'                 => 0,
    'visible_on_front'           => 0,
    'visible_in_advanced_search' => 0,
    'used_in_product_listing'    => 0,
    'is_html_allowed_on_front'   => 0,
    'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
));

$attributeName = 'origin_price_ht';
$attributeLabel = 'Prix origine HT';
$setup->addAttribute('catalog_product', $attributeName, array(
    'group'                      => 'Prices',
    'input'                      => 'text',
    'type'                       => 'decimal',
    'label'                      => $attributeLabel,
    'frontend'                   => '',
    'backend'                    => '',
    'visible'                    => 1,
    'required'                   => 0,
    'user_defined'               => 1,
    'searchable'                 => 0,
    'filterable'                 => 0,
    'comparable'                 => 0,
    'visible_on_front'           => 0,
    'visible_in_advanced_search' => 0,
    'used_in_product_listing'    => 0,
    'is_html_allowed_on_front'   => 0,
    'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
));

$installer->endSetup();
