<?php

/**
 * Class Magoffice_Sellsecure_Model_Mysql4_Sell_Secure_Order_Collection
 *
 * @category     Magoffice
 * @package      Magoffice_Sellsecure
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Sellsecure_Model_Mysql4_Sell_Secure_Order_Collection
    extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Function _construct
     *
     */
    protected function _construct()
    {
        $this->_init('magoffice_sellsecure/sell_secure_order');
    }
}
