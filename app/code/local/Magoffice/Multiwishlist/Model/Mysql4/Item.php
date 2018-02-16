<?php

/**
 * Class Magoffice_Multiwishlist_Model_Mysql4_Item
 *
 * @category     Magoffice
 * @package      Magoffice_Multiwishlist
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Multiwishlist_Model_Mysql4_Item extends Amasty_List_Model_Mysql4_Item
{
    /**
     * Function findDuplicate
     *
     * @param $item
     * @return string
     */
    public function findDuplicate($item)
    {
        $select = $this->_getReadAdapter()->select()
                       ->from($this->getMainTable(), 'item_id')
                       ->where('list_id = ?', $item->getListId())
                       ->where('product_id = ?', $item->getProductId())
                       ->limit(1);
        $id = $this->_getReadAdapter()->fetchOne($select);
        return $id;
    }
}