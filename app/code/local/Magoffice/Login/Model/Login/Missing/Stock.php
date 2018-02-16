<?php

/**
 * Class Magoffice_Login_Model_Login_Missing_Stock
 *
 * @category     Magoffice
 * @package      Magoffice_Login
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Login_Model_Login_Missing_Stock extends Mage_Core_Model_Abstract
{
    const LOGIN_LOG_FILE_NAME = 'missing_stock_service';
    const LOGIN_LOG_TYPE_INFO = '[INFO]';
    const LOGIN_LOG_TYPE_ERROR = '[ERROR]';
    const LOGIN_LOG_TYPE_WARNING = '[WARNING]';
    const LOGIN_LOG_TYPE_DEBUG = '[DEBUG]';
    const LOGIN_RESPONSE_TEMPLATE = '<response code="state">message</response>';
    const LOGIN_RESPONSE_OK = 'OK';
    const LOGIN_RESPONSE_KO = 'KO';
    const LOGIN_RUPTURE_STATE = 'OPEN';

    /**
     * Function call
     *
     * @param $user
     * @param $password
     * @param $query
     * @return mixed
     */
    public function call($user, $password, $query)
    {
        $this->_log($this::LOGIN_LOG_TYPE_INFO, 'Start integration');

        $result = $this->_integration($user, $password, $query);

        $this->_log($this::LOGIN_LOG_TYPE_INFO, 'End integration');

        return $result;
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
            file_put_contents($logFile, $this->_getLogTimestamp() . ' ' . $type . ' ' . $message . PHP_EOL,
                FILE_APPEND);
        }
    }

    /**
     * Function _getLogFileName
     *
     */
    protected function _getLogFileName()
    {
        return Mage::getBaseDir('log') . DS . $this::LOGIN_LOG_FILE_NAME . '_' . date('Ymd') . '.log';
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
     * Function _integration
     *
     * @param $user
     * @param $password
     * @param $query
     * @return mixed
     * @throws Exception
     */
    protected function _integration($user, $password, $query)
    {
        $message = '';
        $shipments = null;
        $state = self::LOGIN_RESPONSE_OK;

        $result = $this->_serviceAuthentification($user, $password);

        if ($result['error']) {
            return $result['response'];
        }

        $this->_log($this::LOGIN_LOG_TYPE_DEBUG, $query);

        if ($query) {
            $xmlQuery = simplexml_load_string(trim($query));
            if ($xmlQuery) {
                $shipments = $xmlQuery->{"shipment"};
            }
        }

        if (count($shipments)) {
            foreach ($shipments as $shipment) {
                $shipmentAttributes = $shipment->attributes();
                $invoiceId = $shipmentAttributes->{"invoice-id"}[0];

                if (!$invoiceId) {
                    $state = self::LOGIN_RESPONSE_KO;
                    $message = "Invoice ID is missing";
                    $this->_log($this::LOGIN_LOG_TYPE_ERROR, $message);
                    break;
                }

                /** @var Mage_Sales_Model_Order_Invoice $invoice */
                $invoice = Mage::getModel('sales/order_invoice')->loadByIncrementId($invoiceId);

                if (!$invoice->getId()) {
                    $state = self::LOGIN_RESPONSE_KO;
                    $message = "Invoice #$invoiceId is unknown";
                    $this->_log($this::LOGIN_LOG_TYPE_ERROR, $message);
                    break;
                }

                /** @var Mage_Sales_Model_Order $order */
                $order = $invoice->getOrder();

                if (!$order->getId()) {
                    $state = self::LOGIN_RESPONSE_KO;
                    $message = "Invoice #$invoiceId - No order found";
                    $this->_log($this::LOGIN_LOG_TYPE_ERROR, $message);
                    break;
                }

                $orderItems = $order->getAllItems();
                $shipmentItems = $shipment->{"item"};

                if (count($shipmentItems)) {
                    $date = Mage::getModel('core/date')->date('Y-m-d H:i:s');

                    foreach ($shipmentItems as $shipmentItem) {
                        $itemAttributes = $shipmentItem->attributes();
                        $itemRef = $itemAttributes->{"ref"}[0];

                        if (!$itemRef) {
                            $state = self::LOGIN_RESPONSE_KO;
                            $message = "#$invoiceId - Ref is missing";
                            $this->_log($this::LOGIN_LOG_TYPE_ERROR, $message);
                            break 2;
                        }

                        $itemFound = null;
                        foreach ($orderItems as $orderItem) {
                            if ($itemRef == $orderItem->getSku()) {
                                $itemFound = $orderItem;
                            }
                        }

                        if (!$itemFound || !$itemFound->getId()) {
                            $state = self::LOGIN_RESPONSE_KO;
                            $message = "#$invoiceId - $itemRef - Ref is unknown";
                            $this->_log($this::LOGIN_LOG_TYPE_ERROR, $message);
                            break 2;
                        }

                        $itemQtyRequest = $itemAttributes->{"qty-requested"}[0];
                        if (!$itemQtyRequest || $itemQtyRequest <= 0) {
                            $itemQtyRequest = number_format(1, 1);
                            $this->_log($this::LOGIN_LOG_TYPE_WARNING,
                                "#$invoiceId - $itemRef has an empty or negative requested quantity, force to 1");
                        }

                        $itemQtyMissing = $itemAttributes->{"qty-missing"}[0];
                        if (!$itemQtyMissing || $itemQtyMissing <= 0) {
                            $itemQtyMissing = number_format(1, 1);
                            $this->_log($this::LOGIN_LOG_TYPE_WARNING,
                                "#$invoiceId - $itemRef has an empty or negative missing quantity, force to 1");
                        }

                        if ($itemQtyRequest < $itemQtyMissing) {
                            $this->_log($this::LOGIN_LOG_TYPE_WARNING,
                                "#$invoiceId - $itemRef has an missing quantity upper than requested quantity");
                        }

                        $itemType = $itemAttributes->{"type"}[0];
                        if (!$itemType) {
                            $itemType = 'UNDEFINED';
                            $this->_log($this::LOGIN_LOG_TYPE_WARNING,
                                "#$invoiceId - $itemRef has an empty or missing type");
                        }

                        $itemDefinitive = $itemAttributes->{"definitive"}[0];
                        if (!$itemDefinitive) {
                            $itemDefinitive = 'false';
                        }

                        $itemFound->setMissingStockState(self::LOGIN_RUPTURE_STATE);
                        $itemFound->setMissingStockDate($date);
                        $itemFound->setMissingStockReason($itemType);
                        $itemFound->setMissingStockDefinitive($itemDefinitive);
                        $itemFound->setMissingStockQty($itemQtyMissing);
                        $itemFound->save();

                        $this->_log($this::LOGIN_LOG_TYPE_INFO, "#$invoiceId - $itemRef is missing $itemQtyMissing " .
                                                                "of $itemQtyRequest qty requested, type = $itemType");
                    }

                    if ($order->getMissingStockState() != self::LOGIN_RUPTURE_STATE) {
                        $order->setMissingStockState(self::LOGIN_RUPTURE_STATE);
                        $order->setMissingStockDate($date);
                        $order->save();
                    }
                } else {
                    $state = self::LOGIN_RESPONSE_KO;
                    $message = '#' . $invoiceId . ' - No item';
                    $this->_log($this::LOGIN_LOG_TYPE_ERROR, $message);
                    break;
                }

                unset($invoice);
                unset($order);
                unset($orderItems);
            }
        } else {
            $this->_log($this::LOGIN_LOG_TYPE_ERROR, 'GLOBAL - Missing main tag or no shipment in call');
            $state = self::LOGIN_RESPONSE_KO;
            $message = 'No shipments';
        }

        $this->_log($this::LOGIN_LOG_TYPE_INFO, 'Service response is ' . $state);

        $response = str_replace('message', $message, self::LOGIN_RESPONSE_TEMPLATE);
        $response = str_replace('state', $state, $response);

        return $response;
    }

    /**
     * Function _serviceAuthentification
     *
     * @param $user
     * @param $password
     * @return array
     */
    protected function _serviceAuthentification($user, $password)
    {
        $userConfig = Mage::getStoreConfig('magoffice_rupture_login/login_rupture_configuration/user_login_service');
        $passwordConfig =
            Mage::getStoreConfig('magoffice_rupture_login/login_rupture_configuration/password_login_service');

        if (!($user == $userConfig && $password == $passwordConfig)) {
            $this->_log($this::LOGIN_LOG_TYPE_ERROR, 'Authentification failed');
            $state = self::LOGIN_RESPONSE_KO;
            $message = 'authentification failed';

            $this->_log($this::LOGIN_LOG_TYPE_INFO, 'Service response is ' . $state);

            $response = str_replace('message', $message, self::LOGIN_RESPONSE_TEMPLATE);
            $response = str_replace('state', $state, $response);

            return array('error' => 1,
                         'response' => $response
            );
        }

        return array('error' => 0,
                     'response' => true
        );
    }
}
