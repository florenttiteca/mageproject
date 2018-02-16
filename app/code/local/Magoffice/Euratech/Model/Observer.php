<?php

/**
 * Class Magoffice_Euratech_Model_Observer
 *
 * @category   Magoffice_Euratech_Model_Observer
 * @package    Magoffice_Euratech_Model_Observer
 * @author     Florent TITECA <florent.titeca@cgi.com>
 * @copyright  CGI 2016
 * @version    1.0
 */
class Magoffice_Euratech_Model_Observer extends Mage_Core_Model_Observer
{
    public function sendStoreEmail(Varien_Event_Observer $observer)
    {
        $eventDatas = $observer->getEvent()->getData();

        if (isset($eventDatas['invoice'])) {
            /** @var Mage_Sales_Model_Order_Invoice $invoice */
            $invoice = $eventDatas['invoice'];
            /** @var Itroom_Score_Model_Rewrite_Sales_Order $order */
            $order = $invoice->getOrder();
        } else {
            /** @var Mage_Sales_Model_Order $order */
            if (isset($eventDatas['order'])) {
                $order   = $eventDatas['order'];
                $orderId = $order->getId();
            } else {
                $orderId = Mage::app()->getRequest()->getParam('order_id');
                $order   = Mage::getModel('sales/order')->load($orderId);
            }
        }

        $shippingMethod = $order->getShippingCarrier()->getCarrierCode();

        if ($shippingMethod == 'express') {
            /** @var Magoffice_Euratech_Helper_Data $helper */
            $helper = Mage::helper('magoffice_euratech');

            $helper->sendEuratechEmail($order);
        }
    }
}