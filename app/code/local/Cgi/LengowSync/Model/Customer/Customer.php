<?php

/**
 * Class Cgi_LengowSync_Model_Customer_Customer
 *
 * @category     Cgi
 * @package      Cgi_LengowSync
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Cgi_LengowSync_Model_Customer_Customer extends Lengow_Sync_Model_Customer_Customer
{
    /**
     * Function setFromNode
     *
     * @param SimpleXMLElement $xmlNode
     * @param $config
     * @return $this
     */
    public function setFromNode(SimpleXMLElement $xmlNode, $config)
    {
        /** @var Cgi_LengowSync_Model_Customer_Customer $customer */
        $customer = parent::setFromNode($xmlNode, $config);

        $array = Mage::helper('lensync')->xmlToAssoc($xmlNode);

        $society = $array['billing_address']['billing_society'];

        // if society exist in billing adress, then the customer is in professional group
        if ($society) {
            $this->setGroupId(3);
        } else {
            $this->setGroupId(1);
        }

        $this->save();

        /** @var C2bi_Elfydatac2bi_Model_Elfydata $elfydata */
        $elfydata = Mage::getModel('elfydata/elfydata');

        $billingAddress = $customer->getDefaultBillingAddress();

        $additionnalData =
            array(
                'from_lengow' => 1
            );

        $elfydata->customer_account($additionnalData, $customer, $customer->getEmail(), $billingAddress);

        return $this;
    }
}