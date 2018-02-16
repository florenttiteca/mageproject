<?php

/**
 * Class Magoffice_Ogone_Model_Transactions_Tracking
 *
 * @category     Magoffice
 * @package      Magoffice_Ogone
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2015
 * @version      v1.0
 */
class Magoffice_Ogone_Model_Transactions_Tracking extends Mage_Core_Model_Abstract
{
    const TRANSACTIONS_TRACKING_LOG_FILE_NAME = 'unclosed_transactions_alert';
    const TRANSACTIONS_TRACKING_LOG_TYPE_INFO = '[INFO]';
    const TRANSACTIONS_TRACKING_LOG_TYPE_ERROR = '[ERROR]';
    const TRANSACTIONS_TRACKING_LOG_TYPE_WARNING = '[WARNING]';
    const TRANSACTIONS_TRACKING_LOG_TYPE_DEBUG = '[DEBUG]';

    /**
     * Function call
     *
     */
    public function call()
    {
        $this->_log($this::TRANSACTIONS_TRACKING_LOG_TYPE_INFO, 'Start of unclosed transactions alert generation.');

        $NbTransactions = $this->_unclosedTransactionsAlertGeneration();

        $this->_log($this::TRANSACTIONS_TRACKING_LOG_TYPE_INFO,
            $NbTransactions . ' unclosed transactions alert generated');

        $this->_log($this::TRANSACTIONS_TRACKING_LOG_TYPE_INFO, 'End of unclosed transactions alert generation.');
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
        return Mage::getBaseDir('log') . DS . date('Ymd') . '_' . $this::TRANSACTIONS_TRACKING_LOG_FILE_NAME . '.log';
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
     * Function _unclosedTransactionsAlertGeneration
     *
     * @return bool|int
     */
    protected function _unclosedTransactionsAlertGeneration()
    {
        /** @var Mage_Core_Model_Date $dateModel */
        $dateModel = Mage::getModel('core/date');
        $date = $dateModel->date('Ymd');

        $alertTimeLimitConfig = Mage::getStoreConfig('payment_services/transation_tracking/alert_time_limit');
        $fromDate = date("Y-m-d H:i:s", strtotime($date . "-$alertTimeLimitConfig hours"));

        if (!$fromDate) {
            $this->_log($this::TRANSACTIONS_TRACKING_LOG_TYPE_ERROR, 'An error occured when calculating the date');
            return false;
        }

        /** @var Mage_Sales_Model_Order_Payment_Transaction $transactionModel */
        $transactionModel = Mage::getModel('sales/order_payment_transaction');

        /** @var Mage_Sales_Model_Resource_Order_Payment_Transaction_Collection $unclosedTransactions */
        $unclosedTransactions = $transactionModel->getCollection()
                                                 ->addAttributeToSelect('*')
                                                 ->addAttributeToFilter('txn_type', array('eq' => 'refund'))
                                                 ->addAttributeToFilter('is_closed', 0)
                                                 ->addAttributeToFilter('created_at', array('from' => $fromDate));

        $this->_log($this::TRANSACTIONS_TRACKING_LOG_TYPE_INFO, 'SQL Query : ');
        $this->_log($this::TRANSACTIONS_TRACKING_LOG_TYPE_INFO, $unclosedTransactions->getSelect()->__toString());

        $unclosedTransactionsCount = $unclosedTransactions->count();

        if ($unclosedTransactionsCount) {
            $items = array();

            /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
            foreach ($unclosedTransactions as $transaction) {
                if (!$transaction->getId()) {
                    continue;
                }

                /** @var Mage_Sales_Model_Order $order */
                $order = $transaction->getOrder();
                $payment = $order->getPayment();

                if ($payment &&
                    $payment->getAdditionalInformation('status') != Netresearch_OPS_Model_Payment_Abstract::OPS_REFUNDED
                ) {
                    $items[] = array(
                        'order_id'       => $order->getIncrementId(),
                        'transaction_id' => $transaction->getTxnId(),
                        'amount'         => $order->getGrandTotal(),
                        'since'          => date("Ymd H:i", strtotime($transaction->getCreatedAt()))
                    );
                }
            }

            if (count($items)) {
                $templateId = 'unclosed_transactions_alert_email_template';

                /** @var Mage_Core_Model_Email_Template $templateMail */
                $templateMail = Mage::getModel('core/email_template');

                /** @var Mage_Core_Model_Email_Template $emailTemplate */
                $emailTemplate = $templateMail->loadDefault($templateId);

                $templateVariables = array();
                $templateVariables['transactions_count'] = $unclosedTransactionsCount;
                $templateVariables['alert_time_limit'] = $alertTimeLimitConfig;
                $templateVariables['items'] = $items;

                $emailTemplate->getProcessedTemplate($templateVariables);

                $noAnswer = Mage::getStoreConfig('system/email_configuration/to_not_answer_mail');
                $retourMail = Mage::getStoreConfig('pagec2bi_options/pagec2bi/retour_email');
                $supportWeb = Mage::getStoreConfig('system/email_configuration/web_support_mail');

                $emailTemplate->setSenderName('Top Office');
                $emailTemplate->setSenderEmail($noAnswer);

                $recipientMails = array(
                    $supportWeb,
                    $retourMail
                );

                try {
                    $emailTemplate->send($recipientMails, null, $templateVariables);
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
            }
        }

        return $unclosedTransactionsCount;
    }
}