<?php

/**
 * Class Magoffice_Multiwishlist_Helper_Data
 *
 * @category     Magoffice
 * @package      Magoffice_Multiwishlist
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Multiwishlist_Helper_Data extends Mage_Core_Helper_Data
{
    const ORIGIN_ADDING_FROM_SEARCH_PAGE = "SEARCH-LISTING";
    const ORIGIN_ADDING_FROM_CATEGORY_PAGE = "PRODUCT-LISTING";
    const ORIGIN_ADDING_FROM_PRODUCT_PAGE = "PRODUCT-PAGE";
    const ORIGIN_ADDING_FROM_WISHLIST_PAGE = "WISHLIST";
    const ORIGIN_ADDING_FROM_CART_PAGE = "BASKET";
    const ORIGIN_ADDING_UNKNOWN = "UNKNOWN";

    const LIST_TYPE_AUTO = "AUTO";
    const LIST_TYPE_MANUAL = "MANUAL";
    const LIST_AUTO_TECHNIC_TITLE = "MY_TOP_PRODUCT";
    const LIST_MANUAL_TECHNIC_TITLE = "CUSTOM#";

    const TOP_LISTE_DEFAULT_NAME = "Mes favoris";

    /**
     * Function getAddUrl
     *
     * @param $productId
     * @return string
     */
    public function getAddUrl($productId)
    {
        $url = '';
        if (Mage::getStoreConfig('amlist/general/active')) {
            $url = $this->_getUrl('amlist/list/addItem', array('product' => $productId));
        }

        return $url;
    }

    /**
     * Function getCreateUrl
     *
     * @param $productId
     * @return string
     */
    public function getCreateUrl($productId)
    {
        $url = '';
        if (Mage::getStoreConfig('amlist/general/active')) {
            $url = $this->_getUrl('multiwishlist/list/createList', array('product' => $productId));
        }

        return $url;
    }

    /**
     * Function getListTotalWithoutTax
     *
     * @param Amasty_List_Model_List $list
     * @return int
     */
    public function getListTotalWithoutTax($list)
    {
        $totalExcludingTax = 0;
        $items = $list->getItems();

        if (count($items)) {
            /** @var Amasty_List_Model_Item $item */
            foreach ($items as $item) {
                $product = $item->getProduct();

                if ($product) {
                    $finalPriceExcludingTax = Mage::helper('tax')->getPrice($product, $product->getFinalPrice(), false);
                    $finalSpecialPriceExclTax =
                        Mage::helper('catalogc2bi/productc2bi')->getSpecialPriceHt($product->getFinalPrice(), $product);

                    $totalExcludingTax += $finalSpecialPriceExclTax * $item->getQty();
                }
            }
        }

        return $totalExcludingTax;
    }

    /**
     * Function getListTotalIncludingTax
     *
     * @param Amasty_List_Model_List $list
     * @return int
     */
    public function getListTotalIncludingTax($list)
    {
        $totalIncludingTax = 0;
        $items = $list->getItems();

        if (count($items)) {
            /** @var Amasty_List_Model_Item $item */
            foreach ($items as $item) {
                $product = $item->getProduct();

                if ($product) {
                    $totalIncludingTax += $product->getFinalPrice() * $item->getQty();
                }
            }
        }

        return $totalIncludingTax;
    }

    /**
     * Function getItemPriceWithoutTax
     *
     * @param $item
     * @return int
     */
    public function getItemPriceWithoutTax($item)
    {
        $finalSpecialPriceExclTax = 0;
        $product = $item->getProduct();

        if ($product) {
            $finalPriceExcludingTax =
                Mage::helper('tax')->getPrice($product, $product->getFinalPrice(), false);
            $finalSpecialPriceExclTax = Mage::helper('catalogc2bi/productc2bi')
                                            ->getSpecialPriceHt($product->getFinalPrice(), $product);
        }

        return $finalSpecialPriceExclTax;
    }

    /**
     * Function getOrigin
     *
     * @param $controllerName
     * @param $actionName
     * @return string
     */
    public function getOrigin($controllerName, $actionName)
    {
        $origin = self::ORIGIN_ADDING_UNKNOWN;

        if (!$controllerName || !$actionName) {
            return $origin;
        }

        if ($controllerName === "product" && $actionName === "view") {
            $origin = self::ORIGIN_ADDING_FROM_PRODUCT_PAGE;
        } else if ($controllerName === "result" && $actionName === "index") {
            $origin = self::ORIGIN_ADDING_FROM_SEARCH_PAGE;
        } else if ($controllerName === "category" && $actionName === "view") {
            $origin = self::ORIGIN_ADDING_FROM_CATEGORY_PAGE;
        } else if ($controllerName === "rewrite" && $actionName === "list") {
            $origin = self::ORIGIN_ADDING_FROM_CATEGORY_PAGE;
        } else {
            $origin = self::ORIGIN_ADDING_UNKNOWN;
        }

        return $origin;
    }

    /**
     * Function getParentUrl
     *
     * @param Mage_Catalog_Model_Product $product
     * @param $arrayOfParentIds
     * @return string
     */
    public function getParentUrl($product, $arrayOfParentIds)
    {
        if (!$product) {
            return false;
        }

        /** @var Mage_Core_Model_Url_Rewrite $rewrite */
        $rewrite = Mage::getModel('core/url_rewrite');

        $params = array();
        $params['_current'] = false;

        $parentId = (count($arrayOfParentIds) > 0 ? $arrayOfParentIds[0] : null);
        $url = $product->getProductUrl();
        $idPath = 'product/' . $parentId;
        $rewrite->loadByIdPath($idPath);

        $parentUrl = Mage::getUrl($rewrite->getRequestPath(), $params);
        $url = ($parentUrl ? $parentUrl : $url);

        return $url;
    }

    /**
     * Function getTotalQty
     *
     * @param array() $items
     * @return int
     */
    public function getTotalQty($items)
    {
        $totalQty = 0;
        if (count($items)) {
            foreach ($items as $item) {
                $totalQty += $item->getQty();
            }
        }
        return $totalQty;
    }
}