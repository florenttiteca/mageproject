<?php

/**
 * Class Magoffice_Chili_Model_Observer
 *
 * @category     Magoffice
 * @package      Magoffice_Chili
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2017
 * @version      v1.0
 */
class Magoffice_Chili_Model_Observer extends Chili_Web2print_Model_Observer
{

    /**
     * Function setConfiguration
     *
     * @param $observer
     *
     * @throws Exception
     */
    public function setConfiguration($observer)
    {
        $chiliDocumentId = Mage::app()->getRequest()->getParam('chili_document_id');

        if ($chiliDocumentId) {
            $quoteItem = $observer->getQuoteItem();
            $quoteItem->save();
            $product = $quoteItem->getProduct();

            $exaRefId = Mage::app()->getRequest()->getParam('exa_ref');
            $exaQty   = Mage::app()->getRequest()->getParam('exa_qty');
            $chiliId  = Mage::getSingleton('customer/session')->getData('product_id');;

            Mage::helper('magoffice_exaprint')->addCardConfiguration(
                $chiliId, $chiliDocumentId, $exaRefId, $exaQty, $quoteItem
            );

            //Try to move the item to the quotes folder on CHILI
            try {
                $api = Mage::getModel('web2print/api');
                if ($api->isServiceAvailable()) {
                    $documentId = $api->moveResourceItem($chiliDocumentId, $product->getUrlKey());
                } else {
                    Mage::getSingleton('checkout/session')->addError('Service currently unavailable.');

                    return;
                }
            } catch (Exception $e) {
                Mage::getSingleton('checkout/session')
                    ->addError('Document path not properly configured in backend. Clear session after changes');
                throw new Exception($e->getMessage());
            }
        }
    }

    /**
     * Function setOrderItemId
     *
     * @param $observer
     */
    public function setOrderItemId($observer)
    {
        // Load the order and order items related to the event
        /** @var Itroom_Score_Model_Rewrite_Sales_Order $order */
        $order      = $observer->getOrder();
        $orderItems = $order->getAllItems();

        foreach ($orderItems as $orderItem) {
            $quoteItemId = $orderItem->getQuoteItemId();

            $configurations = Mage::getModel('magoffice_exaprint/cardconfig')->getCardConfigsQuote($quoteItemId);

            if ($configurations->count()) {
                foreach ($configurations as $configuration) {
                    $configuration->setOrderItemId($orderItem->getItemId());

                    if ($order->hasInvoices()
                        && $configuration->getData('item_order_status') == Magoffice_Exaprint_Helper_Data::ORDER_NEW
                        && $order->getPayment()->getMethodInstance()->getCode() == 'ops_cc'
                    ) {
                        $configuration->setItemOrderStatus(Magoffice_Exaprint_Helper_Data::ORDER_TODO);
                    }

                    $configuration->save();
                }
            }
        }
    }

    /**
     * Function convertQuoteItemToOrderItem
     *
     * @param $observer
     *
     * @return $this
     */
    public function convertQuoteItemToOrderItem($observer)
    {
        //Get order and quote item
        $orderItem      = $observer->getEvent()->getOrderItem();
        $item           = $observer->getEvent()->getItem();
        $configurations = Mage::getModel('magoffice_exaprint/cardconfig')->getCardConfigsQuote($item->getItemId());

        if ($configurations->count()) {
            foreach ($configurations as $configuration) {
                //Get document id from quote item
                $documentId = $configuration->getChiliDocumentId();
                if ($documentId) {
                    //Move document on CHILI server
                    Mage::getModel('web2print/api')
                        ->moveResourceItem($documentId, $item->getProduct()->getUrlKey(), 'Documents', 'order');
                }
            }
        }

        return $this;
    }

    /**
     * removeConfiguration
     *
     */
    public function removeConfiguration()
    {
        /** @var C2bi_Salesc2bi_Model_Quote $quote */
        $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();

        /** @var C2bi_Salesc2bi_Model_Quote_Item $quoteItem */
        foreach ($quote->getAllItems() as $quoteItem) {
            /** @var Magoffice_Exaprint_Model_Cardconfig $cardModel */
            $cardModel = Mage::getModel('magoffice_exaprint/cardconfig');
            $cardModel->removeItems($quoteItem->getData('item_id'));
        }
    }

    /**
     * Function categoryCrossing
     *
     * @param $observer
     */
    public function categoryCrossing($observer)
    {
        /** @var C2bi_Catalogc2bi_Model_Categoryc2bi $category */
        $category = $observer->getEvent()->getCategory();

        if ($category->getId()) {
            $children = $category->getChildrenCategories();

            if (count($children) == 1 && $category->getCustomDesign() == 'mag_office/chili') {
                $child = $children->getFirstItem();

                if ($child->getId()) {
                    Mage::app()->getResponse()->setRedirect(Mage::getBaseUrl() . $child->getRequestPath());
                }
            }
        }
    }

    /**
     * EVENT LISTENER
     *
     * When saving an order, create for each order item a pdf and save url
     *
     * @todo    activate this method again (system.xml) / changes have already been made
     */
    public function orderPlaceAfterCreatePdf($observer)
    {
        // Load the order and order items related to the event
        /** @var Itroom_Score_Model_Rewrite_Sales_Order $order */
        $order      = $observer->getOrder();
        $orderItems = $order->getAllItems();
        $website    = $order->getStore()->getWebsite();
        $storeId    = $order->getStore()->getId();

        /** @var Innoexts_Warehouse_Model_Sales_Order_Item $orderItem */
        foreach ($orderItems as $orderItem) {

            /** @var Magoffice_Exaprint_Model_Cardconfig $cardModel */
            $cardModel = Mage::getModel('magoffice_exaprint/cardconfig');

            /** @var Magoffice_Exaprint_Model_Cardconfig $configuration */
            $configuration = $cardModel->getCardConfigsQuoteItem($orderItem->getData('quote_item_id'));

            $documentId = $configuration->getData('chili_document_id');

            try {
                if (!empty($documentId)) {
                    //Frontend
                    /** @var Chili_Web2print_Model_Pdf $pdfFrontend */
                    $pdfFrontend = Mage::getModel('web2print/pdf')->getCollection()
                        ->addFieldToFilter('document_id', $documentId)
                        ->addFieldToFilter('export_type', 'frontend')
                        ->getFirstItem();
                    if (!$pdfFrontend) {
                        $pdfFrontend = Mage::getModel('web2print/pdf');
                    }

                    $pdfFrontend->setDocumentId($documentId);
                    $pdfFrontend->setOrderItemId($orderItem->getId());
                    $pdfFrontend->setOrderId($order->getId());
                    $pdfFrontend->setOrderIncrementId($order->getIncrementId());
                    $pdfFrontend->setCreatedAt(date("Y-m-d H:i:s"));
                    $pdfFrontend->setExportType('frontend');

                    if (Mage::helper('web2print')->isValidExportType($website, 'frontend')) {
                        $pdfFrontend->setStatus('queued');
                        $pdfFrontend->setExportProfile(
                            Mage::helper('web2print')
                                ->getPdfExportProfile(
                                    'frontend', $website,
                                    $orderItem->getProductId(), $storeId
                                )
                        );
                        $pdfFrontend->save();
                    } else {
                        $pdfFrontend->setStatus('no-pdf-export-settings-found');
                        $pdfFrontend->save();
                    }
                    $configuration->setData('pdf_preview_id', $pdfFrontend->getData('pdf_id'));

                    //Backend
                    $pdfBackend = Mage::getModel('web2print/pdf');
                    $pdfBackend->setDocumentId($documentId);
                    $pdfBackend->setOrderItemId($orderItem->getId());
                    $pdfBackend->setOrderId($order->getId());
                    $pdfBackend->setOrderIncrementId($order->getIncrementId());
                    $pdfBackend->setCreatedAt(date("Y-m-d H:i:s"));
                    $pdfBackend->setExportType('backend');

                    if (Mage::helper('web2print')->isValidExportType($website, 'backend')) {
                        $pdfBackend->setStatus('queued');
                        $pdfBackend->setExportProfile(
                            Mage::helper('web2print')
                                ->getPdfExportProfile(
                                    'backend', $website,
                                    $orderItem->getProductId(), $storeId
                                )
                        );
                        $pdfBackend->save();
                    } else {
                        $pdfBackend->setStatus('no-pdf-export-settings-found');
                        $pdfBackend->save();
                    }

                    $configuration->setData('pdf_final_id', $pdfBackend->getData('pdf_id'));
                    $configuration->save();
                }
            } catch (Exception $e) {
                Mage::log($e->getMessage());
            }
        }
    }
}
