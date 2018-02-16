<?php

/**
 * Class Cgi_LengowSync_Model_Update_Status
 *
 * @category     Cgi
 * @package      Cgi_LengowSync
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Cgi_LengowSync_Model_Update_Status extends Mage_Core_Model_Abstract
{
    const LENGOWSYNC_LOG_FILE_NAME = 'lengow_update_status';
    const LENGOWSYNC_LOG_TYPE_INFO = '[INFO]';
    const LENGOWSYNC_LOG_TYPE_ERROR = '[ERROR]';
    const LENGOWSYNC_LOG_TYPE_WARNING = '[WARNING]';
    const LENGOWSYNC_LOG_TYPE_DEBUG = '[DEBUG]';

    /**
     * Function call
     *
     */
    public function call()
    {
        $this->_log($this::LENGOWSYNC_LOG_TYPE_INFO, 'Start updating order’s status in Lengow.');

        $calls = $this->_launchStatusUpdate();

        $this->_log($this::LENGOWSYNC_LOG_TYPE_INFO, $calls['nb_ok'] . ' calls OK.');
        $this->_log($this::LENGOWSYNC_LOG_TYPE_INFO, $calls['nb_ko'] . ' calls KO.');

        $this->_log($this::LENGOWSYNC_LOG_TYPE_INFO, 'End updating order’s status in Lengow.');
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
        return Mage::getBaseDir('log') . DS . $this::LENGOWSYNC_LOG_FILE_NAME . '_' . date('Ymd') . '.log';
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
     * Function _launchStatusUpdate
     *
     * @return array
     */
    protected function _launchStatusUpdate()
    {
        $countOk = 0;
        $countKo = 0;

        /** @var $callsCollection Cgi_LengowSync_Model_Mysql4_Orders_Collection */
        $callsCollection = Mage::getModel('cgi_lensync/orders')
                               ->getCollection()
                               ->addFieldToFilter('state', array('in' => array('TODO', 'FAILURE')));

        $this->_log($this::LENGOWSYNC_LOG_TYPE_INFO, 'There is ' . $callsCollection->count() . ' calls to do');

        $configMarketJson = Mage::getStoreConfig('lensync/config_trade_ws_market_place/textarea_json');
        $configMarketJson = json_decode($configMarketJson, true);

        if (!$configMarketJson) {
            $this->_log($this::LENGOWSYNC_LOG_TYPE_ERROR, 'Invalid WS JSON format configuration');
            $countKo = $callsCollection->count();
        }

        if ($configMarketJson && $callsCollection->count()) {
            /** @var Cgi_LengowSync_Model_Orders $data */
            foreach ($callsCollection as $data) {
                $orderId = $data->getData('order_id');
                $mpNumber = $data->getData('mp_id');
                $wsAction = $data->getData('ws');
                $order = Mage::getModel('sales/order')->load($orderId);
                $marketPlace = $order->getData('marketplace_lengow');
                $feedId = $order->getData('feed_id_lengow');
                $error = null;
                $urlToCall = null;

                foreach ($configMarketJson as $market) {
                    if ($market['name'] === $marketPlace) {
                        $urlToCall = $market['ws' . ucfirst($wsAction)];
                    }
                }

                if (!$urlToCall) {
                    $error = 'No matching marketplace configured for "' . $marketPlace . '"';
                    $this->_log($this::LENGOWSYNC_LOG_TYPE_ERROR, $error);

                    $data->setData('state', 'FAILURE')
                         ->setData('error_msg', $error)
                         ->setData('updated_at', Mage::getModel('core/date')->date())
                         ->setData('url', $urlToCall)
                         ->save();
                    $countKo++;
                    continue;
                }

                if (!strpos($urlToCall, '${MARKET_PLACE}') || !strpos($urlToCall, '${FEED_ID}') ||
                    !strpos($urlToCall, '${ORDER_MP_NUMBER}')
                ) {
                    $error = 'Required params in url configuration are missing for "' . $marketPlace .
                             '". Check if url configuration contains ' .
                             '${MARKET_PLACE}, ${FEED_ID} and ${ORDER_MP_NUMBER}';
                    $this->_log($this::LENGOWSYNC_LOG_TYPE_ERROR, $error);

                    $data->setData('state', 'FAILURE')
                         ->setData('error_msg', $error)
                         ->setData('updated_at', Mage::getModel('core/date')->date())
                         ->setData('url', $urlToCall)
                         ->save();
                    $countKo++;
                    continue;
                }

                $urlToCall = str_replace('${MARKET_PLACE}', $marketPlace, $urlToCall);
                $urlToCall = str_replace('${FEED_ID}', $feedId, $urlToCall);
                $urlToCall = str_replace('${ORDER_MP_NUMBER}', $mpNumber, $urlToCall);

                if ($wsAction == 'acceptOrder') {
                    $shipmentCollection = Mage::getResourceModel('sales/order_shipment_collection')
                                              ->setOrderFilter($order)
                                              ->load();

                    $carrierMpCode = null;
                    $trackNumber = null;

                    if (!strpos($urlToCall, '${CARRIER_CODE}') || !strpos($urlToCall, '${TRACKING_NUMBER}')
                    ) {
                        $error = 'Required params in url configuration are missing for "' . $marketPlace .
                                 '". Check if url configuration contains ${MARKET_PLACE} and ${TRACKING_NUMBER}';
                        $this->_log($this::LENGOWSYNC_LOG_TYPE_ERROR, $error);

                        $data->setData('state', 'FAILURE')
                             ->setData('error_msg', $error)
                             ->setData('updated_at', Mage::getModel('core/date')->date())
                             ->setData('url', $urlToCall)
                             ->save();
                        $countKo++;
                        continue;
                    }

                    if (!array_key_exists('carrierCodeMapping', $configMarketJson[$marketPlace])) {
                        $error = "carrierCodeMapping config is missing for marketplace $marketPlace";
                        $this->_log($this::LENGOWSYNC_LOG_TYPE_ERROR, $error);
                        break;
                    }

                    foreach ($shipmentCollection as $shipment) {
                        /** @var C2bi_Salesc2bi_Model_Order_Shipment_Track $tracking */
                        foreach ($shipment->getAllTracks() as $tracking) {
                            $carrierCode = $tracking->getCarrierCode();
                            $carrierCodeMapping = $configMarketJson[$marketPlace]['carrierCodeMapping'];
                            $trackNumber = $tracking->getNumber();

                            if (!array_key_exists($carrierCode, $carrierCodeMapping)) {
                                $error = "carrier code '" . $carrierCode .
                                         "' is unknown in carrierCodeMapping config for marketplace " . $marketPlace;
                                $this->_log($this::LENGOWSYNC_LOG_TYPE_ERROR, $error);
                                break;
                            } else {
                                $carrierMpCode = $carrierCodeMapping[$carrierCode];
                            }
                        }
                    }

                    if ($carrierMpCode && $trackNumber) {
                        $urlToCall = str_replace('${CARRIER_CODE}', $carrierMpCode, $urlToCall);
                        $urlToCall = str_replace('${TRACKING_NUMBER}', $trackNumber, $urlToCall);
                    } else {
                        if ($error) {
                            $data->setData('state', 'FAILURE')
                                 ->setData('error_msg', $error)
                                 ->setData('updated_at', Mage::getModel('core/date')->date())
                                 ->setData('url', $urlToCall)
                                 ->save();
                            $countKo++;
                            continue;
                        } else {
                            $error = "carrier code or tracking number is missing for order id $orderId";
                            $this->_log($this::LENGOWSYNC_LOG_TYPE_ERROR, $error);
                        }
                    }
                }

                if (!$error) {
                    $this->_log($this::LENGOWSYNC_LOG_TYPE_INFO, "Url called : " . $urlToCall);

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $urlToCall);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    $return = curl_exec($ch);

                    $returnXml = simplexml_load_string($return);

                    $newStatus = trim((string)$returnXml->status);

                    if ($newStatus && $newStatus != 'error') {
                        $data->setData('state', 'SUCCESS')
                             ->setData('error_msg', '');
                        $countOk++;
                    } else {
                        if (!$returnXml) {
                            $data->setData('state', 'FAILURE')
                                 ->setData('error_msg', 'No response from Web Service');
                        } elseif (!$newStatus) {
                            $data->setData('state', 'FAILURE')
                                 ->setData('error_msg', 'No response status returned');
                        } else {
                            $data->setData('state', 'FAILURE')
                                 ->setData('error_msg', 'Bad response from Web Service');
                        }

                        $countKo++;
                    }
                } else {
                    $data->setData('state', 'FAILURE')
                         ->setData('error_msg', $error);
                    $countKo++;
                }

                $data->setData('updated_at', Mage::getModel('core/date')->date())
                     ->setData('url', $urlToCall)
                     ->save();
            }
        }

        $calls = array(
            'nb_ok' => $countOk,
            'nb_ko' => $countKo
        );

        return $calls;
    }

    /**
     * Function addLog
     *
     * @param $type
     * @param $message
     */
    public function addLog($type, $message)
    {
        $this->_log($type, $message);
    }
}
