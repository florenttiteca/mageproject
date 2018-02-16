<?php

/**
 * Class Magoffice_Sellsecure_Model_Sell_Secure_Order
 *
 * @category     Magoffice
 * @package      Magoffice_Sellsecure
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Sellsecure_Model_Mysql4_Sell_Secure_Order extends Mage_Core_Model_Mysql4_Abstract
{
    protected $_isPkAutoIncrement = false;

    /**
     * Constructor
     */
    protected function _construct()
    {
        $this->_init('magoffice_sellsecure/sell_secure_order', 'order_id');
    }
}
