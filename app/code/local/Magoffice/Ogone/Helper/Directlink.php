<?php

require_once('Netresearch/OPS/Helper/Directlink.php');

/**
 * Class Magoffice_Ogone_Helper_DirectLink
 *
 * @category     Magoffice
 * @package      Magoffice_Ogone
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Ogone_Helper_DirectLink extends Netresearch_OPS_Helper_DirectLink
{

    /**
     * Process Direct Link Feedback to do: Capture, De-Capture and Refund
     *
     * @param Mage_Sales_Model_Order $order Order
     * @param array $params Request params
     *
     * @return void
     */
    public function processFeedback($order, $params)
    {
        parent::processFeedback($order, $params);

        switch ($params['STATUS']) {
            case Netresearch_OPS_Model_Payment_Abstract::OPS_INVALID :
            case Netresearch_OPS_Model_Payment_Abstract::OPS_REFUNDED :
                break;
            case Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_WAITING:
            case Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_UNCERTAIN_STATUS :
            case Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_REFUSED :
            case Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_DECLINED_ACQUIRER :
            case Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_PROCESSED_MERCHANT :
                /* send mail if credit memo not refunded (status != 8) */
                $this->_sendMailNotRefunded($order);
                break;
            default:
                break;
        }
    }

    /**
     * Function _sendMailNotRefunded
     *
     * @param Mage_Sales_Model_Order $order
     * @throws Exception
     */
    protected function _sendMailNotRefunded($order)
    {
        $templateId = 'aborted_credit_memo_email_template';

        /** @var Mage_Core_Model_Email_Template $templateMail */
        $templateMail = Mage::getModel('core/email_template');

        /** @var Mage_Core_Model_Email_Template $emailTemplate */
        $emailTemplate = $templateMail->loadDefault($templateId);

        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = $order->getPayment();

        try {
            $templateVariables = array();
            $templateVariables['order_id'] = $order->getId();
            $templateVariables['order_amount'] = number_format($payment->getAmountPaid(), 2);
            $templateVariables['order_date'] = $order->getCreatedAt();

            $emailTemplate->getProcessedTemplate($templateVariables);

            $noAnswer = Mage::getStoreConfig('system/email_configuration/to_not_answer_mail');
            $retourMail = Mage::getStoreConfig('pagec2bi_options/pagec2bi/retour_email');
            $generalMail = Mage::getStoreConfig('trans_email/ident_general/email');
            $supportWeb = Mage::getStoreConfig('system/email_configuration/web_support_mail');

            $emailTemplate->setSenderName('Top Office');
            $emailTemplate->setSenderEmail($noAnswer);

            $emailTemplate->addBcc($supportWeb);
            $emailTemplate->addBcc($retourMail);

            $recipientMails = array(
                $generalMail
            );

            $emailTemplate->send($recipientMails, null, $templateVariables);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}
