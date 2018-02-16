<?php

/**
 * Class Lengow_Sync_Model_Order
 *
 * @category     Lengow
 * @package      Lengow_Sync
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2015
 * @version      v1.0
 */
class Lengow_Sync_Model_Order extends C2bi_Salesc2bi_Model_Order
{

    protected $_countryCollection;

    protected $_config;

    protected $_canInvoice = false;

    protected $_canShip = false;

    protected $_canCancel = false;

    protected $_canRefund = false;

    protected $_hasInvoices = false;

    protected $_hasShipments = false;

    protected $_isCanceled = false;

    protected $_isRefunded = false;

    /**
     * is Already Imported
     *
     * @param integer $idLengow Lengow order id
     * @param integer $idFlux Id flux Lengow
     *
     * @return mixed
     */
    public function isAlreadyImported($idLengow, $idFlux)
    {
        $orderCollection = $this->getCollection()
                                ->addAttributeToFilter('order_id_lengow', $idLengow)
                                ->addAttributeToFilter('feed_id_lengow', $idFlux)
                                ->addAttributeToSelect('entity_id')
                                ->getData();
        return isset($orderCollection[0]['entity_id']) ? $orderCollection[0]['entity_id'] : false;
    }

    /**
     * Retrieve config singleton
     *
     * @return Lengow_Sync_Model_Config
     */
    public function getConfig()
    {
        if (is_null($this->_config)) {
            $this->_config = Mage::getSingleton('lensync/config');
        }
        return $this->_config;
    }

    /**
     * Set config
     *
     * @param Lengow_Sync_Model_Config $config
     *
     * @return Lengow_Sync_Model_Order
     */
    public function setConfig($config)
    {
        $this->_config = $config;
        return $this;
    }

    /**
     * Create invoice
     *
     * @param Mage_Sales_Model_Order $order
     *
     */
    public function toInvoice($order)
    {
        $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
        if ($invoice) {
            $invoice->register();
            $invoice->getOrder()->setIsInProcess(true);
            $transactionSave = Mage::getModel('core/resource_transaction')
                                   ->addObject($invoice)
                                   ->addObject($invoice->getOrder());
            $transactionSave->save();
            $order->_hasInvoices = true;

            if($invoice->getId()){
                $invoice->sendEmail(true, '', false);
            }else{
                Mage::helper('lensync/data')->log('ERROR when sending invoice fo*r order : '.$order->getIncrementId().'
                no invoice id.');
            }
        }
    }

    /**
     * Ship order
     *
     * @param Mage_Sales_Model_Order $order
     * @param string $carrier
     * @param string $title
     * @param string $tracking
     *
     */
    public function toShip($order, $carrier = null, $title = '', $tracking = '')
    {
        if ($order->canShip()) {
            $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment();
            if ($shipment) {
                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);
                $transactionSave = Mage::getModel('core/resource_transaction')
                                       ->addObject($shipment)
                                       ->addObject($shipment->getOrder());
                $transactionSave->save();
                $this->_hasShipments = true;
                // Add tracking information
                if ($tracking) {
                    $shipment = Mage::getModel('sales/order_shipment')->loadByIncrementId($shipment->getIncrementId());
                    $track = Mage::getModel('sales/order_shipment_track')
                                 ->setNumber($tracking)
                                 ->setCarrierCode($carrier)
                                 ->setTitle($title);
                    $shipment->addTrack($track);
                }
                try {
                    $shipment->save();
                    if (isset($track)) {
                        $track->save();
                    }
                } catch (Mage_Core_Exception $e) {
                    Mage::helper('lensync/data')->log('ERROR create shipment : ' . $e->getMessage(),
                        $order->getOrderIdLengow());
                }
            }
        }
    }

    /**
     * Cancel order
     *
     * @param Mage_Sales_Model_Order $order
     *
     */
    public function toCancel($order)
    {
        if ($this->_canCancel && $order->canCancel()) {
            $order->cancel();
            $this->_isCanceled = true;
        }
    }

    /**
     * Refund Order
     *
     * @param Lengow_Sync_Model_Order $order
     * @return $this
     */
    public function toRefund(Lengow_Sync_Model_Order $order)
    {
        if ($this->_canRefund && $order->canCreditmemo()) {
            $invoiceId = $order->getInvoiceCollection()->getFirstItem()->getId();
            if (!$invoiceId) {
                return $this;
            }
            $invoice = Mage::getModel('sales/order_invoice')->load($invoiceId)->setOrder($order);
            $service = Mage::getModel('sales/service_order', $order);
            $creditmemo = $service->prepareInvoiceCreditmemo($invoice);
            $backToStock = array();
            foreach ($order->getAllItems() as $item) {
                $backToStock[$item->getId()] = true;
            }
            // Process back to stock flags
            foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                $orderItem = $creditmemoItem->getOrderItem();
                $parentId = $orderItem->getParentItemId();
                if (Mage::helper('cataloginventory')->isAutoReturnEnabled()) {
                    $creditmemoItem->setBackToStock(true);
                } else {
                    $creditmemoItem->setBackToStock(false);
                }
            }
            $creditmemo->register();
            $transactionSave = Mage::getModel('core/resource_transaction')
                                   ->addObject($creditmemo)
                                   ->addObject($creditmemo->getOrder());
            if ($creditmemo->getInvoice()) {
                $transactionSave->addObject($creditmemo->getInvoice());
            }
            $transactionSave->save();
            $this->_isRefunded = true;
        }
        return $this;
    }

    /**
     * Retrieve country id based on country name
     *
     * @param string $countryName
     *
     * @return string
     */
    protected function _getCountryId($countryName)
    {
        if (is_null($this->_countryCollection)) {
            $this->_countryCollection = Mage::getResourceModel('directory/country_collection')->toOptionArray();
        }
        foreach ($this->_countryCollection as $country) {
            if (strtolower($country['label']) == strtolower($countryName)) {
                return $country['value'];
            }
        }
        return $countryName;
    }

    /**
     * Get Magento equivalent to lengow order state
     *
     * @param  string $lengowStatus lengow state
     *
     * @return string
     */
    public function getOrderState($lengowStatus)
    {
        switch ($lengowStatus) {
            case 'new':
                return Mage_Sales_Model_Order::STATE_NEW;
                break;
            case 'processing':
                return Mage_Sales_Model_Order::STATE_PROCESSING;
                break;
            case 'shipped':
                return Mage_Sales_Model_Order::STATE_COMPLETE;
                break;
            case 'canceled':
                return Mage_Sales_Model_Order::STATE_CANCELED;
                break;
        }

        return false;
    }

    /**
     * Update order state to marketplace state
     *
     * @param Mage_Sales_Model_Order $order Magento Order
     * @param string $lengowStatus marketplace status
     * @param string $orderData order data
     *
     * @return bool     true if order has been updated
     */
    public function updateState($order, $lengowStatus, SimpleXMLelement $orderData)
    {
        $helper = Mage::helper('lensync/data');
        // Update order's status only if in process, shipped, or canceled
        if ($order->getState() != self::getOrderState($lengowStatus) && $order->getData('from_lengow') == 1) {
            if ($order->getState() == self::getOrderState('new') && $lengowStatus == 'processing') {
                // generate invoice
                $this->toInvoice($order);
                $helper->log('state updated to "processing" (Order ' . $order->getIncrementId() . ')',
                    $order->getOrderIdLengow());
                return true;
            } elseif (($order->getState() == self::getOrderState('processing') ||
                       $order->getState() == self::getOrderState('new'))
                      && $lengowStatus == 'shipped'
            ) {
                // if order is new -> generate invoice
                if ($order->getState() == self::getOrderState('new'))
                    $this->toInvoice($order);
                $this->toShip(
                    $order,
                    (string)$orderData->tracking_informations->tracking_carrier,
                    (string)$orderData->tracking_informations->tracking_method,
                    (string)$orderData->tracking_informations->tracking_number
                );
                $helper->log('state updated to "shipped" (Order ' . $order->getIncrementId() . ')',
                    $order->getOrderIdLengow());
                return true;
            } else if (($order->getState() == self::getOrderState('processing') ||
                        $order->getState() == self::getOrderState('shipped'))
                       && $lengowStatus == 'canceled'
            ) {
                $this->toCancel($order);
                if ($this->_isCanceled) {
                    $helper->log('state update to "canceled" (Order ' . $order->getIncrementId() . ')',
                        $order->getOrderIdLengow());
                    return true;
                }
                return false;
            }
        }
        return false;
    }
}