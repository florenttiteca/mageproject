<?php

/**
 * Class Magoffice_Sellsecure_Model_Source_Paymentmethods
 *
 * @category     Magoffice
 * @package      Magoffice_Sellsecure
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Sellsecure_Model_Source_Paymentmethods
{
    /**
     * Provide available options as a value/label array
     *
     * @return array
     */
    public function toOptionArray()
    {
        $activePaymentMethods = Mage::getModel('payment/config')->getActiveMethods();

        $options = array();
        foreach ($activePaymentMethods as $paymentCode => $paymentModel) {
            $paymentTitle = Mage::getStoreConfig('payment/' . $paymentCode . '/title');
            $options[] = array(
                'label' => $paymentTitle,
                'value' => $paymentCode,
            );
        }

        return $options;
    }
}
