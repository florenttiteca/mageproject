<?php
$installer = $this;
$installer->startSetup();

// Creating 'duree_dispo_pieces_detachees_text' attribute
$installer->addAttribute('catalog_product', 'duree_dispo_pieces_detachees', array(
    'group'                      => 'General',
    'input'                      => 'text',
    'type'                       => 'varchar',
    'label'                      => 'Disponibilité des pièces détachées',
    'frontend'                   => '',
    'backend'                    => '',
    'visible'                    => 1,
    'required'                   => 0,
    'user_defined'               => 1,
    'searchable'                 => 0,
    'filterable'                 => 0,
    'comparable'                 => 0,
    'visible_on_front'           => 1,
    'visible_in_advanced_search' => 0,
    'used_in_product_listing'    => 0,
    'is_html_allowed_on_front'   => 0,
    'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
));

$attributeId = $installer->getAttributeId('catalog_product', 'duree_dispo_pieces_detachees');

$attributeGroups = Mage::getModel('eav/entity_attribute_group')
                       ->getCollection()
                       ->addFieldToFilter('attribute_group_name', 'spec_generic');

// Setting 'duree_dispo_pieces_detachees' attribute to all 'spec_generic' attributes groups
foreach ($attributeGroups as $attributeGroup) {
    $installer->addAttributeToSet('catalog_product', $attributeGroup->getAttributeSetId(),
        $attributeGroup->getAttributeGroupId(), $attributeId);
}

$installer->endSetup();
