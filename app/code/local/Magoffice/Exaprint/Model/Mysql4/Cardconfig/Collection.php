<?php

/**
 * Class Magoffice_Exaprint_Model_Mysql4_Cardconfig_Collection
 *
 * @category     Magoffice
 * @package      Magoffice_Exaprint
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class  Magoffice_Exaprint_Model_Mysql4_Cardconfig_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('magoffice_exaprint/cardconfig');
    }
}
