<?php
$installer = $this;
$installer->startSetup();

$attribute = 'arboresence_classique';
$installer->removeAttribute('catalog_category', $attribute);

$this->addAttribute(
    'catalog_category', 'arborescence_category', array(
    'group'    => 'Display Settings',
    'input'    => 'select',
    'type'     => 'int',
    'source'   => 'eav/entity_attribute_source_boolean',
    'label'    => 'Arborescence par catégorie',
    'visible'  => true,
    'required' => false,
    'global'   => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'default'  => '0'
)
);


$installer->endSetup();
