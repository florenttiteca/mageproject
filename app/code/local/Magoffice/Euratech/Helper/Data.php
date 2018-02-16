<?php

/**
 * Class Magoffice_Euratech_Helper_Data
 *
 * @category     Magoffice
 * @package      Magoffice_Euratech
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Euratech_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * sendEuratechEmail
     *
     * @param Itroom_Score_Model_Rewrite_Sales_Order $order
     *
     * @return $this
     */
    public function sendEuratechEmail(Itroom_Score_Model_Rewrite_Sales_Order $order)
    {
        $storeId = $order->getStore()->getId();

        /** @var C2bi_Customerc2bi_Model_Customer $customer */
        $customer = Mage::getModel("customer/customer")->load($order->getCustomerId());

        /** @var Mage_Core_Model_Date $dateModel */
        $dateModel = Mage::getModel('core/date');

        $currentTimestamp = $dateModel->timestamp(time());

        $currentDate = date('Y-m-d H:i:s', $currentTimestamp);
        $timestamp   = $dateModel->timestamp($currentDate);

        $currentDay   = date('Y-m-d', $currentTimestamp);
        $limit        = $currentDay . ' 10:00:00';
        $tenTimestamp = $dateModel->timestamp($limit);

        // after 10h00
        if ($timestamp > $tenTimestamp) {
            /** @var C2bi_Email_Model_Email_Template $emailTemplate */
            $emailTemplate = Mage::getModel('core/email_template')->loadByCode('Commande express après 10h');
        } else { // before 10h00
            /** @var C2bi_Email_Model_Email_Template $emailTemplate */
            $emailTemplate = Mage::getModel('core/email_template')->loadByCode('Commande express avant 10h');
        }

        $templateId = $emailTemplate->getId();

        /** @var C2bi_Email_Model_Email_Template_Mailer $mailer */
        $mailer = Mage::getModel('core/email_template_mailer');

        /** @var C2bi_Shops_Model_Shops $shop */
        $shop   = Mage::getModel('shops/shops')->load(36, 'code_id');
        $sendTo = explode(';', $shop->getData('email_colop_commande_web'));

        /** @var Mage_Core_Model_Email_Info $emailInfo */
        $emailInfo = Mage::getModel('core/email_info');
        $emailInfo->addTo($sendTo, $customer->getName());
        $mailer->addEmailInfo($emailInfo);

        // Set all required params and send emails
        $mailer->setSender(Mage::getStoreConfig('sales_email/shipment/identity', $storeId));
        $mailer->setStoreId($storeId);
        $mailer->setTemplateId($templateId);
        $mailer->setTemplateParams(
            array(
                'order'    => $order,
                'customer' => $customer
            )
        );
        $mailer->send();

        return $this;
    }
}
