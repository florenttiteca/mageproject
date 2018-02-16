<?php

/**
 * Class Magoffice_Sellsecure_Model_Observer
 *
 * @category     Magoffice
 * @package      Magoffice_Sellsecure
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Sellsecure_Model_Observer
{
    /**
     * Function setSellSecureOrder
     *
     * @param Varien_Event_Observer $observer
     * @return bool
     */
    public function setSellSecureOrder(Varien_Event_Observer $observer)
    {
        try {
            $order = $observer->getOrder();

            /** @var Magoffice_Sellsecure_Model_Sell_Secure_Order $sellSecureOrderModel */
            $sellSecureOrderModel =
                Mage::getModel('magoffice_sellsecure/sell_secure_order')->load($order->getEntityId());

            if ($sellSecureOrderModel->getState()) {
                return false;
            }

            $sellSecureOrderModel
                ->setOrderId($order->getEntityId())
                ->setIncrementId($order->getIncrementId())
                ->setState(Magoffice_Sellsecure_Helper_Data::SCORING_TODO)
                ->setCreatedAt(Mage::app()->getLocale()->date($order->getCreatedAt())->get('Y-MM-d H:m:s'));

            $sellSecureOrderModel->save();
        } catch (Exception $exception) {
        }
    }
}
