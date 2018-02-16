<?php

/**
 * Class Lengow_Sync_Model_Quote
 *
 * @category     Lengow
 * @package      Lengow_Sync
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Lengow_Sync_Model_Quote extends C2bi_Salesc2bi_Model_Quote
{

    protected $_rowTotalLengow = array();

    /**
     * Add products from API to current quote
     *
     * @param SimpleXMLelement $products product list to be added
     * @param Lengow_Sync_Model_Marketplace $marketplace
     * @param String $inddLengowOrder
     * @param boolean $priceIncludeTax
     *
     * @return  Lengow_Sync_Model_Quote
     */
    public function addLengowProducts(SimpleXMLelement $products, Lengow_Sync_Model_Marketplace $marketplace,
                                      $inddLengowOrder, $priceIncludeTax = true)
    {
        $orderLineid = '';
        $first = true;
        foreach ($products as $productLine) {
            if ($first || empty($orderLineid) || $orderLineid != (string)$productLine->order_lineid) {
                $first = false;
                $orderLineid = (string)$productLine->order_lineid;
                // check whether the product is canceled
                if (!empty($productLine->status)) {
                    if ($marketplace->getStateLengow((string)$productLine->status) == 'canceled') {
                        Mage::helper('lensync')->log('product ' . $productLine->sku .
                                                     ' could not be added to cart - status: ' .
                                                     $marketplace->getStateLengow((string)$productLine->status),
                            $inddLengowOrder);
                        continue;
                    }
                }
                $product = $this->_findProduct($productLine);
                if ($product) {
                    // get unit price with tax
                    $price = (float)$productLine->price_unit;
                    // save total row Lengow for each product
                    $this->_rowTotalLengow[(string)$product->getId()] = $price * $productLine->quantity;
                    // if price not include tax -> get shipping cost without tax
                    if (!$priceIncludeTax) {
                        $basedOn =
                            Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_BASED_ON, $this->getStore());
                        $countryId = ($basedOn == 'shipping') ? $this->getShippingAddress()->getCountryId() :
                            $this->getBillingAddress()->getCountryId();
                        $taxCalculator = Mage::getModel('tax/calculation');
                        $taxRequest = new Varien_Object();
                        $taxRequest->setCountryId($countryId)
                                   ->setCustomerClassId($this->getCustomer()->getTaxClassId())
                                   ->setProductClassId($product->getTaxClassId());
                        $taxRate = $taxCalculator->getRate($taxRequest);
                        $tax = (float)$taxCalculator->calcTaxAmount($price, $taxRate, true);
                        $price = $price - $tax;
                    }
                    $product->setPrice($price);
                    $product->setFinalPrice($price);
                    //option "import with product's title from Lengow"
                    if (Mage::getStoreConfig('lensync/orders/title', $this->getStore())) {
                        $product->setName((string)$productLine->title);
                    }
                    // add item to quote
                    $quoteItem = Mage::getModel('lensync/quote_item')
                                     ->setProduct($product)
                                     ->setQty((int)$productLine->quantity)
                                     ->setConvertedPrice($price);
                    $this->addItem($quoteItem);

                    // substract qty ordered on stock
                    $productId = $quoteItem->getProductId();
                    if ($productId) {
                        // stock id for web order
                        $stockId = 1;

                        $stockItem = Mage::getModel('cataloginventory/stock_item')->setStockId($stockId);
                        $stockItem = $stockItem->loadByProduct($productId);

                        if (Mage::helper('catalogInventory')->isQty($stockItem->getTypeId())) {
                            if ($quoteItem->getStoreId()) {
                                $stockItem->setStoreId(1);
                            }
                            if ($stockItem->checkQty($quoteItem->getQty()) || Mage::app()->getStore()->isAdmin()) {
                                $stockItem->subtractQty($quoteItem->getQty());
                                $stockItem->save();
                            }
                        }
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Find product in Magento based on API data
     *
     * @param SimpleXMLelement $lengowProduct
     * @return bool|Mage_Core_Model_Abstract
     * @throws Exception
     */
    protected function _findProduct(SimpleXMLelement $lengowProduct)
    {
        $apiFields = array(
            'sku',
            'idLengow',
            'idMP',
            'ean',
        );
        $productField = strtolower((string)$lengowProduct->sku['field'][0]);
        $productModel = Mage::getModel('catalog/product');
        // search product foreach sku
        $ind = 0;
        $found = false;
        $product = false;
        $count = count($apiFields);
        while (!$found && $ind < $count) {
            // search with sku type field first
            $sku = (string)$lengowProduct->{$apiFields[$ind]};
            $ind++;
            if (empty($sku)) {
                continue;
            }
            // search by field if exists
            $attributeModel = Mage::getSingleton('eav/config')->getAttribute('catalog_product', $productField);
            if ($attributeModel->getAttributeId()) {
                $collection = Mage::getResourceModel('catalog/product_collection')
                                  ->setStoreId($this->getStore()->getStoreId())
                                  ->addAttributeToSelect($productField)
                                  ->addAttributeToFilter($productField, $sku)
                                  ->setPage(1, 1)
                                  ->getData();
                if (is_array($collection) && count($collection) > 0) {
                    $product = $productModel->load($collection[0]['entity_id']);
                }
            }
            // search by id or sku
            if (!$product || !$product->getId()) {
                if (preg_match('/^[0-9]*$/', $sku)) {
                    $product = $productModel->load((integer)$sku);
                } else {
                    $sku = str_replace('\_', '_', $sku);
                    $product = $productModel->load($productModel->getIdBySku($sku));
                }
            }
            if ($product && $product->getId()) {
                $found = true;
            }
        }
        if (!$found) {
            throw new Exception('product ' . (string)$lengowProduct->sku . ' could not be found.');
        } elseif ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            throw new Exception('product ' . (string)$lengowProduct->sku . ' is a parent product.');
        }
        return $product;
    }

    /**
     * Get row Total from Lengow
     *
     * @param string $productId product id
     *
     * @return string
     */
    public function getRowTotalLengow($productId)
    {
        return $this->_rowTotalLengow[$productId];
    }

}
