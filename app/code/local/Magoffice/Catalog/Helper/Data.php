<?php

/**
 * Class Magoffice_Catalog_Helper_Data
 *
 * @category     Magoffice
 * @package      Magoffice_Catalog
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Catalog_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Function getFormatHtPrice
     *
     * @param Mage_Catalog_Model_Product $product
     * @return bool|string
     */
    public function getFormatHtPrice($product)
    {
        if (!$product) {
            return false;
        }

        $taxHelper = Mage::helper('tax');
        $_finalPriceInclTax = $taxHelper->getPrice($product, $product->getFinalPrice(), true);

        $finalSpecialPriceExclTax =
            Mage::helper('catalogc2bi/productc2bi')->getSpecialPriceHt($product->getFinalPrice(), $product);

        $finalHtPrice = number_format($finalSpecialPriceExclTax, 2);

        $htPrice = explode('.', $finalHtPrice);

        if (count($htPrice)) {
            return $htPrice[0] . '<sup>€' . $htPrice[1] . '</sup><small>HT</small>';
        } else {
            return false;
        }
    }

    /**
     * Function getFormatTtcPrice
     *
     * @param Mage_Catalog_Model_Product $product
     * @return string
     */
    public function getFormatTtcPrice($product)
    {
        if (!$product) {
            return false;
        }

        /* @var $_store Rewrites_Supprindex_Model_Core_Store */
        $_store = Mage::app()->getStore();

        $finalPrice = $_store->roundPrice($product->getFinalPrice());

        return str_replace('.', ',', number_format($finalPrice, 2)) . '€ <small>TTC </small>';
    }

    /**
     * Function getFormatHtPromoPrice
     *
     * @param Mage_Catalog_Model_Product $product
     * @return string
     */
    public function getFormatHtPromoPrice($product)
    {
        if (!$product) {
            return false;
        }

        /** @var Mage_Tax_Helper_Data $taxHelper */
        $taxHelper = Mage::helper('tax');

        $regularPrice =
            str_replace('.', ',',
                number_format($taxHelper->getPrice($product, $product->getPrice(), false), 2)) .
            '€ <small>HT</small>';

        return $regularPrice;
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
}