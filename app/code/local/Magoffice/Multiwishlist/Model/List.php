<?php

/**
 * Class Magoffice_Multiwishlist_Model_List
 *
 * @category     Magoffice
 * @package      Magoffice_Multiwishlist
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Multiwishlist_Model_List extends Amasty_List_Model_List
{
    /**
     * Function addItem
     *
     * @param $productId
     * @param $customOptions
     * @return Mage_Core_Model_Abstract
     * @throws Exception
     */
    public function addItem($productId, $customOptions)
    {
        $item = Mage::getModel('amlist/item')
                    ->setProductId($productId)
                    ->setListId($this->getId())
                    ->setQty(1);
        $origin = "UNKNOWN";
        if ($customOptions) {
            foreach ($customOptions as $product) {
                $options = $product->getCustomOptions();
                foreach ($options as $option) {
                    if ($option->getProductId() == $productId && $option->getCode() == 'info_buyRequest') {
                        $v = unserialize($option->getValue());

                        $qty = isset($v['qty']) ? max(0.01, $v['qty']) : 1;
                        $origin = strtoupper($v['origin']);
                        $item->setQty($qty);

                        // to be able to compare request in future
                        $unusedVars = array('list', 'qty', 'list_next', 'related_product');
                        foreach ($unusedVars as $k) {
                            if (isset($v[$k])) {
                                unset($v[$k]);
                            }
                        }
                        $item->setBuyRequest(serialize($v));
                    }
                }
            }
        }

        /** @var MAge_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load($productId);

        $item->setDescr($product->getName());
        $item->setPricesWhenAdding($product->getFinalPrice());

        // check if we already have the same item in the list.
        // if yes - set it's id to the current item
        $id = $item->findDuplicate();
        if ($id) {
            $qty = $item->getQty();

            $item = Mage::getModel('amlist/item')->load($id);

            if ($item) {
                $item->setQty($qty);
                $item->setUpdatedAt(date('Y-m-d'));
                $item->save();
            }
        } else {
            $item->setCreatedAt(date('Y-m-d'));
            $item->setOrigin($origin);
            $item->save();
        }

        $this->setUpdatedAt(date('Y-m-d'));
        $this->save();

        return $item;
    }

    /**
     * Function getItems
     *
     * @return array|mixed
     */
    public function getItems()
    {
        $items = $this->getData('items');
        if (is_null($items)) {
            $collection = Mage::getResourceModel('amlist/item_collection')
                              ->addFieldToFilter('list_id', $this->getId())
                              ->setOrder('created_at', 'DESC')
                              ->load();

            $products = $this->_getProductsArray($collection);

            $items = array();
            foreach ($collection as $item) {
                if (isset($products[$item->getProductId()])) {
                    $item->setProduct($products[$item->getProductId()]);
                }
                $items[] = $item;
            }
            $this->setData('items', $items);
        }
        return $items;
    }
}