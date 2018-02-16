<?php

/**
 * Class Magoffice_Chili_Model_Source_Chilistyles
 *
 * @category   Magoffice
 * @package    Magoffice_Chili
 * @author     Florent TITECA <florent.titeca@cgi.com>
 * @copyright  CGI 2017
 * @version    1.0
 */
class Magoffice_Chili_Model_Source_Chilistyles extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    public function getAllOptions()
    {
        $attribute = Mage::getModel('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'chili_style_value');
        $options = $attribute->getSource()->getAllOptions();
        return $options;
    }

    /**
     * Provide available options as a value/label array
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = $this->getAllOptions();

        return $options;
    }
}
