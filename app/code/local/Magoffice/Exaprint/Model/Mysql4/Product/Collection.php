<?php

/**
 * Class Magoffice_Exaprint_Model_Mysql4_Product_Collection
 *
 * @category     Magoffice
 * @package      Magoffice_Exaprint
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Exaprint_Model_Mysql4_Product_Collection extends Mage_Catalog_Model_Resource_Product_Collection
{
    /**
     * Function isEnabledFlat
     *
     * @return bool
     */
    public function isEnabledFlat()
    {
        return false;
    }
}
