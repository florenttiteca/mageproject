<?php

/**
 * Class Magoffice_Multiwishlist_Block_Index
 *
 * @category     Magoffice
 * @package      Magoffice_Multiwishlist
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Multiwishlist_Block_Index extends Amasty_List_Block_Index
{
    /**
     * Function _prepareLayout
     *
     */
    protected function _prepareLayout()
    {
        Mage_Core_Block_Template::_prepareLayout();

        $lists = Mage::getResourceModel('amlist/list_collection')
                     ->addCustomerFilter(Mage::getSingleton('customer/session')->getCustomerId())
                     ->setOrder('list_type', 'ASC')
                     ->setOrder('title', 'ASC')
                     ->load();

        $this->setLists($lists);

        if ($headBlock = $this->getLayout()->getBlock('head')) {
            $headBlock->setTitle($this->__('Mes listes d\'achats'));
        }
    }
}