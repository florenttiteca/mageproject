<?php

/**
 * Class Cgi_LengowSync_Model_Import
 *
 * @category     Cgi
 * @package      Cgi_LengowSync
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Cgi_LengowSync_Model_Import extends Lengow_Sync_Model_Import
{
    /**
     * Retrieve Lengow orders
     *
     * @return SimpleXmlElement list of orders to be imported
     */
    protected function getLengowOrders()
    {
        $isModeTest = Mage::getStoreConfig('lensync/test_mode_magoffice/mode_test');
        $urlToCall = Mage::getStoreConfig('lensync/test_mode_magoffice/url_call_import');

        if ($isModeTest) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, Mage::getBaseUrl() . $urlToCall);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            /** @var Cgi_LengowSync_Model_Importorders $logger */
            $logger = Mage::getSingleton('cgi_lensync/importorders');

            $logger->addLog(Cgi_LengowSync_Model_Importorders::LENGOWSYNC_LOG_TYPE_INFO, "Url called : " . $urlToCall);

            $urlTestOrders = curl_exec($curl);

            curl_close($curl);

            $orders = simplexml_load_file($urlTestOrders);
            return $orders;
        } else {
            return parent::getLengowOrders();
        }
    }

    /**
     * Create quote
     *
     * @param string $idLengowOrder
     * @param SimpleXMLelement $orderData
     * @param Lengow_Sync_Model_Customer_Customer $customer
     * @param Lengow_Sync_Model_Marketplace $marketplace
     *
     * @return Lengow_Sync_Model_Quote
     */
    protected function _createQuote($idLengowOrder, SimpleXMLelement $orderData,
                                    Lengow_Sync_Model_Customer_Customer $customer,
                                    Lengow_Sync_Model_Marketplace $marketplace)
    {
        $quote = Mage::getModel('lensync/quote')
                     ->setIsMultiShipping(false)
                     ->setStore($this->_config->getStore())
                     ->setIsSuperMode(true);

        $quote->setIsLengow(1);

        // import customer addresses into quote
        // Set billing Address
        $customerBillingAddress = Mage::getModel('customer/address')
                                      ->load($customer->getDefaultBilling());
        $billingAddress = Mage::getModel('sales/quote_address')
                              ->setShouldIgnoreValidation(true)
                              ->importCustomerAddress($customerBillingAddress)
                              ->setSaveInAddressBook(0);

        // Set shipping Address
        $customerShippingAddr = Mage::getModel('customer/address')
                                    ->load($customer->getDefaultShipping());
        $shippingAddress = Mage::getModel('sales/quote_address')
                               ->setShouldIgnoreValidation(true)
                               ->importCustomerAddress($customerShippingAddr)
                               ->setSaveInAddressBook(0)
                               ->setSameAsBilling(0);
        $quote->assignCustomerWithAddressChange($customer, $billingAddress, $shippingAddress);

        // check if store include tax (Product and shipping cost)
        $priceIncludeTax = Mage::helper('tax')->priceIncludesTax($quote->getStore());
        $shippingIncludeTax = Mage::helper('tax')->shippingPriceIncludesTax($quote->getStore());

        // add product in quote
        $quote->addLengowProducts($orderData->cart->products->product, $marketplace, $idLengowOrder,
            $priceIncludeTax);

        // get shipping cost with tax
        $shippingCost = (float)$orderData->order_processing_fee + (float)$orderData->order_shipping;

        // if shipping cost not include tax -> get shipping cost without tax
        if (!$shippingIncludeTax) {
            $basedOn = Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_BASED_ON, $quote->getStore());
            $countryId =
                ($basedOn == 'shipping') ? $shippingAddress->getCountryId() : $billingAddress->getCountryId();
            $shippingTaxClass =
                Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_SHIPPING_TAX_CLASS, $quote->getStore());
            $taxCalculator = Mage::getModel('tax/calculation');
            $taxRequest = new Varien_Object();
            $taxRequest->setCountryId($countryId)
                       ->setCustomerClassId($customer->getTaxClassId())
                       ->setProductClassId($shippingTaxClass);
            $taxRate = (float)$taxCalculator->getRate($taxRequest);
            $taxShippingCost = (float)$taxCalculator->calcTaxAmount($shippingCost, $taxRate, true);
            $shippingCost = $shippingCost - $taxShippingCost;
        }

        // get and update shipping rates for current order
        $rates = $quote->getShippingAddress()
                       ->setCollectShippingRates(true)
                       ->collectShippingRates()
                       ->getShippingRatesCollection();
        $shipping_method = $this->updateRates($rates, $idLengowOrder, $shippingCost);

        // set shipping price and shipping method for current order
        $quote->getShippingAddress()
              ->setShippingPrice($shippingCost)
              ->setShippingMethod($shipping_method);

        // collect totals
        $quote->collectTotals();

        // Re-ajuste cents for item quote
        // Conversion Tax Include > Tax Exclude > Tax Include maybe make 0.01 amount error
        if (!$priceIncludeTax) {
            if ($quote->getGrandTotal() != (float)$orderData->order_amount) {
                $quote_items = $quote->getAllItems();
                foreach ($quote_items as $item) {
                    $row_total_lengow = (float)$quote->getRowTotalLengow((string)$item->getProduct()->getId());
                    if ($row_total_lengow != $item->getRowTotalInclTax()) {
                        $diff = $row_total_lengow - $item->getRowTotalInclTax();
                        $item->setPriceInclTax($item->getPriceInclTax() + ($diff / $item->getQty()));
                        $item->setBasePriceInclTax($item->getPriceInclTax());
                        $item->setPrice($item->getPrice() + ($diff / $item->getQty()));
                        $item->setOriginalPrice($item->getPrice());
                        $item->setRowTotal($item->getRowTotal() + $diff);
                        $item->setBaseRowTotal($item->getRowTotal());
                        $item->setRowTotalInclTax((float)$row_total_lengow);
                        $item->setBaseRowTotalInclTax($item->getRowTotalInclTax());
                    }
                }
            }
        }

        // set payment method lengow
        $quote->getPayment()
              ->importData(
                  array(
                      'method'      => 'market_place',
                      'marketplace' => (string)$orderData->marketplace . ' - ' .
                                       (string)$orderData->order_payment->payment_type,
                  )
              );

        $quote->setIsWeb(1);

        return $quote;
    }

    /**
     * Makes the Orders API Url and imports all orders
     *
     * @param SimpleXmlElement $orders List of orders to be imported
     *
     * @return array Number of new and update orders
     */
    protected function importOrders($orders)
    {
        $countOrdersUpdated = 0;
        $countOrdersAdded = 0;

        foreach ($orders->orders->order as $key => $orderData) {
            $modelOrder = Mage::getModel('lensync/order');
            $modelOrder->setConfig($this->_config);

            $idLengowOrder = (string)$orderData->order_id;

            if ($this->_config->isDebugMode())
                $idLengowOrder .= '--' . time();

            // check if order has a status
            $marketplaceStatus = (string)$orderData->order_status->marketplace;
            if (empty($marketplaceStatus)) {
                $this->_helper->log('no order\'s status', $idLengowOrder);
                continue;
            }

            // first check if not shipped by marketplace
            if ((integer)$orderData->tracking_informations->tracking_deliveringByMarketPlace == 1) {
                $this->_helper->log('delivery by marketplace (' . (string)$orderData->marketplace . ')',
                    $idLengowOrder);
                continue;
            }

            // convert marketplace status to Lengow equivalent
            $marketplace = Mage::getModel('lensync/marketplace');
            $marketplace->set((string)$orderData->marketplace);
            $lengowStatus = $marketplace->getStateLengow($marketplaceStatus);

            // check if order has already been imported
            $idOrder = $modelOrder->isAlreadyImported($idLengowOrder, (integer)$orderData->idFlux);
            if ($idOrder) {
                $order_imported = Mage::getModel('sales/order')->load($idOrder);
                $this->_helper->log('already imported in Magento with order ID ' . $order_imported->getIncrementId(),
                    $idLengowOrder);
                if ($modelOrder->updateState($order_imported, $lengowStatus, $orderData))
                    $countOrdersUpdated++;
            } else {
                // Import only process order or shipped order and not imported with previous module
                $idOrder_magento = $this->_config->isDebugMode() ? null : (string)$orderData->order_external_id;
                if ($lengowStatus == 'processing' || $lengowStatus == 'shipped' && !$idOrder_magento) {

                    // Create or Update customer with addresses
                    $customer = Mage::getModel('lensync/customer_customer');
                    $customer->setFromNode($orderData, $this->_config);

                    // rewrite order if processing fees not included
                    if (!$this->_config->get('orders/processing_fee')) {
                        $totalWtProcFees =
                            (float)$orderData->order_amount - (float)$orderData->order_processing_fee;
                        $orderData->order_amount =
                            new SimpleXMLElement('<order_amount><![CDATA[' . ($totalWtProcFees) .
                                                 ']]></order_amount>');
                        $orderData->order_processing_fee =
                            new SimpleXMLElement('<order_processing_fee><![CDATA[ ]]></order_processing_fee>');
                        $this->_helper->log('rewrite amount without processing fee', $idLengowOrder);
                        unset($totalWtProcFees);
                    }

                    try {
                        $quote = $this->_createQuote($idLengowOrder, $orderData, $customer, $marketplace);
                    } catch (Exception $e) {
                        $this->_helper->log('create quote fail : ' . $e->getMessage(), $idLengowOrder);
                        continue;
                    }
                    try {
                        $order = $this->makeOrder($idLengowOrder, $orderData, $quote, $modelOrder, true);
                    } catch (Exception $e) {
                        $this->_helper->log('create order fail : ' . $e->getMessage(), $idLengowOrder);
                    }

                    if ($order) {
                        // Sync to lengow
                        $isModeTest = Mage::getStoreConfig('lensync/test_mode_magoffice/mode_test');

                        if (!$this->_config->isDebugMode() && !$isModeTest) {
                            $orders = $this->_connector->api('getInternalOrderId', array(
                                'idClient'           => (integer)$this->_idCustomer,
                                'idFlux'             => (integer)$orderData->idFlux,
                                'Marketplace'        => (string)$orderData->marketplace,
                                'idCommandeMP'       => $idLengowOrder,
                                'idCommandeMage'     => $order->getId(),
                                'statutCommandeMP'   => (string)$orderData->order_status->lengow,
                                'statutCommandeMage' => $order->getState(),
                                'idQuoteMage'        => $quote->getId(),
                                'Message'            => 'Import depuis: ' . (string)$orderData->marketplace .
                                                        '<br/>idOrder: ' . $idLengowOrder,
                                'type'               => 'Magento'
                            ));
                            $this->_helper->log('order successfully synchronised with Lengow webservice (Order ' .
                                                $order->getIncrementId() . ')', $idLengowOrder);
                        }
                        $countOrdersAdded++;
                        $this->_helper->log('order successfully imported (Order ' . $order->getIncrementId() . ')',
                            $idLengowOrder);
                        if ($lengowStatus == 'shipped') {
                            $modelOrder->toShip($order,
                                (string)$orderData->tracking_informations->tracking_carrier,
                                (string)$orderData->tracking_informations->tracking_method,
                                (string)$orderData->tracking_informations->tracking_number
                            );
                            $this->_helper->log('update state to "shipped" (Order ' . $order->getIncrementId() . ')',
                                $idLengowOrder);
                        }
                        unset($customer);
                        unset($quote);
                        unset($order);
                    }
                } else {
                    if ($idOrder_magento) {
                        $this->_helper->log('already imported in Magento with order ID ' . $idOrder_magento,
                            $idLengowOrder);
                    } else {
                        $this->_helper->log('order\'s status (' . $lengowStatus . ') not available to import',
                            $idLengowOrder);
                    }
                }
                unset($modelOrder);
            }
        }
        self::$import_start = false;
        // Clear session
        Mage::getSingleton('core/session')->clear();
        return array('new' => $countOrdersAdded, 'update' => $countOrdersUpdated);
    }
}