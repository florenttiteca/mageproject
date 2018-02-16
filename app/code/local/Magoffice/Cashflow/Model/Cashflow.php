<?php

/**
 * Class Magoffice_Cashflow_Model_Cashflow
 *
 * @category     Magoffice
 * @package      Magoffice_Cashflow
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Cashflow_Model_Cashflow extends Mage_Core_Model_Abstract
{
    const CASHFLOW_LOG_FILE_NAME = 'generate_cashflow_control_table';
    const CASHFLOW_LOG_TYPE_INFO = '[INFO]';
    const CASHFLOW_LOG_TYPE_ERROR = '[ERROR]';
    const CASHFLOW_LOG_TYPE_WARNING = '[WARNING]';
    const CASHFLOW_LOG_TYPE_DEBUG = '[DEBUG]';

    const CASHFLOW_TABLE = 'V_DQM_I_WEB';
    const CASHFLOW_INVOICE_LABEL = 'VENTE';
    const CASHFLOW_REFUND_LABEL = 'RETOUR';
    const CASHFLOW_WEB_STORE_CODE = 99;


    /**
     * Function call
     *
     */
    public function call()
    {
        $this->_log($this::CASHFLOW_LOG_TYPE_INFO, 'Start of ' . self::CASHFLOW_TABLE . ' generation.');

        $nbDaysGenerated = $this->_generateCashflowControlTable();

        if ($nbDaysGenerated) {
            $this->_log($this::CASHFLOW_LOG_TYPE_INFO, 'Generate ' . $nbDaysGenerated . ' days.');
        }

        $this->_log($this::CASHFLOW_LOG_TYPE_INFO, 'End of Best ' . self::CASHFLOW_TABLE . ' generation.');
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
        return Mage::getBaseDir('log') . DS . $this::CASHFLOW_LOG_FILE_NAME . '_' . date('Ymd') . '.log';
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
     * Function _getTable
     *
     * @return string
     */
    protected function _getTable()
    {
        return self::CASHFLOW_TABLE;
    }

    /**
     * Function _generateCashflowControlTable
     *
     * @return bool|int
     */
    protected function _generateCashflowControlTable()
    {
        /** @var Mage_Core_Model_Date $dateModel */
        $dateModel = Mage::getModel('core/date');
        $date = $dateModel->date('Ymd');

        $ndDaysConfig = Mage::getStoreConfig('flux_cashflow/cashflow_control/nb_days_to_consolidated');
        $fromDate = date("Ymd", strtotime($date . "-$ndDaysConfig days"));

        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');

        if (!$fromDate) {
            $this->_log($this::CASHFLOW_LOG_TYPE_ERROR, 'An error occured when calculating the date');

            return false;
        }

        $writeConnection = $resource->getConnection('core_write');
        $writeConnection->query(
            'DELETE FROM ' . $this->_getTable() . ' WHERE id_dat > ' . $fromDate . ' OR id_dat = ' .
            $date
        );

        /** @var Mage_Sales_Model_Order_Invoice $invoiceModel */
        $invoiceModel = Mage::getModel('sales/order_invoice');

        /** @var Mage_Sales_Model_Entity_Order_Invoice_Collection $invoices */
        $invoices = $invoiceModel->getCollection()
                                 ->addAttributeToSelect('*')
                                 ->addAttributeToFilter('is_web', array('eq' => 1))
                                 ->addAttributeToFilter('created_at', array('from' => $fromDate));
        $this->_log($this::CASHFLOW_LOG_TYPE_INFO, $invoices->count() . ' relevant invoice(s)');

        $invoiceDatas = array();

        if ($invoices->count()) {
            /** @var Mage_Sales_Model_Order_Invoice $invoice */
            foreach ($invoices as $invoice) {

                $createdAt = Mage::getModel('core/date')->date('Ymd', strtotime($invoice->getCreatedAt()));

                /** @var Mage_Sales_Model_Order $order */
                $order = $invoice->getOrder();

                if (!$order->getId()) {
                    $this->_log(
                        $this::CASHFLOW_LOG_TYPE_ERROR,
                        'No order found for invoice with id #' . $invoice->getId()
                    );
                }

                $invoiceDatas[$createdAt]['nb_pieces_invoice'] += 1;
                $invoiceDatas[$createdAt]['qty_arts_invoice'] += $invoice->getTotalQty();
                $invoiceDatas[$createdAt]['ca_ttc_invoice'] += $invoice->getGrandTotal();
                $invoiceDatas[$createdAt]['ca_ht_invoice'] += $invoice->getSubtotal() + $invoice->getShippingAmount();
            }
        }

        foreach ($invoiceDatas as $invoiceCreatedAt => $invoiceData) {
            $data = array(
                'ID_DAT'     => $invoiceCreatedAt,
                'ID_MAG'     => self::CASHFLOW_WEB_STORE_CODE,
                'TYPE_PIECE' => self::CASHFLOW_INVOICE_LABEL,
                'I_NB_PIECE' => $invoiceData['nb_pieces_invoice'],
                'I_QTE_ART'  => $invoiceData['qty_arts_invoice'],
                'I_CA_TTC'   => $invoiceData['ca_ttc_invoice'],
                'I_CA_HT'    => $invoiceData['ca_ht_invoice'],
                'I_MRG'      => null
            );

            $writeConnection->insert($this->_getTable(), $data);
            $this->_log(
                $this::CASHFLOW_LOG_TYPE_INFO, 'Write invoices data line ' . $invoiceCreatedAt . ' to database done'
            );
        }

        /** @var Mage_Sales_Model_Order_Creditmemo $creditMemoModel */
        $creditMemoModel = Mage::getModel('sales/order_creditmemo');

        /** @var Mage_Sales_Model_Entity_Order_Creditmemo_Collection $refunds */
        $refunds = $creditMemoModel->getCollection()
                                   ->addAttributeToSelect('*')
                                   ->addAttributeToFilter('is_web', array('eq' => 1))
                                   ->addAttributeToFilter('created_at', array('from' => $fromDate));

        $this->_log($this::CASHFLOW_LOG_TYPE_INFO, $refunds->count() . ' relevant refund(s)');

        $refundDatas = array();

        if ($refunds->count()) {
            /** @var Mage_Sales_Model_Order_Creditmemo $refund */
            foreach ($refunds as $refund) {

                $nbItemsRefunded = 0;

                /** @var Innoexts_Warehouse_Model_Sales_Order_Creditmemo_Item $item */
                foreach ($refund->getAllItems() as $item) {
                    $nbItemsRefunded += $item->getQty();
                }

                $createdAt = Mage::getModel('core/date')->date('Ymd', strtotime($refund->getCreatedAt()));

                /** @var Mage_Sales_Model_Order $order */
                $order = $refund->getOrder();

                if (!$order->getId()) {
                    $this->_log(
                        $this::CASHFLOW_LOG_TYPE_ERROR,
                        'No order found for refund with id #' . $refund->getId()
                    );
                }

                $refundDatas[$createdAt]['nb_pieces_invoice'] += 1;
                $refundDatas[$createdAt]['qty_arts_invoice'] += $nbItemsRefunded;
                $refundDatas[$createdAt]['ca_ttc_invoice'] -= $refund->getGrandTotal();
                $refundDatas[$createdAt]['ca_ht_invoice'] -= $refund->getSubtotal() + $refund->getShippingAmount();
            }
        }

        foreach ($refundDatas as $refundCreatedAt => $refundData) {
            $data = array(
                'ID_DAT'     => $refundCreatedAt,
                'ID_MAG'     => self::CASHFLOW_WEB_STORE_CODE,
                'TYPE_PIECE' => self::CASHFLOW_REFUND_LABEL,
                'I_NB_PIECE' => $refundData['nb_pieces_invoice']*-1,
                'I_QTE_ART'  => $refundData['qty_arts_invoice']*-1,
                'I_CA_TTC'   => $refundData['ca_ttc_invoice'],
                'I_CA_HT'    => $refundData['ca_ht_invoice'],
                'I_MRG'      => null
            );

            $writeConnection->insert($this->_getTable(), $data);
            $this->_log(
                $this::CASHFLOW_LOG_TYPE_INFO, 'Write refunds data line ' . $refundCreatedAt . ' to database done'
            );
        }

        return $ndDaysConfig;
    }
}