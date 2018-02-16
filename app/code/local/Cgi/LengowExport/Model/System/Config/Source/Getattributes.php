<?php

/**
 * Class Cgi_LengowExport_Model_System_Config_Source_Getattributes
 *
 * @category     Cgi
 * @package      Cgi_LengowExport
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Cgi_LengowExport_Model_System_Config_Source_Getattributes
    extends Lengow_Export_Model_System_Config_Source_Getattributes
{

    /**
     * Function toOptionArray
     *
     * @return array
     */
    public function toOptionArray()
    {
        $attribute = Mage::getResourceModel('eav/entity_attribute_collection')
                         ->setEntityTypeFilter(Mage::getModel('catalog/product')
                                                   ->getResource()
                                                   ->getTypeId())
                         ->setOrder('attribute_code', 'ASC');

        $attributeArray = array();
        $attributeArray[] = array('value' => 'none',
                                  'label' => '',
        );

        foreach ($attribute as $option) {
            $attributeArray[] = array(
                'value' => $option->getAttributeCode(),
                'label' => $option->getAttributeCode() . $option->getAttributeSet()
            );
        }
        return $attributeArray;
    }

}
