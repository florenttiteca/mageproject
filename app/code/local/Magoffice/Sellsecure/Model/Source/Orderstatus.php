<?php

/**
 * Class Magoffice_Sellsecure_Model_Source_Orderstatus
 *
 * @category     Magoffice
 * @package      Magoffice_Sellsecure
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Sellsecure_Model_Source_Orderstatus
{
    /**
     * Provide available options as a value/label array
     *
     * @return array
     */
    public function toOptionArray()
    {
        $orderStatus = Mage::getModel('sales/order_status')->getResourceCollection()->getData();

        $options = array();
        foreach ($orderStatus as $status) {
            $options[] = array(
                'value' => $status['status'],
                'label' => $status['label']
            );
        }

        return $options;
    }
}
