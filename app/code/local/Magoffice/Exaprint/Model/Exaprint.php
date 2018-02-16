<?php

/**
 * Class Magoffice_Exaprint_Model_Exaprint
 *
 * @category     Magoffice
 * @package      Magoffice_Exaprint
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Exaprint_Model_Exaprint extends Varien_Object
{
    /** @var Magoffice_Exaprint_Model_Connect_Adapter_Soap _adapter */
    protected $_adapter;

    private $_responseCodes = array(
        0  => 'Internal error',
        -1 => 'Authentification error (invalid token)',
        -2 => 'Missing parameters',
        -3 => 'Invalid product reference',
        -4 => 'Invalid quantity for product',
        -5 => 'Bad format for product',
        -6 => 'Creation of address failed',
        -7 => 'Creation of order failed'
    );

    /**
     * Magoffice_Exaprint_Model_Exaprint constructor.
     */
    public function __construct()
    {
        $this->_adapter = Mage::getModel('magoffice_exaprint/connect_adapter_soap');
    }

    /**
     * Function createOrder
     *
     * @param $product
     * @param $qty
     * @param $format
     * @param $address
     * @param string $comment
     * @return mixed
     */
    public function createOrder($product, $qty, $format, $address, $comment = '')
    {
        $orderParameter = array();

        $orderParameter['reference'] = $product->getName();
        $orderParameter['productReference'] = $product->getSku();
        $orderParameter['quantity'] = $qty;

        $orderParameter['openedFormatLength'] = $format['openedFormatLength'];
        $orderParameter['openedFormatWidth'] = $format['openedFormatWidth'];
        $orderParameter['closedFormatLength'] = $format['closedFormatLength'];
        $orderParameter['closedFormatWidth'] = $format['closedFormatWidth'];

        $orderParameter['deliveryAddress'] = array();

        $street = $address->getStreet();
        $orderParameter['deliveryAddress']['line1'] = $street[0];
        $orderParameter['deliveryAddress']['line2'] = $street[0];
        $orderParameter['deliveryAddress']['line3'] = $street[0];
        $orderParameter['deliveryAddress']['zipCode'] = $address->getPostCode();
        $orderParameter['deliveryAddress']['city'] = $address->getCity();
        $orderParameter['deliveryAddress']['country'] = $address->getCountry();
//        $orderParameter['deliveryAddress']['digicode'] = $address;
//        $orderParameter['deliveryAddress']['comment'] = $address;
        $orderParameter['deliveryAddress']['mobile'] = $address->getCellular();
        $orderParameter['deliveryAddress']['mail'] = $address->getEmail();
        $orderParameter['deliveryAddress']['phone'] = $address->getTelephone();
        $orderParameter['deliveryAddress']['contactName'] = $address->getFirstName() . ' ' . $address->getLastName();

        $orderParameter['comment'] = $comment;
        $orderParameter['isExaprintReference'] = true;

        $result = $this->_adapter->wsCreateOrder($orderParameter);

        $this->_checkError($result);

        return $result;
    }

    /**
     * Function cancelOrder
     *
     * @param $orderId
     * @param $reason
     * @return mixed
     */
    public function cancelOrder($orderId, $reason)
    {
        $orderCancel = array(
            'orderId' => $orderId,
            'reason'  => $reason
        );

        $result = $this->_adapter->wsCancelOrder($orderCancel);

        $this->_checkError($result);

        return $result;
    }

    /**
     * Function orderStatus
     *
     * @param $orderList
     * @return mixed
     */
    public function orderStatus($orderList)
    {
        $result = $this->_adapter->wsOrderStatus($orderList);

        $this->_checkError($result);

        return $result;
    }

    /**
     * Function transferFile
     *
     * @param $file
     * @param $remoteFile
     * @param $orderList
     * @return bool
     */
    public function transferFile($file, $remoteFile, $orderList)
    {
        $result = $this->_adapter->wsTransferFile($file, $remoteFile, $orderList);

        $this->_checkError($result);

        return $result;
    }

    /**
     * Function _checkError
     *
     * @param $callResult
     */
    protected function _checkError($callResult)
    {
        if (array_key_exists('error', $callResult)) {
            $code = $callResult['code'];
            Mage::log($this->_responseCodes[$code]);
        }
    }
}
