<?php

/**
 * Class Magoffice_Sellsecure_Model_Evaluation_Flow
 *
 * @category     Magoffice
 * @package      Magoffice_Sellsecure
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Sellsecure_Model_Evaluation_Flow extends Mage_Core_Model_Abstract
{
    const SELLSECURE_LOG_FILE_NAME = 'sell_secure_evaluation_flow';
    const SELLSECURE_LOG_TYPE_INFO = '[INFO]';
    const SELLSECURE_LOG_TYPE_ERROR = '[ERROR]';
    const SELLSECURE_LOG_TYPE_WARNING = '[WARNING]';
    const SELLSECURE_LOG_TYPE_DEBUG = '[DEBUG]';

    protected $_mode;

    protected $_evaluationWsBaseUrl;
    protected $_siteIdCmc;
    protected $_siteIdFac;

    /**
     * Function _construct
     *
     */
    public function _construct()
    {
        $this->_mode = Mage::getStoreConfig('magoffice_sellsecure/flow_url_mapping/execute_mode_activation');

        $this->_evaluationWsBaseUrl = Mage::getStoreConfig(
            'magoffice_sellsecure/flow_url_mapping/url_service_recovery_scoring'
        );
        $this->_siteIdCmc           = Mage::getStoreConfig('magoffice_sellsecure/login/sideidcmc');
        $this->_siteIdFac           = Mage::getStoreConfig('magoffice_sellsecure/login/siteidfac');
        $this->_evaluationWsBaseUrl = str_replace('{siteidcmc}', $this->_siteIdCmc, $this->_evaluationWsBaseUrl);
        $this->_evaluationWsBaseUrl = str_replace('{siteidfac}', $this->_siteIdFac, $this->_evaluationWsBaseUrl);
    }

    /**
     * Function call
     *
     */
    public function call()
    {
        $this->_log($this::SELLSECURE_LOG_TYPE_INFO, 'Start Evaluation Flow');

        $ordersSendCnt = $this->_evaluationFlow();

        $this->_log($this::SELLSECURE_LOG_TYPE_INFO, "$ordersSendCnt order(s) will be send");

        $this->_log($this::SELLSECURE_LOG_TYPE_INFO, 'End Evaluation Flow');
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
     * Function _evaluationFlow
     *
     * @return bool|int
     * @throws Exception
     */
    protected function _evaluationFlow()
    {
        $ordersSendCnt = 0;

        if (!$this->_mode) {
            $this->_log($this::SELLSECURE_LOG_TYPE_WARNING, 'Execution is off');

            return false;
        }

        if (!$this->_evaluationWsBaseUrl) {
            $this->_log(
                $this::SELLSECURE_LOG_TYPE_ERROR,
                "The evaluation recovery scoring service url is not configured"
            );

            return false;
        }

        $date              = Mage::getModel('core/date')->date('Y-m-d H:i:s');
        $recoveryTimeLimit = Mage::getStoreConfig('magoffice_sellsecure/orders_eligibility/recovery_time_limit');
        $recoveryLimitDate = date("Y-m-d H:i:s", strtotime($date . "-$recoveryTimeLimit hours"));

        if (!$recoveryLimitDate) {
            $this->_log($this::SELLSECURE_LOG_TYPE_ERROR, 'An error occured when calculating the date');

            return false;
        }

        /** @var Magoffice_Sellsecure_Model_Sell_Secure_Order $sellSecureOrderModel */
        $sellSecureOrderModel = Mage::getModel('magoffice_sellsecure/sell_secure_order');

        /** @var Magoffice_Sellsecure_Model_Mysql4_Sell_Secure_Order_Collection $sellSecureOrders */
        $sellSecureOrders = $sellSecureOrderModel
            ->getCollection()
            ->addFieldToFilter(
                'state',
                array(
                    array('eq' => Magoffice_Sellsecure_Helper_Data::SCORING_WAITING_EVAL)
                )
            );

        /** @var Itroom_Score_Model_Rewrite_Sales_Order $orderModel */
        $orderModel = Mage::getModel('sales/order');
        if ($sellSecureOrders->count()) {
            $ordersLoopCnt = 0;

            $listOrderRefID = Array();
            foreach ($sellSecureOrders as $sellSecureOrder) {
                $ordersLoopCnt++;

                //check délai de récupération
                $creationDate = date("Y-m-d H:i:s", strtotime($sellSecureOrder->getCreatedAt()));
                if ($creationDate < $recoveryLimitDate) {
                    $message = "Order #" . $sellSecureOrder->getIncrementId() . " : Evaluation collects failed";

                    $this->_log($this::SELLSECURE_LOG_TYPE_ERROR, $message);

                    $sellSecureOrder->setState(Magoffice_Sellsecure_Helper_Data::SCORING_IGNORE);
                    $sellSecureOrder->setErrorMsg($message);
                    $sellSecureOrder->setUpdatedAt($date);
                    $sellSecureOrder->save();
                    continue;
                }

                /** @var Itroom_Score_Model_Rewrite_Sales_Order $order */
                $order = $orderModel->load($sellSecureOrder->getOrderId());

                if (!$order) {
                    $message = "The order " . $sellSecureOrder->getIncrementId() . " does not exists";

                    $this->_log($this::SELLSECURE_LOG_TYPE_ERROR, $message);

                    $sellSecureOrder->setState(Magoffice_Sellsecure_Helper_Data::SCORING_ERROR);
                    $sellSecureOrder->setErrorMsg($message);
                    $sellSecureOrder->setUpdatedAt($date);
                    $sellSecureOrder->save();
                    continue;
                }

                $listOrderRefID[] = $sellSecureOrder->getIncrementId();

                if ((($ordersLoopCnt % 25) == 0) || ($ordersLoopCnt == $sellSecureOrders->count())) {
                    if (count($listOrderRefID)) {
                        $result = $this->_callEvaluationWs($listOrderRefID);

                        if ($result && count($result->result)) {
                            $ordersSendCnt = $this->_updateSellSecureOrders($result, $ordersSendCnt);
                        }
                        $listOrderRefID = Array();
                    }
                }
                unset($order);
            }
        }

        return $ordersSendCnt;
    }

    /**
     * Function _callEvaluationWs
     *
     * @param $listOrderRefID
     *
     * @return mixed|null|SimpleXMLElement
     */
    protected function _callEvaluationWs($listOrderRefID)
    {
        $this->_log($this::SELLSECURE_LOG_TYPE_INFO, implode(",", $listOrderRefID));

        $evaluationWsUrl = str_replace('{RefID}', implode(",", $listOrderRefID), $this->_evaluationWsBaseUrl);

        $result = null;
        try {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $evaluationWsUrl);
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
     * _updateSellSecureOrders
     *
     * @param $result
     * @param $ordersSendCnt
     *
     * @return mixed
     */
    protected function _updateSellSecureOrders($result, $ordersSendCnt)
    {

        $date = Mage::getModel('core/date')->date('Y-m-d H:i:s');

        /** @var Magoffice_Sellsecure_Model_Sell_Secure_Order $sellSecureOrderModel */
        $sellSecureOrderModel = Mage::getModel('magoffice_sellsecure/sell_secure_order');

        /** @var Itroom_Score_Model_Rewrite_Sales_Order $orderModel */
        $orderModel = Mage::getModel('sales/order');

        foreach ($result->result as $res) {
            $sendOrder   = $sellSecureOrderModel->load((string)$res['refid'], 'increment_id');
            $stateResult = Magoffice_Sellsecure_Helper_Data::SCORING_WAITING_EVAL;

            $resultValue = strtoupper(trim($res[0]));

            $this->_log(
                $this::SELLSECURE_LOG_TYPE_INFO,
                "Order #" . $sendOrder->getIncrementId() . "Result value : " . $resultValue
            );

            $error    = (string)$res->erreurs;
            $anomalie = (string)$res->retour->anomalie;

            if ($error || $anomalie) {
                $stateResult = Magoffice_Sellsecure_Helper_Data::SCORING_ERROR;
                $this->_log(
                    $this::SELLSECURE_LOG_TYPE_WARNING,
                    "Order #" . (string)$res['refid'] . " : Evaluation request was not send: " .
                    $error
                );

                if ($error) {
                    $sendOrder->setErrorMsg($error);
                } else {
                    $sendOrder->setErrorMsg($anomalie);
                }

            } else {

                if ($resultValue == 'OK' || $resultValue == 'KO' || $resultValue == 'EN ATTENTE') {

                    if ($resultValue != 'EN ATTENTE') {
                        $stateResult = Magoffice_Sellsecure_Helper_Data::SCORING_DONE;
                    }

                    $sendOrder->setErrorMsg(null);
                    $sendOrder->setScoringEvalScore($res->eval);

                    $this->_log(
                        $this::SELLSECURE_LOG_TYPE_INFO,
                        "Order #" . $sendOrder->getIncrementId() . " : Evaluation was " . (string)$res->eval . "."
                    );

                    $order = $orderModel->load((string)$res['refid'], 'increment_id');

                    $this->_log(
                        $this::SELLSECURE_LOG_TYPE_INFO,
                        "Adding order history comment."
                    );

                    try {
                        $order->addStatusHistoryComment("[Sell Secure] Evaluation : " . (string)$res->eval . ".");

                        $this->_log(
                            $this::SELLSECURE_LOG_TYPE_INFO,
                            "Saving order."
                        );

                        $order->save();
                    } catch (Mage_Core_Exception $e) {
                        $this->_log($this::SELLSECURE_LOG_TYPE_ERROR, $e->getMessage());
                    }

                    $error = null;
                    $ordersSendCnt++;
                } else {
                    $error = (string)$res->erreurs;
                    $this->_log(
                        $this::SELLSECURE_LOG_TYPE_WARNING,
                        "Order #" . $sendOrder->getIncrementId() . " : Evaluation request was not send: " .
                        $error
                    );
                    $sendOrder->setErrorMsg($error);
                }
            }

            $sendOrder->setState($stateResult);
            $sendOrder->setCallCount($sendOrder->getCallCount() + 1);
            $sendOrder->setScoringEval($res->asXML());

            $sendOrder->setUpdatedAt($date);
            $sendOrder->save();
        }

        return $ordersSendCnt;
    }
}
