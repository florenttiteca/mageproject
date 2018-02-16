<?php

/**
 * Class Magoffice_Exaprint_Model_Cardconfig
 *
 * @category     Magoffice
 * @package      Magoffice_Exaprint
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Exaprint_Model_Cardconfig extends Mage_Core_Model_Abstract
{
    /**
     * Function _construct
     *
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('magoffice_exaprint/cardconfig');
    }

    /**
     * Function getCardConfigsQuote
     *
     * @param $quoteItemId
     *
     * @return mixed
     */
    public function getCardConfigsQuote($quoteItemId)
    {
        return $this->getCollection()
            ->addFieldToSelect('*')
            ->addFieldToFilter('quote_item_id', $quoteItemId);
    }

    /**
     * getCardConfigsQuoteItem
     *
     * @param $quoteItemId
     *
     * @return mixed
     */
    public function getCardConfigsQuoteItem($quoteItemId)
    {
        return $this->getCollection()
            ->addFieldToSelect('*')
            ->addFieldToFilter('quote_item_id', $quoteItemId)
            ->getFirstItem();
    }

    /**
     * Function getCardConfigsQuoteItemsCount
     *
     * @param $quoteItemId
     *
     * @return mixed
     */
    public function getCardConfigsQuoteItemsCount($quoteItemId)
    {
        return $this->getCollection()
            ->addFieldToSelect('*')
            ->addFieldToFilter('quote_item_id', $quoteItemId)
            ->count();
    }

    /**
     * getCardConfigsOrderItemsCount
     *
     * @param $orderItemId
     *
     * @return mixed
     */
    public function getCardConfigsOrderItemsCount($orderItemId)
    {
        return $this->getCollection()
            ->addFieldToSelect('*')
            ->addFieldToFilter('order_item_id', $orderItemId)
            ->count();
    }

    /**
     * Function getCardConfigsOrder
     *
     * @param            $orderId
     * @param bool|false $quoteId
     *
     * @return mixed
     */
    public function getCardConfigsOrder($orderId)
    {
        $collection = $this->getCollection()
            ->addFieldToSelect('*')
            ->addFieldToFilter('order_item_id', $orderId);


        return $collection;
    }

    /**
     * Function isCardConfigOrder
     *
     * @param $orderId
     *
     * @return bool
     */
    public function isCardConfigOrder($orderId)
    {
        return ($this->getCollection()->addFieldToFilter('order_id', $orderId)->count() > 0);
    }

    /**
     * Function getCardConfigsByQuote
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return mixed
     */
    public function getCardConfigsByQuote($quote)
    {
        $ids = array();

        foreach ($quote->getAllItems() as $item) {
            $ids[] = $item->getItemId();
        }

        return $this->getCollection()
                    ->addFieldToSelect('*')
                    ->addFieldToFilter('quote_item_id', array('in' => $ids));
    }

    /**
     * Function removeItems
     *
     * @param $quoteId
     */
    public function removeItems($quoteId)
    {
        $products = $this->getCardConfigsQuote($quoteId);

        foreach ($products as $productConf) {
            $this->removeItem($productConf->getId());
        }
    }

    /**
     * Function removeQuoteCardConfigItem
     *
     * @param $sku
     * @param $quoteId
     */
    public function removeQuoteCardConfigItem($sku, $quoteId)
    {
        $products = $this->getCollection()
            ->addFieldToSelect('*')
            ->addFieldToFilter('product_id', $sku)
            ->addFieldToFilter('order_id', array('null' => true))
            ->addFieldToFilter('quote_id', $quoteId);

        foreach ($products as $productConf) {
            $this->removeItem($productConf->getId());
        }
    }

    /**
     * removeItem
     *
     * @param $itemId
     *
     * @return Mage_Core_Model_Abstract
     * @throws Exception
     */
    public function removeItem($itemId)
    {
        $productConf = $this->load($itemId, 'conf_id');

        return $productConf->delete();
    }

    /**
     * reorderChiliItem
     *
     * @param C2bi_Salesc2bi_Model_Quote_Item $quoteItem
     */
    public function reorderChiliItem(C2bi_Salesc2bi_Model_Quote_Item $quoteItem)
    {
        /** @var Magoffice_Exaprint_Model_Cardconfig $cardConfig */
        $cardConfig = $this->load($quoteItem->getData('conf_id'));

        /** @var Magoffice_Exaprint_Helper_Data $helper */
        $helper = Mage::helper('magoffice_exaprint');

        $helper->reorderCardConfiguration($cardConfig, $quoteItem);
    }

    /**
     * setNewOrderItemId
     *
     * @param $order
     */
    public function setNewOrderItemId($order)
    {
        $orderItems = $order->getAllItems();

        foreach ($orderItems as $orderItem) {
            $quoteItemId = $orderItem->getQuoteItemId();

            $configurations = Mage::getModel('magoffice_exaprint/cardconfig')->getCardConfigsQuote($quoteItemId);

            if ($configurations->count()) {
                foreach ($configurations as $configuration) {
                    $configuration->setOrderItemId($orderItem->getItemId());
                    $configuration->setItemOrderStatus(Magoffice_Exaprint_Helper_Data::ORDER_NEW);

                    $currentTimestamp = Mage::getModel('core/date')->timestamp(time());
                    $currentDate      = date('Y-m-d H:i:s', $currentTimestamp);

                    $configuration->setData('updated_at', $currentDate);

                    $configuration->save();
                }
            }
        }
    }

}
