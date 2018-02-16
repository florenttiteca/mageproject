<?php

/**
 * Class Magoffice_Multiwishlist_Block_Edit
 *
 * @category     Magoffice
 * @package      Magoffice_Multiwishlist
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Multiwishlist_Block_Edit extends Amasty_List_Block_Edit
{
    /**
     * Function getTitle
     *
     * @return mixed|string
     */
    public function getTitle()
    {
        if ($title = $this->getData('title')) {
            return $title;
        }
        if ($this->getList()->getId()) {
            $title = Mage::helper('amlist')->__($this->getList()->getTitle());
        } else {
            $title = Mage::helper('amlist')->__('Mes achats fréquents');
        }
        return $title;
    }

    /**
     * Function _prepareLayout
     *
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $this->_list = Mage::registry('current_list');

        if ($headBlock = $this->getLayout()->getBlock('head')) {
            $headBlock->setTitle($this->getTitle());
        }
        if ($postedData = Mage::getSingleton('amlist/session')->getListFormData(true)) {
            $this->_event->setData($postedData);
        }

        $title = strlen($this->getTitle()) > 30 ? substr($this->getTitle(), 0, 30) . '...' : $this->getTitle();
        if ($breadcrumbs = $this->getLayout()->getBlock('breadcrumbs')) {
            $breadcrumbs->addCrumb('wishlist_detail',
                array('label' => $title, 'title' => $this->getTitle()));
        }
    }

}