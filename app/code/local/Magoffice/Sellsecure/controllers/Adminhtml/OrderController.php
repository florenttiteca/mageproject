<?php

/**
 * Class Magoffice_Sellsecure_Adminhtml_OrderController
 *
 * @category     Magoffice
 * @package      Magoffice_Sellsecure
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Sellsecure_Adminhtml_OrderController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Function indexAction
     *
     */
    public function indexAction()
    {
        $this->_redirect('adminhtml/sales_order');
    }

    /**
     * Function sendEvaluationAction
     *
     */
    public function sendEvaluationAction()
    {
        try {
            $orderIds = $this->getRequest()->getPost('order_ids', array());
            $countHoldOrder = 0;
            $date = Mage::getModel('core/date')->date();

            foreach ($orderIds as $orderId) {
                $order = Mage::getModel('sales/order')->load($orderId);

                /** @var Magoffice_Sellsecure_Model_Sell_Secure_Order $sellSecureOrderModel */
                $sellSecureOrderModel = Mage::getModel('magoffice_sellsecure/sell_secure_order');

                $sellSecureItem = $sellSecureOrderModel->load($order->getId());

                if (!$sellSecureItem->getId()) {
                    $sellSecureItem->setOrderId($order->getId());
                    $sellSecureItem->setIncrementId($order->getIncrementId());
                    $sellSecureItem->setState(Magoffice_Sellsecure_Helper_Data::SCORING_FORCED);
                    $sellSecureItem->setCreatedAt($order->getCreatedAt());
                } else {
                    $sellSecureItem->setState(Magoffice_Sellsecure_Helper_Data::SCORING_FORCED);
                }

                $sellSecureItem->setUpdatedAt($date);
                $sellSecureItem->save();
                $countHoldOrder++;
            }

            $this->_getSession()->addSuccess(Mage::helper('magoffice_sellsecure')
                                                 ->__('%s order(s) successfully sent to Sell Secure', $countHoldOrder));

        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }
        $this->_redirect('adminhtml/sales_order');
    }
}
