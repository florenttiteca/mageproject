<?php

/**
 * Class Magoffice_Chili_Model_Source_Chiliorientation
 *
 * @category   Magoffice
 * @package    Magoffice_Chili
 * @author     Florent TITECA <florent.titeca@cgi.com>
 * @copyright  CGI 2017
 * @version    1.0
 */
class Magoffice_Chili_Model_Source_Chiliorientation extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    public function getAllOptions()
    {
        return array(
            array('value' => '1', 'label' => Mage::helper('magoffice_chili')->__('Portrait')),
            array('value' => '2', 'label' => Mage::helper('magoffice_chili')->__('Paysage')),
        );
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
