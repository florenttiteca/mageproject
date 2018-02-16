<?php

/**
 * Class Magoffice_Sellsecure_Model_Source_Producttypes
 *
 * @category     Magoffice
 * @package      Magoffice_Sellsecure
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Sellsecure_Model_Source_Producttypes extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    /**
     * Retrieve all options array
     *
     * @return array
     */
    public function getAllOptions()
    {
        $productTypesConfig = Mage::getStoreConfig('magoffice_sellsecure/flow_url_mapping/product_types_matrix');
        $productTypesConfig = json_decode($productTypesConfig);

        $options = array();

        $options[] = array(
            'label' => '',
            'value' => 0
        );

        foreach ($productTypesConfig as $key => $productType) {
            $options[] = array(
                'label' => $productType,
                'value' => $key
            );
        }

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
