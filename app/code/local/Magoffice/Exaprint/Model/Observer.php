<?php

/**
 * Class Magoffice_Exaprint_Model_Observer
 *
 * @category     Magoffice
 * @package      Magoffice_Exaprint
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Exaprint_Model_Observer extends Mage_Core_Model_Abstract
{
    /**
     * Function updateSupplierOrderAfterInvoice
     *
     * @param Varien_Event_Observer $observer
     *
     * @return bool
     */
    public function updateSupplierOrderAfterInvoice(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Order_Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();

        if (!$invoice) {
            return false;
        }

        $orderModel = Mage::getModel('sales/order');
        /** @var Itroom_Score_Model_Rewrite_Sales_Order $order */
        $order = $orderModel->load($invoice->getOrderId());

        if (!$order) {
            return false;
        }

        // Drop shipment projet : create shipment if order is LDF (not Colop or not Exaprint)
        if ($order->getPayment()->getMethodInstance()->getCode() != 'ops_cc' && $order->canShip()) {
            $this->_shipOrder($order);
        }

        /** @var Magoffice_Exaprint_Model_Cardconfig $cardConfigModel */
        $cardConfigModel = Mage::getModel('magoffice_exaprint/cardconfig');

        $currentTimestamp = Mage::getModel('core/date')->timestamp(time());
        $currentDate      = date('Y-m-d H:i:s', $currentTimestamp);

        $orderItems = $order->getAllItems();
        if (count($orderItems)) {
            foreach ($orderItems as $item) {
                $configs = $cardConfigModel->getCardConfigsOrder($item->getItemId());
                if ($configs->count()) {
                    /** @var Magoffice_Exaprint_Model_Cardconfig $config */
                    foreach ($configs as $config) {
                        if ($config->getData('item_order_status') == Magoffice_Exaprint_Helper_Data::ORDER_NEW) {
                            $config->setItemOrderStatus(Magoffice_Exaprint_Helper_Data::ORDER_TODO)
                                ->setUpdatedAt($currentDate)
                                ->save();

                            $order->addStatusHistoryComment(
                                '[Exaprint] Evènement de facturation: toutes les configurations Exaprint sont autorisées pour la production.'
                            );
                            $order->save();
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * _shipOrder
     *
     * @param $order
     *
     */
    protected function _shipOrder($order)
    {
        /** @var Itroom_Score_Model_Rewrite_Sales_Order $order */
        $items = $order->getAllItems();

        $qty              = array();
        $hasExcluded      = false;
        $excludedProducts = array();

        /** @var Innoexts_Warehouse_Model_Sales_Order_Item $item */
        foreach ($items as $item) {
            $isLdf              = false;
            $isColop            = false;
            $isExaprint         = false;
            $isNegStockAccepted = true;

            $isLdf = $item->getProduct()->getData('is_ldf');

            /** @var C2bi_Catalogc2bi_Helper_Productc2bi $productHelper */
            $productHelper = Mage::helper('catalogc2bi/productc2bi');
            $isColop       = $productHelper->isProductCustomDesignColop($item->getProduct()->getId());

            /** @var Magoffice_Exaprint_Model_Cardconfig $exaprintModel */
            $exaprintModel = Mage::getModel('magoffice_exaprint/cardconfig');
            $isExaprint    = $exaprintModel->getCardConfigsOrderItemsCount($item->getId());

            /** @var Innoexts_Warehouse_Model_Cataloginventory_Stock_Item $inventory */
            $inventory = Mage::getModel('cataloginventory/stock_item')
                ->getCollection()
                ->addProductsFilter(array($item->getProduct()->getId()))
                ->addStockFilter(1)
                ->getFirstItem();

            if ($inventory->getBackorders() == 0) {
                $isNegStockAccepted = false;
            }

            if ($isLdf && !$isColop && !$isExaprint) {
                /** @var C2bi_Catalogc2bi_Model_Productc2bi $product */
                $product = Mage::getModel('catalog/product')->load($item->getData('product_id'));

                //  If product has a supplier code not authorized, item is not shipped
                $supplierCode       = $product->getData('code_fournisseur');
                $availableSuppliers = explode(';', Mage::getStoreConfig('sales/traitement/trt_mapping_included'));

                if (!in_array($supplierCode, $availableSuppliers)) {
                    $excludedProducts[] = $product->getSku();
                    $hasExcluded        = true;
                    continue;
                }

                if (!$isNegStockAccepted) {
                    $itemQty             = 0;
                    $itemQty             = $item->getQtyOrdered()
                        - $item->getQtyShipped()
                        - $item->getQtyRefunded()
                        - $item->getQtyCanceled();

                    if ($itemQty > 0) {
                        $qty[$item->getId()] = $itemQty;
                    }

                    $stockQty = $inventory->getData('qty');
                    $limitQty = $inventory->getData('min_qty');

                    if (!($stockQty > 0 && $stockQty - $itemQty >= $limitQty)) {
                        $order->addStatusHistoryComment(
                            "Pas de stock chez le fournisseur lors de la tentative d'expédition"
                        );
                        $order->setTraitement("A_traiter");
                        $order->save();

                        return false;
                    }
                } else {
                    $itemQty = 0;
                    $itemQty = $item->getQtyOrdered()
                        - $item->getQtyShipped()
                        - $item->getQtyRefunded()
                        - $item->getQtyCanceled();

                    if ($itemQty > 0) {
                        $qty[$item->getId()] = $itemQty;
                    }
                }
            }
        }

        if (!empty($qty)) {
            /** @var C2bi_Salesc2bi_Model_Order_Shipment $shipment */
            $shipment = $order->prepareShipment($qty);

            if ($shipment) {
                $shipment->register();

                $order->setIsInProcess(true);
                /** @var Mage_Core_Model_Resource_Transaction $transactionSave */
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();

                $shipment->setData('to_export', 2);
                $shipment->save();
            }

            $order->addStatusHistoryComment('Expédition LDF créée à destination de Générix.');

            $order->save();
        }

        if ($hasExcluded) {
            //$order->setTraitement('Drop Gautier');
            $order->setData('traitement', 'A_traiter_manuellement');
            $order->save();

            $skuList = implode(',', $excludedProducts);
            $order->addStatusHistoryComment(
                'Code fournisseur non autorisé pour le/les produit(s) suivants : ' . $skuList
            );
            $order->save();
        }
    }

    /**
     * Function deleteItemConfig
     *
     * @param Varien_Event_Observer $observer
     */
    public function deleteItemConfig(Varien_Event_Observer $observer)
    {
        $item   = $observer->getItem();
        $itemId = $item->getItemId();

        if ($itemId) {
            /** @var Magoffice_Exaprint_Model_Cardconfig $cardconfig */
            $cardconfig = Mage::getModel('magoffice_exaprint/cardconfig');
            $cardconfig->removeItems($itemId);
        }
    }

    /**
     * mergeQuoteConfigurations
     *
     * @param Varien_Event_Observer $observer
     *
     * @throws Exception
     */
    public function mergeQuoteConfigurations(Varien_Event_Observer $observer)
    {
        /** @var C2bi_Salesc2bi_Model_Quote $quote */
        $quote = $observer->getData('quote');

        foreach ($quote->getAllItems() as $item) {
            if ($item->getData('conf_id')) {
                /** @var Magoffice_Exaprint_Model_Cardconfig $cardconfig */
                $cardconfig = Mage::getModel('magoffice_exaprint/cardconfig')->load($item->getData('conf_id'));
                $cardconfig->setData('quote_item_id', $item->getData('item_id'));
                $cardconfig->save();
            }
        }
    }
}
