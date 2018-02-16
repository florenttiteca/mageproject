<?php
$installer = $this;
$installer->startSetup();

$this->addAttribute('catalog_category', 'arboresence_classique', array(
    'group'         => 'Display Settings',
    'input' => 'select',
    'type' => 'int',
    'source' => 'eav/entity_attribute_source_boolean',
    'label'         => 'Arborescence classique',
    'visible'       => true,
    'required'      => false,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'default'       => '1'
));


$installer->endSetup();
