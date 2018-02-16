<?php

/**
 * Class Magoffice_Sellsecure_Model_Scoring_Flow
 *
 * @category     Magoffice
 * @package      Magoffice_Sellsecure
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Sellsecure_Model_Scoring_Flow extends Mage_Core_Model_Abstract
{
    const SELLSECURE_LOG_FILE_NAME = 'sell_secure_scoring_flow';
    const SELLSECURE_LOG_TYPE_INFO = '[INFO]';
    const SELLSECURE_LOG_TYPE_ERROR = '[ERROR]';
    const SELLSECURE_LOG_TYPE_WARNING = '[WARNING]';
    const SELLSECURE_LOG_TYPE_DEBUG = '[DEBUG]';

    protected $_mode;
    protected $_needCall;

    protected $_scoringWsUrl;
    protected $_siteIdCmc;
    protected $_siteIdFac;

    protected $_productTypesMatrix;
    protected $_productTypesDefault;
    protected $_shippingCodeMatrix;
    protected $_paymentMethodMatrix;

    protected $_error;

    /**
     * Function _construct
     *
     */
    public function _construct()
    {
        $this->_mode     = Mage::getStoreConfig('magoffice_sellsecure/flow_url_mapping/execute_mode_activation');
        $this->_needCall = false;

        $this->_scoringWsUrl = Mage::getStoreConfig(
            'magoffice_sellsecure/flow_url_mapping/url_service_deposit_request_scoring'
        );
        $this->_siteIdCmc    = Mage::getStoreConfig('magoffice_sellsecure/login/sideidcmc');
        $this->_siteIdFac    = Mage::getStoreConfig('magoffice_sellsecure/login/siteidfac');

        $this->_scoringWsUrl = str_replace('{siteidcmc}', $this->_siteIdCmc, $this->_scoringWsUrl);

        $this->_productTypesMatrix = Mage::getStoreConfig('magoffice_sellsecure/flow_url_mapping/product_types_matrix');
        $this->_productTypesMatrix = json_decode($this->_productTypesMatrix, true);

        $this->_productTypesDefault = Mage::getStoreConfig('magoffice_sellsecure/flow_url_mapping/default_type');

        $this->_shippingCodeMatrix = Mage::getStoreConfig(
            'magoffice_sellsecure/flow_url_mapping/shipping_mode_code_matrix'
        );
        $this->_shippingCodeMatrix = json_decode($this->_shippingCodeMatrix, true);

        $this->_paymentMethodMatrix = Mage::getStoreConfig(
            'magoffice_sellsecure/flow_url_mapping/payment_method_code_matrix'
        );
        $this->_paymentMethodMatrix = json_decode($this->_paymentMethodMatrix, true);

    }

    /**
     * Function call
     *
     */
    public function call()
    {
        $this->_log($this::SELLSECURE_LOG_TYPE_INFO, 'Start sending Scoring Flow');

        $ordersSendCnt = $this->_scoringFlow();

        $this->_log($this::SELLSECURE_LOG_TYPE_INFO, "$ordersSendCnt order(s) will be send");

        $this->_log($this::SELLSECURE_LOG_TYPE_INFO, 'End sending Scoring Flow');
    }

    /**
     * Function _log
     *
     * @param $type
     * @param $message
     */
    protected function _log($type, $message)
    {
        $logFile = $this->_getLogFileName();

        if (!file_exists($logFile)) {
            file_put_contents($logFile, $this->_getLogTimestamp() . ' ' . $type . ' ' . $message . PHP_EOL);
            chmod($logFile, 0777);
        } else {
            file_put_contents(
                $logFile, $this->_getLogTimestamp() . ' ' . $type . ' ' . $message . PHP_EOL,
                FILE_APPEND
            );
        }
    }

    /**
     * Function _getLogFileName
     *
     */
    protected function _getLogFileName()
    {
        return Mage::getBaseDir('log') . DS . $this::SELLSECURE_LOG_FILE_NAME . '_' . date('Ymd') . '.log';
    }

    /**
     * Function _getLogTimestamp
     *
     * @return string
     */
    protected function _getLogTimestamp()
    {
        return date('Ymd') . ' / ' . date('His') . ' : ';
    }

    /**
     * Function _scoringFlow
     *
     * @return bool|int
     * @throws Exception
     */
    protected function _scoringFlow()
    {
        $ordersSendCnt   = 0;
        $this->_needCall = false;

        if (!$this->_mode) {
            $this->_log($this::SELLSECURE_LOG_TYPE_WARNING, 'Execution is off');

            return false;
        }

        if (!$this->_scoringWsUrl) {
            $this->_log($this::SELLSECURE_LOG_TYPE_ERROR, "The scoring request deposit service url is not configured");

            return false;
        }

        $date              = Mage::getModel('core/date')->date('Y-m-d H:i:s');
        $recoveryTimeLimit = Mage::getStoreConfig('magoffice_sellsecure/orders_eligibility/recovery_time_limit');
        $recoveryLimitDate = date("Y-m-d H:i:s", strtotime($date . "-$recoveryTimeLimit hours"));

        if (!$recoveryLimitDate) {
            $this->_log($this::SELLSECURE_LOG_TYPE_ERROR, 'An error occured when calculating the date');

            return false;
        }

        $forceRecoveryTimeLimit = Mage::getStoreConfig(
            'magoffice_sellsecure/orders_eligibility/force_recovery_time_limit'
        );
        $forceRecoveryLimitDate = date("Y-m-d H:i:s", strtotime($date . "-$forceRecoveryTimeLimit days"));

        /** @var Magoffice_Sellsecure_Model_Sell_Secure_Order $sellSecureOrderModel */
        $sellSecureOrderModel = Mage::getModel('magoffice_sellsecure/sell_secure_order');

        /** @var Magoffice_Sellsecure_Model_Mysql4_Sell_Secure_Order_Collection $sellSecureOrders */
        $sellSecureOrders = $sellSecureOrderModel
            ->getCollection()
            ->addFieldToFilter(
                'state',
                array(
                    array('null' => true),
                    array('eq' => Magoffice_Sellsecure_Helper_Data::SCORING_TODO),
                    array('eq' => Magoffice_Sellsecure_Helper_Data::SCORING_FORCED)
                )
            );

        $this->_log($this::SELLSECURE_LOG_TYPE_INFO, $sellSecureOrders->count() . " orders taken in account");

        /** @var Itroom_Score_Model_Rewrite_Sales_Order $orderModel */
        $orderModel = Mage::getModel('sales/order');
        if ($sellSecureOrders->count()) {
            $ordersLoopCnt = 0;
            /** @var SimpleXMLElement $xmlRequest */
            $xmlRequest = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"utf-8\" ?><stack></stack>");
            foreach ($sellSecureOrders as $sellSecureOrder) {
                $this->_error = false;
                $ordersLoopCnt++;
                $accept = true;

                if ($sellSecureOrder->getState() != Magoffice_Sellsecure_Helper_Data::SCORING_FORCED) {
                    //check délai de récupération
                    $creationDate = date("Y-m-d H:i:s", strtotime($sellSecureOrder->getCreatedAt()));
                    if ($creationDate < $recoveryLimitDate) {
                        $message = "Order #" . $sellSecureOrder->getIncrementId() .
                            " : Scoring request was not send after $recoveryTimeLimit hours.";

                        $this->_log($this::SELLSECURE_LOG_TYPE_WARNING, $message);

                        $sellSecureOrder->setState(Magoffice_Sellsecure_Helper_Data::SCORING_IGNORE);
                        $sellSecureOrder->setErrorMsg($message);
                        $sellSecureOrder->setUpdatedAt($date);
                        $sellSecureOrder->save();
                        $accept = false;
                    }
                } else {
                    //check délai de récupération for forced state
                    $creationDate = date("Y-m-d H:i:s", strtotime($sellSecureOrder->getCreatedAt()));
                    if ($creationDate < $forceRecoveryLimitDate) {
                        $message = "Order #" . $sellSecureOrder->getIncrementId() .
                            " : Scoring request was not send after $forceRecoveryTimeLimit" .
                            " days when state is forced.";

                        $this->_log($this::SELLSECURE_LOG_TYPE_WARNING, $message);

                        $sellSecureOrder->setState(Magoffice_Sellsecure_Helper_Data::SCORING_IGNORE);
                        $sellSecureOrder->setErrorMsg($message);
                        $sellSecureOrder->setUpdatedAt($date);
                        $sellSecureOrder->save();
                        $accept = false;
                    }
                }

                if ($accept) {
                    /** @var Itroom_Score_Model_Rewrite_Sales_Order $order */
                    $order = $orderModel->load($sellSecureOrder->getOrderId());

                    if (!$order) {
                        $message = "The order " . $sellSecureOrder->getIncrementId() . " does not exists";

                        $this->_log($this::SELLSECURE_LOG_TYPE_ERROR, $message);

                        $sellSecureOrder->setState(Magoffice_Sellsecure_Helper_Data::SCORING_ERROR);
                        $sellSecureOrder->setErrorMsg($message);
                        $sellSecureOrder->setUpdatedAt($date);
                        $sellSecureOrder->save();
                    } else {
                        $this->_addOrderToXml($xmlRequest, $order, $sellSecureOrder);
                        $order->reset();
                        unset($order);
                    }
                }

                if ((($ordersLoopCnt % 20) == 0) || ($ordersLoopCnt == $sellSecureOrders->count()) && !$this->_error) {
                    if ($this->_needCall) {
                        $result = $this->_callScoringWs($xmlRequest);

                        if ($result && count($result->result)) {
                            $ordersSendCnt = $this->_updateSellSecureOrders($result, $ordersSendCnt);
                        }
                        $xmlRequest      = new SimpleXMLElement(
                            "<?xml version=\"1.0\" encoding=\"utf-8\" ?><stack></stack>"
                        );
                        $this->_needCall = false;
                    }
                } else {
                    $this->_log($this::SELLSECURE_LOG_TYPE_INFO, "The order has not been sent to Sell Secure");
                }

            }
        }

        return $ordersSendCnt;
    }

    /**
     * Function _addOrderToXml
     *
     * @param $xmlRequest
     * @param $order
     * @param $sellSecureOrder
     */
    protected function _addOrderToXml($xmlRequest, $order, $sellSecureOrder)
    {
        $date = Mage::getModel('core/date')->date('Y-m-d H:i:s');

        $acceptedPaymentModes = explode(
            ',', Mage::getStoreConfig('magoffice_sellsecure/orders_eligibility/accepted_payment_modes')
        );
        $acceptedOrderStatus  = explode(
            ',', Mage::getStoreConfig('magoffice_sellsecure/orders_eligibility/accepted_order_states')
        );

        $payment       = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance()->getCode();

        $status = $order->getStatus();

        if (
            $sellSecureOrder->getState() != Magoffice_Sellsecure_Helper_Data::SCORING_FORCED
            && (!($status == "pending_payment" && $paymentMethod == "ops_cc"))
            && (!in_array($paymentMethod, $acceptedPaymentModes) || !in_array($status, $acceptedOrderStatus))
        ) {
            $message = "The order " . $sellSecureOrder->getIncrementId() .
                " is not accepted to Sell Secure evaluation: " .
                " payment method '$paymentMethod' or status '$status' not allowed";

            $this->_log($this::SELLSECURE_LOG_TYPE_WARNING, $message);

            $sellSecureOrder->setState(Magoffice_Sellsecure_Helper_Data::SCORING_IGNORE);
            $sellSecureOrder->setErrorMsg($message);
            $sellSecureOrder->setUpdatedAt($date);
            $sellSecureOrder->save();
        } else {
            $this->_log($this::SELLSECURE_LOG_TYPE_INFO, "Adding order : " . $order->getIncrementId() . ' to XML.');
            $this->_addOrderNode($xmlRequest, $order);
            $this->_needCall = true;
        }
    }

    /**
     * _addOrderNode
     *
     * @param SimpleXMLElement $xmlRequest
     * @param                  $order
     */
    protected function _addOrderNode(SimpleXMLElement $xmlRequest, $order)
    {
        /** @var Itroom_Score_Model_Rewrite_Sales_Order $order */
        $order = Mage::getModel('sales/order')->load($order->getEntityId());

        $shippingMethod = $order->getShippingMethod();

        if (array_key_exists($shippingMethod, $this->_shippingCodeMatrix)) {

            if (strpos($shippingMethod, 'mondialrelay') !== false) {
                $relayPoint = $order->getPointRelais();

                if (is_null($relayPoint)) {
                    $this->_log(
                        $this::SELLSECURE_LOG_TYPE_ERROR,
                        'Order_id #' . $order->getIncrementId() . ' : mondialrelay delivery, with relay lost'
                    );

                    /** @var Magoffice_Sellsecure_Model_Sell_Secure_Order $sellSecureOrderModel */
                    $sellSecureOrderModel = Mage::getModel('magoffice_sellsecure/sell_secure_order');

                    $stateResult = Magoffice_Sellsecure_Helper_Data::SCORING_ERROR;

                    $sendOrder = $sellSecureOrderModel->load($order->getIncrementId(), 'increment_id');
                    $sendOrder->setState($stateResult);

                    $date = Mage::getModel('core/date')->date('Y-m-d H:i:s');

                    $sendOrder->setUpdatedAt($date);
                    $sendOrder->save();

                    $order->addStatusHistoryComment(
                        'Sell Secure: erreur de scoring, la commande semble ne plus avoir de point relais,
                        malgré le mode de livraison "mondialrelay".'
                    );
                    $order->save();

                    return;
                }
            }
        }

        //control node for each order
        /** @var SimpleXMLElement $control */
        $control = $xmlRequest->addChild('control');

        //type_flux
        $control->addChild('type_flux', 'score');

        $customerId = $order->getCustomerId();

        /** @var C2bi_Customerc2bi_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer')->load($customerId);

        //user data
        $user = $control->addChild('utilisateur');
        $user->addAttribute('type', 'facturation');

        if ($customer->getGroupId() == 3) {
            $user->addAttribute('qualite', 1);
        } else {
            $user->addAttribute('qualite', 2);
        }


        $name = $user->addChild('nom', $customer->getLastname());
        $name->addAttribute('titre', strtolower($customer->getPrefix()));
        $user->addChild('email', $customer->getEmail());
        $user->addChild('prenom', $customer->getFirstname());
        $user->addChild('societe', $customer->getProRs());

        /** @var Innoexts_Warehouse_Model_Sales_Order_Address $billingAddress */
        $billingAddress = $order->getBillingAddress();

        $phone = $customer->getPhone();
        $user->addChild('telhome', $phone);

        $cellular = $customer->getCellular();

        if ($cellular) {
            $user->addChild('telmobile', $cellular);
        }

        //entity_id
        $user->addChild('idclient', $customer->getEntityId());

        //history orders
        /** @var Mage_Sales_Model_Resource_Order_Collection $historyOrders */
        $historyOrders = Mage::getModel('sales/order')->getCollection()
            ->addAttributeToFilter('customer_id', array('eq' => $customer->getEntityId()));

        if (count($historyOrders)) {
            $totalCa        = 0;
            $dateFirstOrder = null;
            $dateLastOrder  = null;

            foreach ($historyOrders as $historyOrder) {
                $createdOrderDate = $historyOrder->getCreatedAt();
                if (!$dateFirstOrder) {
                    $dateFirstOrder = $createdOrderDate;
                }

                if (!$dateLastOrder) {
                    $dateLastOrder = $createdOrderDate;
                }

                if ($createdOrderDate > $dateLastOrder) {
                    $dateLastOrder = $createdOrderDate;
                }

                if ($createdOrderDate < $dateFirstOrder) {
                    $dateFirstOrder = $createdOrderDate;
                }

                $totalCa += $historyOrder->getGrandTotal();
            }

            //if at least one order in the past
            $siteconso = $user->addChild('siteconso');
            $siteconso->addChild('nb', count($historyOrders));
            $siteconso->addChild('ca', $totalCa);
            //date premiere commande
            $siteconso->addChild('datepremcmd', $dateFirstOrder);
            //date derniere commande
            $siteconso->addChild('datederncmd', $dateLastOrder);
        }

        //billing address
        $xmlBillingAddress = $control->addChild('adresse');
        $xmlBillingAddress->addAttribute('type', 'facturation');
        $xmlBillingAddress->addAttribute('format', '1');
        $street = htmlspecialchars($billingAddress->getStreet1());
        $rue1 = $xmlBillingAddress->addChild('rue1', str_replace(CHR(13), ' ', $street));

        if ($billingAddress->getStreet2()) {
            $street = htmlspecialchars($billingAddress->getStreet2());
            $xmlBillingAddress->addChild('rue2', str_replace(CHR(13), ' ', $street));
        }

        if ($billingAddress->getStreet3()) {
            $street = htmlspecialchars($billingAddress->getStreet3());
            $xmlBillingAddress->addChild('rue3', str_replace(CHR(13), ' ', $street));
        }

        $xmlBillingAddress->addChild('cpostal', $billingAddress->getPostcode());
        $xmlBillingAddress->addChild('ville', $billingAddress->getCity());

        /** @var Mage_Directory_Model_Country $billingCountry */
        $billingCountry = Mage::getModel('directory/country')->load($billingAddress->getCountryId());
        $xmlBillingAddress->addChild('pays', $billingCountry->getName());

        $haveOther = false;
        $haveColop = false;
        $haveConf = false;

        $orderItems = $order->getAllVisibleItems();

        foreach ($orderItems as $item) {
            $product = Mage::getModel('catalog/product')->load($item->getProductId());
            $is_colop_product = Mage::helper('colop')->isColopProduct($product->getSku());
            $is_conf = $product->isConfigurable();
            if ($is_colop_product) {
                $haveColop = true;
            } elseif ($is_conf) {
                $haveConf = true;
            } else {
                $haveOther = true;
            }
        }

        //shippingAddress
        /** @var Innoexts_Warehouse_Model_Sales_Order_Address $shippingAddress */
        $shippingAddress = $order->getShippingAddress();

        if ($shippingAddress) {
            $shippingMethod = $order->getShippingMethod();

            if ($haveColop || $haveConf && $haveOther) { // is mixte
                //if shipping address != billing address
                if (($shippingAddress->getStreet1() != $billingAddress->getStreet1())
                    || ($shippingAddress->getPostcode() != $billingAddress->getPostcode())
                    || ($shippingAddress->getCity() != $billingAddress->getCity())
                ) {
                    $xmlShippingAddress = $control->addChild('adresse');
                    $xmlShippingAddress->addAttribute('type', 'livraison');
                    $xmlShippingAddress->addAttribute('format', '1');
                    $street = htmlspecialchars($shippingAddress->getStreet1());
                    $xmlShippingAddress->addChild('rue1', str_replace(CHR(13), ' ', $street));

                    if ($shippingAddress->getStreet2()) {
                        $street = htmlspecialchars($shippingAddress->getStreet2());
                        $xmlShippingAddress->addChild('rue2', str_replace(CHR(13), ' ', $street));
                    }

                    if ($shippingAddress->getStreet3()) {
                        $street = htmlspecialchars($shippingAddress->getStreet3());
                        $xmlShippingAddress->addChild('rue3', str_replace(CHR(13), ' ', $street));
                    }

                    $xmlShippingAddress->addChild('cpostal', $shippingAddress->getPostcode());
                    $xmlShippingAddress->addChild('ville', $shippingAddress->getCity());

                    /** @var Mage_Directory_Model_Country $shippingCountry */
                    $shippingCountry = Mage::getModel('directory/country')->load($shippingAddress->getCountryId());
                    $xmlShippingAddress->addChild('pays', $shippingCountry->getName());
                }
            } else {
                //LdfAddress
                /** @var C2bi_Customerc2bi_Model_Address $ldfAddress */
                $ldfAddress = $order->getLdfAddress();
                if ($ldfAddress) {
                    //if LDF shipping address != shipping address
                    if (($ldfAddress->getStreet1() != $shippingAddress->getStreet1())
                        || ($ldfAddress->getPostcode() != $shippingAddress->getPostcode())
                        || ($ldfAddress->getCity() != $shippingAddress->getCity())
                    ) {
                        $xmlLdfAddress = $control->addChild('adresse');
                        $xmlLdfAddress->addAttribute('type', 'livraison');
                        $xmlLdfAddress->addAttribute('format', '1');
                        $street = htmlspecialchars($ldfAddress->getStreet1());
                        $xmlLdfAddress->addChild('rue1', str_replace(CHR(13), ' ', $street));

                        if ($ldfAddress->getStreet2()) {
                            $street = htmlspecialchars($ldfAddress->getStreet2());
                            $xmlLdfAddress->addChild('rue2', str_replace(CHR(13), ' ', $street));
                        }

                        if ($ldfAddress->getStreet3()) {
                            $street = htmlspecialchars($ldfAddress->getStreet3());
                            $xmlLdfAddress->addChild('rue3', str_replace(CHR(13), ' ', $street));
                        }

                        $xmlLdfAddress->addChild('cpostal', $ldfAddress->getPostcode());
                        $xmlLdfAddress->addChild('ville', $ldfAddress->getCity());

                        /** @var Mage_Directory_Model_Country $ldfCountry */
                        $ldfCountry = Mage::getModel('directory/country')->load($ldfAddress->getCountryId());
                        $xmlLdfAddress->addChild('pays', $ldfCountry->getName());
                    }else{
                        $xmlLdfAddress = $control->addChild('adresse');
                        $xmlLdfAddress->addAttribute('type', 'livraison');
                        $xmlLdfAddress->addAttribute('format', '1');
                        $street = htmlspecialchars($shippingAddress->getStreet1());
                        $xmlLdfAddress->addChild('rue1', str_replace(CHR(13), ' ', $street));

                        if ($ldfAddress->getStreet2()) {
                            $street = htmlspecialchars($shippingAddress->getStreet2());
                            $xmlLdfAddress->addChild('rue2', str_replace(CHR(13), ' ', $street));
                        }

                        if ($ldfAddress->getStreet3()) {
                            $street = htmlspecialchars($shippingAddress->getStreet3());
                            $xmlLdfAddress->addChild('rue3', str_replace(CHR(13), ' ', $street));
                        }

                        $xmlLdfAddress->addChild('cpostal', $shippingAddress->getPostcode());
                        $xmlLdfAddress->addChild('ville', $shippingAddress->getCity());

                        /** @var Mage_Directory_Model_Country $ldfCountry */
                        $ldfCountry = Mage::getModel('directory/country')->load($shippingAddress->getCountryId());
                        $xmlLdfAddress->addChild('pays', $ldfCountry->getName());
                    }
                } elseif (array_key_exists($shippingMethod, $this->_shippingCodeMatrix)) {
                    $method = $this->_shippingCodeMatrix[$shippingMethod];

                    $transport = $control->addChild('transport');
                    $transport->addChild('rapidite', $method['rapidite']);
                    $transport->addChild('type', $method['type']);
                    $transport->addChild('nom', $method['code']);

                    //add relay code if mondial relay
                    if (strpos($shippingMethod, 'mondialrelay') !== false) {
                        $relayPoint    = $order->getPointRelais();
                        $relayPointXml = $transport->addChild('pointrelais');
                        $relayPointXml->addChild('identifiant', $relayPoint->getNum());
                        $relayPointXml->addChild('enseigne', htmlspecialchars($relayPoint->getLgAdr1()));
                        $relayAdressXml = $relayPointXml->addChild('adresse');

                        if ($relayPoint->getLgAdr3()) {
                            $relayAdressXml->addChild('rue1', htmlspecialchars($relayPoint->getLgAdr3()));
                        }

                        if ($relayPoint->getLgAdr2()) {
                            $relayAdressXml->addChild('rue2', htmlspecialchars($relayPoint->getLgAdr2()));
                        }

                        $relayAdressXml->addChild('cpostal', $relayPoint->getCP());
                        $relayAdressXml->addChild('ville', $relayPoint->getVille());

                        /** @var Mage_Directory_Model_Country $relayCountry */
                        $relayCountry = Mage::getModel('directory/country')->load($relayPoint->getPays());
                        $relayAdressXml->addChild('pays', $relayCountry->getName());
                    }
                } else {
                    //if shipping address != billing address
                    if (($shippingAddress->getStreet1() != $billingAddress->getStreet1())
                        || ($shippingAddress->getPostcode() != $billingAddress->getPostcode())
                        || ($shippingAddress->getCity() != $billingAddress->getCity())
                    ) {
                        $xmlShippingAddress = $control->addChild('adresse');
                        $xmlShippingAddress->addAttribute('type', 'livraison');
                        $xmlShippingAddress->addAttribute('format', '1');

                        $street = htmlspecialchars($shippingAddress->getStreet1());
                        $xmlShippingAddress->addChild('rue1', str_replace(CHR(13), ' ', $street));

                        if ($shippingAddress->getStreet2()) {
                            $street = htmlspecialchars($shippingAddress->getStreet2());
                            $xmlShippingAddress->addChild('rue2', str_replace(CHR(13), ' ', $street));
                        }

                        if ($shippingAddress->getStreet3()) {
                            $street = htmlspecialchars($shippingAddress->getStreet3());
                            $xmlShippingAddress->addChild('rue3', str_replace(CHR(13), ' ', $street));
                        }

                        $xmlShippingAddress->addChild('cpostal', $shippingAddress->getPostcode());
                        $xmlShippingAddress->addChild('ville', $shippingAddress->getCity());

                        /** @var Mage_Directory_Model_Country $shippingCountry */
                        $shippingCountry = Mage::getModel('directory/country')->load($shippingAddress->getCountryId());
                        $xmlShippingAddress->addChild('pays', $shippingCountry->getName());
                    }
                    $this->_log(
                        $this::SELLSECURE_LOG_TYPE_WARNING,
                        "The shipping method $shippingMethod does not match with the shipping matrix configuration"
                    );
                }
            }
        }


        //infocommande
        $infocommande = $control->addChild('infocommande');
        $infocommande->addChild('saisiecommande', '1');
        $montant = $infocommande->addChild('montant', $order->getGrandTotal());
        $montant->addAttribute('devise', $order->getStoreCurrencyCode());
        $infocommande->addChild('siteid', $this->_siteIdCmc);
        $infocommande->addChild('refid', $order->getIncrementId());

        $customerIp = $order->getXForwardedFor();
        if ($customerIp) {
            $ipaddress = $infocommande->addChild('ip', $customerIp);
            $ipaddress->addAttribute('timestamp', $order->getCreatedAt());
        } else {
            $this->_error = true;
            $this->_log($this::SELLSECURE_LOG_TYPE_ERROR, "the IP field is empty");
        }

        $payment       = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance()->getCode();

        if (array_key_exists($shippingMethod, $this->_shippingCodeMatrix)) {
            $method = $this->_shippingCodeMatrix[$shippingMethod];

            $transport = $infocommande->addChild('transport');
            $transport->addChild('rapidite', $method['rapidite']);
            $transport->addChild('type', $method['type']);
            $transport->addChild('nom', $method['code']);

            //add relay code if mondial relay
            if (strpos($shippingMethod, 'mondialrelay') !== false) {
                $relayPoint    = $order->getPointRelais();
                $relayPointXml = $transport->addChild('pointrelais');
                $relayPointXml->addChild('identifiant', $relayPoint->getNum());
                $relayPointXml->addChild('enseigne', htmlspecialchars($relayPoint->getLgAdr1()));
                $relayAdressXml = $relayPointXml->addChild('adresse');

                if ($relayPoint->getLgAdr3()) {
                    $relayAdressXml->addChild('rue1', htmlspecialchars($relayPoint->getLgAdr3()));
                }

                if ($relayPoint->getLgAdr2()) {
                    $relayAdressXml->addChild('rue2', htmlspecialchars($relayPoint->getLgAdr2()));
                }

                $relayAdressXml->addChild('cpostal', $relayPoint->getCP());
                $relayAdressXml->addChild('ville', $relayPoint->getVille());

                /** @var Mage_Directory_Model_Country $relayCountry */
                $relayCountry = Mage::getModel('directory/country')->load($relayPoint->getPays());
                $relayAdressXml->addChild('pays', $relayCountry->getName());
            }
        } else {
            $this->_log(
                $this::SELLSECURE_LOG_TYPE_WARNING,
                "The shipping method $shippingMethod does not match with the shipping matrix configuration"
            );
            $this->_error = true;
        }
        $items = $order->getAllItems();
        if (count($items)) {
            $list = $infocommande->addChild('list');
            $list->addAttribute('nbproduit', count($items));

            foreach ($items as $item) {
                /** @var Innoexts_Warehouse_Model_Sales_Order_Item $item */

                /** @var C2bi_Catalogc2bi_Model_Productc2bi $product */
                $product    = $item->getProduct();
                $xmlProduct = $list->addChild('produit', htmlspecialchars($product->getName()));
                $xmlProduct->addAttribute('ref', $product->getSku());

                $categoryIds = $product->getCategoryIds();
                if (count($categoryIds)) {
                    $lastCategoryId = end($categoryIds);
                    $category       = Mage::getModel('catalog/category')->load($lastCategoryId);
                    $sellSecureType = $category->getSellSecureType();

                    if (array_key_exists($sellSecureType, $this->_productTypesMatrix)) {
                        $sellSecureLabelType = $this->_productTypesMatrix[$sellSecureType];
                        $xmlProduct->addAttribute('type', $sellSecureLabelType);
                    } else {
                        $xmlProduct->addAttribute('type', $this->_productTypesDefault);
                    }
                }

                $xmlProduct->addAttribute('nb', intval($item->getQtyOrdered()));
                $prixunit = $item->getPriceInclTax();

                if ($prixunit) {
                    $xmlProduct->addAttribute('prixunit', $item->getPriceInclTax());
                } else {
                    $this->_error = true;
                    $this->_log($this::SELLSECURE_LOG_TYPE_ERROR, "the prixunit field is empty");
                }
            }
        }

        if (array_key_exists($paymentMethod, $this->_paymentMethodMatrix)) {
            $paymentMapping = $this->_paymentMethodMatrix[$paymentMethod];

            //paiement
            $paiement = $control->addChild('paiement');
            $paiement->addChild('type', $paymentMapping);
        }

        //extradata
        $extradata = $control->addChild('extradata');

        $couponCodes = $order->getCouponCode();
        $extradata->addChild('code_promo', $couponCodes);

        if ($customer->getProNumcarte()) {
            $extradata->addChild('carte_fid', 'true');
        }

        if ($customer->getNlMagoffice()) {
            $extradata->addChild('abo_newsletter', 'true');
        } else {
            $extradata->addChild('abo_newsletter', 'false');
        }
        $order->reset();
        unset($order);
    }

    /**
     * Function _callScoringWs
     *
     * @param $xmlRequest
     *
     * @return mixed|null|SimpleXMLElement
     */
    protected function _callScoringWs($xmlRequest)
    {
        $this->_log($this::SELLSECURE_LOG_TYPE_INFO, $xmlRequest->asXML());

        $fields = array(
            'siteidfac'       => $this->_siteIdFac,
            'siteidcmc'       => $this->_siteIdCmc,
            'controlcallback' => $xmlRequest->asXML()
        );

        $result = null;
        try {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $this->_scoringWsUrl);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($fields));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($curl);
            $result = simplexml_load_string($result);

            curl_close($curl);
        } catch (Exception $exception) {
            $this->_log($this::SELLSECURE_LOG_TYPE_ERROR, $exception->getMessage());
        }

        if (!$result) {
            $this->_log($this::SELLSECURE_LOG_TYPE_ERROR, "Service is not available");
        } else {
            if (count($result->result)) {
                $this->_log($this::SELLSECURE_LOG_TYPE_INFO, "Response: " . $result->asXML());
            } else {
                $this->_log($this::SELLSECURE_LOG_TYPE_ERROR, $result->asXML());
            }
        }

        return $result;
    }

    /**
     * Function _updateSellSecureOrders
     *
     * @param $result
     * @param $ordersSendCnt
     *
     * @return mixed
     * @throws Exception
     */
    protected function _updateSellSecureOrders($result, $ordersSendCnt)
    {
        $date = Mage::getModel('core/date')->date('Y-m-d H:i:s');

        /** @var Magoffice_Sellsecure_Model_Sell_Secure_Order $sellSecureOrderModel */
        $sellSecureOrderModel = Mage::getModel('magoffice_sellsecure/sell_secure_order');

        /** @var Itroom_Score_Model_Rewrite_Sales_Order $orderModel */
        $orderModel = Mage::getModel('sales/order');

        foreach ($result->result as $res) {
            if (
                strtoupper($res{'avancement'}) == 'ERROR' || strtoupper($res{'avancement'}) == 'KO' || $res->detail
            ) {
                $stateResult = Magoffice_Sellsecure_Helper_Data::SCORING_ERROR;
                $error       = (string)$res->detail;
            } else {
                $stateResult = Magoffice_Sellsecure_Helper_Data::SCORING_WAITING_EVAL;
                $error       = null;
            }

            $sendOrder = $sellSecureOrderModel->load((string)$res['refid'], 'increment_id');
            $sendOrder->setState($stateResult);
            $sendOrder->setCallCount($sendOrder->getCallCount() + 1);

            if ($stateResult == Magoffice_Sellsecure_Helper_Data::SCORING_WAITING_EVAL) {
                $sendOrder->setErrorMsg(null);
                $this->_log(
                    $this::SELLSECURE_LOG_TYPE_INFO,
                    "Order #" . $sendOrder->getIncrementId() . " : Scoring request success"
                );

                $order = $orderModel->load((string)$res['refid'], 'increment_id');
                $order->addStatusHistoryComment("[Sell Secure] La demande d’évaluation est réalisée.");
                $order->save();

                $ordersSendCnt++;
            } else {
                $this->_log(
                    $this::SELLSECURE_LOG_TYPE_WARNING,
                    "Order #" . $sendOrder->getIncrementId() . " : Scoring request was not send: " .
                    $error
                );
                $sendOrder->setErrorMsg($error);
            }

            $sendOrder->setUpdatedAt($date);
            $sendOrder->save();
        }

        return $ordersSendCnt;
    }
}
