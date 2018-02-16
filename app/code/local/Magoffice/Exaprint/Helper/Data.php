<?php

/**
 * Class Magoffice_Exaprint_Helper_Data
 *
 * @category     Magoffice
 * @package      Magoffice_Exaprint
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Exaprint_Helper_Data extends Mage_Core_Helper_Abstract
{
    const EXAPRINT_PELLICULAGE_LABEL = 'PELLICULAGE';
    const EXAPRINT_PAPIER_LABEL = 'PAPIER';
    const EXAPRINT_RECTO_LABEL = 'RECTO';
    const EXAPRINT_RECTO_VERSO_LABEL = 'RECTO_VERSO';

    const ORDER_NEW = 'ORDER_NEW';
    const ORDER_TODO = 'ORDER_TODO';

    /**
     * Function getHtPriceCalculation
     *
     * @param      $exaPrice
     * @param null $chiliPrice
     * @param null $forcedMargin
     *
     * @return string
     */
    public function getHtPriceCalculation($exaPrice, $chiliPrice = null, $forcedMargin = null)
    {
        if ($forcedMargin) {
            $margin = $forcedMargin / 100;
        } else {
            $margin = Mage::getStoreConfig('web2print/configurator/basic_margin') / 100;
        }

        $marginMultiplicator = 1 / (1 - $margin);
        $htTotal             = ($exaPrice * $marginMultiplicator) + $chiliPrice;

        return $htTotal;
    }

    /**
     * Function getTtcPriceCalculation
     *
     * @param      $exaprice
     * @param null $chiliPrice
     * @param null $forcedMargin
     *
     * @return string
     */
    public function getTtcPriceCalculation($exaprice, $chiliPrice = null, $forcedMargin = null)
    {
        $tvaMultiplicator = 1.2;
        $htTotal          = $this->getHtPriceCalculation($exaprice, $chiliPrice, $forcedMargin);
        $ttcTotal         = $htTotal * $tvaMultiplicator;

        return $ttcTotal;
    }

    /**
     * Function getTtcTemplatePriceCalculation
     *
     * @param $chiliPrice
     *
     * @return string
     */
    public function getTtcTemplatePriceCalculation($chiliPrice)
    {
        $tvaMultiplicator = 1.2;
        $ttcTotal         = $chiliPrice * $tvaMultiplicator;

        return number_format($ttcTotal, 2);
    }

    /**
     * Function getTtcSuppplierDiscountPriceCalculation
     *
     * @param $discountPrice
     *
     * @return string
     */
    public function getTtcSuppplierDiscountPriceCalculation($discountPrice)
    {
        $tvaMultiplicator = 1.2;
        $ttcTotal         = $discountPrice * $tvaMultiplicator;

        return number_format($ttcTotal, 2);
    }

    /**
     * Function calculateDiscountPercent
     *
     * @param $ttcPrice
     * @param $ttcDiscountPrice
     *
     * @return bool|float
     */
    public function calculateDiscountPercent($ttcPrice, $ttcDiscountPrice)
    {
        if ($ttcDiscountPrice < $ttcPrice) {
            $percent = ((($ttcDiscountPrice * 100) / $ttcPrice) - 100) * (-1);
            $percent = round($percent);
            if ($percent >= 1) {
                return $percent;
            }
        }

        return false;
    }

    /**
     * Function getLowestPricesCategory
     *
     * @param $category
     *
     * @return array
     */
    public function getLowestPricesCategory($category)
    {
        /** @var Magoffice_Exaprint_Model_Mysql4_Product_Collection $exaprintCollection */
        $exaprintCollection = Mage::getResourceModel('magoffice_exaprint/product_collection')
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', array('eq' => 2))
            ->addAttributeToFilter('visibility', array('eq' => 1))
            ->addAttributeToFilter('sku', array('like' => "EXA%"))
            ->addAttributeToFilter('sell_flag', array('eq' => 1));

        $categoryChiliParams = json_decode(
            $category->getCriteresInjectes(),
            true
        );

        foreach ($categoryChiliParams as $key => $categoryChiliParam) {
            if (count($categoryChiliParam)) {
                $values = array();
                foreach ($categoryChiliParam as $value) {
                    $values[] = $value;
                }

                $exaprintCollection->addAttributeToFilter($key, array('in', $values));
            }
        }

        $minPrices = array(
            'htLowest'  => 0,
            'ttcLowest' => 0
        );

        if ($exaprintCollection->count()) {
            $minHtPrice = "";
            $discount   = false;

            foreach ($exaprintCollection as $exaprintProduct) {
                /** @var Magpleasure_Tierprices_Model_List $tierPriceModel */
                $tierPriceModel = Mage::getModel('tierprices/list');
                $tierPrices     = $tierPriceModel->getTierPrice($exaprintProduct->getId());

                if (count($tierPrices)) {
                    foreach ($tierPrices as $tierPrice) {

                        if ($minHtPrice == "" || $tierPrice['price_cost'] < $minHtPrice) {
                            $minHtPrice = $tierPrice['price_cost'];
                            $discount   = false;
                        }

                        if ($tierPrice['price_forced'] > 0 && $tierPrice['price_forced'] < $minHtPrice) {
                            $minHtPrice = $tierPrice['price_forced'];
                            $discount   = true;
                        }
                    }
                }
            }

            if (!$discount) {
                $minPrices = array(
                    'htLowest'  => number_format($this->getHtPriceCalculation($minHtPrice), 2, ",", ""),
                    'ttcLowest' => number_format($this->getTtcPriceCalculation($minHtPrice), 2, ",", "")
                );
            } else {
                $minPrices = array(
                    'htLowest'  => number_format($minHtPrice, 2, ",", ""),
                    'ttcLowest' => number_format(
                        $this->getTtcSuppplierDiscountPriceCalculation($minHtPrice), 2, ",",
                        ""
                    )
                );
            }

        }

        return $minPrices;
    }

    /**
     * Function getVersoAdditionnalPrice
     *
     * @param $category
     *
     * @return string
     */
    public function getVersoAdditionnalPrice($category)
    {
        $exaprintCollection = Mage::getResourceModel('magoffice_exaprint/product_collection')
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', array('eq' => 2))
            ->addAttributeToFilter('visibility', array('eq' => 1))
            ->addAttributeToFilter('sku', array('like' => "EXA%"))
            ->addAttributeToFilter('sell_flag', array('eq' => 1));

        //apply chili params
        $chiliParams = Mage::getSingleton('customer/session')->getData('chili_params');
        foreach ($chiliParams as $key => $chiliParam) {
            if ($key != 'exa_recto_verso') {
                if (count($chiliParam)) {
                    $values = array();
                    foreach ($chiliParam as $value) {
                        $values[] = $value;
                    }
                    $exaprintCollection->addAttributeToFilter($key, array('in', $values));
                }
            }
        }

        $minPrices          = "xx€ HT";
        $minRectoPrice      = null;
        $minRectoVersoPrice = null;

        $rectoDiscount = false;
        $versoDiscount = false;

        if ($exaprintCollection->count()) {
            foreach ($exaprintCollection as $exaprintProduct) {
                $tierPrices = Mage::getModel('tierprices/list')->getTierPrice($exaprintProduct->getId());
                if (count($tierPrices)) {
                    foreach ($tierPrices as $tierPrice) {
                        if ($exaprintProduct->getData('exa_recto_verso') == 'RECTO') {
                            if (!$minRectoPrice || $minRectoPrice > $tierPrice['price_cost']) {
                                $minRectoPrice = $tierPrice['price_cost'];
                                $rectoDiscount = false;
                            }
                            if ($tierPrice['price_forced'] > 0 && $minRectoPrice > $tierPrice['price_forced']) {
                                $minRectoPrice = $tierPrice['price_forced'];
                                $rectoDiscount = true;
                            }
                        } else {
                            if ($exaprintProduct->getData('exa_recto_verso') == 'RECTO_VERSO') {
                                if (!$minRectoVersoPrice || $minRectoVersoPrice > $tierPrice['price_cost']) {
                                    $minRectoVersoPrice = $tierPrice['price_cost'];
                                    $versoDiscount      = false;
                                }
                                if ($tierPrice['price_forced'] > 0
                                    && $minRectoVersoPrice > $tierPrice['price_forced']
                                ) {
                                    $minRectoVersoPrice = $tierPrice['price_forced'];
                                    $versoDiscount      = true;
                                }
                            }
                        }
                    }
                }
            }

            if (!$rectoDiscount) {
                $minRectoPrice = $this->getHtPriceCalculation($minRectoPrice);
            }

            if (!$versoDiscount) {
                $minRectoVersoPrice = $this->getHtPriceCalculation($minRectoVersoPrice);
            }

            $minPrices = $minRectoVersoPrice - $minRectoPrice;

            if ($minPrices > 0) {
                $minPrices = "+" . number_format($minPrices, 2) . "€ HT";
            } else {
                $minPrices = number_format($minPrices, 2) . "€ HT";
            }
        }

        return $minPrices;
    }

    /**
     * addCardConfiguration
     *
     * @param                                 $chiliId
     * @param                                 $chiliDocumentId
     * @param                                 $supplierId
     * @param                                 $supplierQty
     * @param C2bi_Salesc2bi_Model_Quote_Item $quoteItem
     *
     * @throws Exception
     */
    public function addCardConfiguration(
        $chiliId, $chiliDocumentId, $supplierId, $supplierQty, C2bi_Salesc2bi_Model_Quote_Item $quoteItem
    ) {
        $tierPrices = Mage::getModel('tierprices/list')->getTierPrice($supplierId);

        $price         = 0;
        $discountPrice = 0;
        foreach ($tierPrices as $tierPrice) {
            if ($tierPrice['price_qty'] == $supplierQty) {
                $price = $tierPrice['price_cost'];

                if ($tierPrice['price_forced'] > 0) {
                    $discountPrice = $tierPrice['price_forced'];
                }
            }
        }

        $resourceModel = Mage::getResourceModel('catalog/product');

        $forcedMargin = $resourceModel->getAttributeRawValue($supplierId, 'forced_mark_up', 1);

        if ($forcedMargin) {
            $margin = $forcedMargin;
        } else {
            $margin = Mage::getStoreConfig('web2print/configurator/basic_margin');
        }

        $templatePrice = $resourceModel->getAttributeRawValue($chiliId, 'price', 1);

        $color      = Mage::getSingleton('customer/session')->getData('color');
        $categoryId = Mage::getSingleton('customer/session')->getData('category_id');

        $priceHt  = $this->getHtPriceCalculation($price, $templatePrice, $forcedMargin);
        $priceTtc = $this->getTtcPriceCalculation($price, $templatePrice, $forcedMargin);

        $date = Mage::getModel('core/date')->date();

        $existsCardConfig
            = Mage::getModel('magoffice_exaprint/cardconfig')->getCardConfigsQuote($quoteItem->getItemId());

        if ($existsCardConfig->count()) {
            $cardconfig = $existsCardConfig->getFirstItem();
        } else {
            $cardconfig = Mage::getModel('magoffice_exaprint/cardconfig');
        }

        $cardconfig->setChiliDocumentId($chiliDocumentId);
        $cardconfig->setChiliPdfLink(Mage::getSingleton('customer/session')->getData('chili_bat_pdf_url'));
        $cardconfig->setChiliFinalPdfLink(Mage::getSingleton('customer/session')->getData('chili_final_pdf_url'));
        $cardconfig->setChiliPreviewUrl(
            Mage::getModel('web2print/api')
                ->getResourceImageUrl(
                    $chiliDocumentId,
                    Mage::helper('catalog/image')->getImageConversionProfile('product'), 'Documents', '1'
                )
        );
        $cardconfig->setChiliEditUrl(
            Mage::getUrl(
                'web2print/editor/load/',
                array(
                    'type'     => 'edit',
                    'id'       => $chiliId,
                    'chili_id' => $chiliDocumentId,
                    'color'    => $color,
                    'category' => $categoryId
                )
            )
        );

        $cardconfig->setChiliConfigLabels(Mage::getSingleton('customer/session')->getData('chili_finitions_labels'));
        $cardconfig->setTemplateEntityId($chiliId);
        $cardconfig->setTemplateSku($resourceModel->getAttributeRawValue($chiliId, 'sku', 1));
        $cardconfig->setTemplatePriceExclTax($templatePrice);
        $cardconfig->setTemplatePriceInclTax($this->getTtcTemplatePriceCalculation($templatePrice));
        $cardconfig->setTemplateTaxTotal(
            $cardconfig->getTemplatePriceInclTax() -
            $cardconfig->getTemplatePriceExclTax()
        );
        $cardconfig->setTemplateTaxRate(20);

        $cardconfig->setSupplierEntityId($supplierId);
        $cardconfig->setSupplierSku($resourceModel->getAttributeRawValue($supplierId, 'sku', 1));
        $cardconfig->setSupplierName('EXAPRINT');
        $cardconfig->setSupplierBuyingPrice($price);

        if ($discountPrice) {
            $supplierPrice = $discountPrice;

            $cardconfig->setSupplierMarginPrice($supplierPrice - $price);
            $cardconfig->setSupplierPriceExclTax($supplierPrice);
            $cardconfig->setSupplierPriceInclTax($this->getTtcSuppplierDiscountPriceCalculation($supplierPrice));
        } else {
            $supplierPrice = $this->getHtPriceCalculation($price, null, $forcedMargin);

            $cardconfig->setSupplierMarginPrice($supplierPrice - $price);
            $cardconfig->setSupplierPriceExclTax($supplierPrice);
            $cardconfig->setSupplierPriceInclTax($this->getTtcPriceCalculation($price, null, $forcedMargin));
        }

        $cardconfig->setSupplierMarginRate($margin);
        $cardconfig->setSupplierTaxTotal(
            $cardconfig->getSupplierPriceInclTax() -
            $cardconfig->getSupplierPriceExclTax()
        );
        $cardconfig->setSupplierTaxRate(20);
        $cardconfig->setSupplierQty($supplierQty);

        $cardconfig->setItemPriceExclTaxWithoutDiscount($priceHt);
        $cardconfig->setItemPriceInclTaxWithoutDiscount($priceTtc);

        if ($discountPrice) {
            $discountPriceHt  = $discountPrice + $templatePrice;
            $discountPriceTtc = $this->getTtcSuppplierDiscountPriceCalculation($discountPriceHt);

            $cardconfig->setItemPriceExclTaxWithDiscount($discountPriceHt);
            $cardconfig->setItemPriceInclTaxWithDiscount($discountPriceTtc);
            $cardconfig->setItemDiscountAmountWithTax($priceTtc - $discountPriceTtc);
        }

        $newRowNumber = $this->getNewConfigNumber($quoteItem->getQuote());

        $cardconfig->setRowNumber($newRowNumber);
        $cardconfig->setOrderNbSent(0);

        $cardconfig->setItemOrderStatus(self::ORDER_NEW);
        $cardconfig->setQuoteItemId($quoteItem->getItemId());
        $cardconfig->setCreatedAt($date);
        $cardconfig->save();

        Mage::unregister('last_card_config');
        Mage::register('last_card_config', $cardconfig);

        /** @var Mage_Customer_Model_Session $session */
        $session = Mage::getSingleton('customer/session');

        $currentQty = $session->getData('exa_qty_selected');

        if ($currentQty) {
            $session->unsetData('exa_qty_selected');
        }

        $quoteItem->setData('conf_id', $cardconfig->getData('conf_id'));
        
        $configurations = Mage::getModel('magoffice_exaprint/cardconfig')->getCardConfigsQuote($quoteItem->getItemId());

        $totalHt  = 0;
        $totalTtc = 0;
        if ($configurations->count()) {
            foreach ($configurations as $configuration) {
                if (
                    $configuration->getItemPriceExclTaxWithDiscount()
                    && $configuration->getItemPriceInclTaxWithDiscount()
                ) {
                    $totalHt += $configuration->getItemPriceExclTaxWithDiscount();
                    $totalTtc += $configuration->getItemPriceInclTaxWithDiscount();
                } else {
                    $totalHt += $configuration->getItemPriceExclTaxWithoutDiscount();
                    $totalTtc += $configuration->getItemPriceInclTaxWithoutDiscount();
                }
            }

            if ($totalHt && $totalTtc) {
                $quoteItem->setCustomPrice($totalTtc);
                $quoteItem->setOriginalCustomPrice($totalTtc);
                $quoteItem->getProduct()->setIsSuperMode(true);
                $quoteItem->save();
            }
        }
    }

    /**
     * Function getSupplierQties
     *
     * @param $supplierId
     *
     * @return mixed
     */
    public function getSupplierQties($supplierId)
    {
        return Mage::getModel('tierprices/list')->getTierPrice($supplierId);
    }

    /**
     * updateCardConfig
     *
     * @param $quoteItemId
     * @param $data
     *
     * @return bool
     */
    public function updateCardConfig($quoteItemId, $data)
    {
        $config = Mage::getModel('magoffice_exaprint/cardconfig')->getCardConfigsQuote($quoteItemId)->getFirstItem();
        if (!$config) {
            return false;
        }
        foreach ($data as $key => $value) {
            $config->setData($key, $value);
        }
        $config->save();

        return true;
    }

    /**
     * array_sort
     *
     * @param     $array
     * @param     $on
     * @param int $order
     *
     * @return array
     */
    public function array_sort($array, $on, $order = SORT_ASC)
    {
        $new_array      = array();
        $sortable_array = array();

        if (count($array) > 0) {
            foreach ($array as $k => $v) {
                if (is_array($v)) {
                    foreach ($v as $k2 => $v2) {
                        if ($k2 == $on) {
                            $sortable_array[$k] = $v2;
                        }
                    }
                } else {
                    $sortable_array[$k] = $v;
                }
            }

            switch ($order) {
                case SORT_ASC:
                    asort($sortable_array);
                    break;
                case SORT_DESC:
                    arsort($sortable_array);
                    break;
            }

            foreach ($sortable_array as $k => $v) {
                $new_array[$k] = $array[$k];
            }
        }

        return $new_array;
    }

    /**
     * Function getNewConfigNumber
     *
     * @param $quote
     *
     * @return int
     */
    public function getNewConfigNumber($quote)
    {
        $configurations = Mage::getModel('magoffice_exaprint/cardconfig')->getCardConfigsByQuote($quote);

        $max = 0;
        if ($configurations->count()) {
            foreach ($configurations as $configuration) {
                $rowNumber = $configuration->getRowNumber();

                if ($rowNumber > $max) {
                    $max = $rowNumber;
                }
            }
        }

        return $max + 1;
    }

    /**
     * reorderCardConfiguration
     *
     * @param Magoffice_Exaprint_Model_Cardconfig $oldConfig
     * @param                                     $quoteItem
     *
     * @throws Exception
     */
    public function reorderCardConfiguration(Magoffice_Exaprint_Model_Cardconfig $oldConfig, $quoteItem)
    {
        $supplierId      = $oldConfig->getData('supplier_entity_id');
        $supplierQty     = $oldConfig->getData('supplier_qty');
        $chiliId         = $oldConfig->getData('template_entity_id');
        $chiliDocumentId = $oldConfig->getData('chili_document_id');

        $tierPrices = Mage::getModel('tierprices/list')->getTierPrice($supplierId);

        $price = 0;
        foreach ($tierPrices as $tierPrice) {
            if ($tierPrice['price_qty'] == $supplierQty) {
                $price = $tierPrice['price_cost'];
            }
        }

        $resourceModel = Mage::getResourceModel('catalog/product');

        $forcedMargin = $resourceModel->getAttributeRawValue($supplierId, 'forced_mark_up', 1);

        if ($forcedMargin) {

            $margin = $forcedMargin;

        } else {

            $margin = Mage::getStoreConfig('web2print/configurator/basic_margin');

        }

        $templatePrice = $resourceModel->getAttributeRawValue($chiliId, 'price', 1);

        /** @var Magoffice_Exaprint_Helper_Data $helperPrice */
        $helperPrice = Mage::helper('magoffice_exaprint');

        $priceHt  = $helperPrice->getHtPriceCalculation($price, $templatePrice, $forcedMargin);
        $priceTtc = $helperPrice->getTtcPriceCalculation($price, $templatePrice, $forcedMargin);

        $supplierPrice = $helperPrice->getHtPriceCalculation($price, null, $forcedMargin);

        $date = Mage::getModel('core/date')->date();

        /** @var Magoffice_Exaprint_Model_Cardconfig $cardconfig */
        $cardconfig = Mage::getModel('magoffice_exaprint/cardconfig');
        $cardconfig->setChiliDocumentId($chiliDocumentId);
        $cardconfig->setChiliPdfLink($oldConfig->getData('chili_pdf_link'));
        $cardconfig->setChiliFinalPdfLink($oldConfig->getData('chili_final_pdf_link'));
        $cardconfig->setChiliPreviewUrl($oldConfig->getData('chili_preview_url'));
        $cardconfig->setChiliEditUrl($oldConfig->getData('chili_edit_url'));
        //$cardconfig->setChiliConfigLabels($oldConfig->getData('chili_finitions_labels'));
        $cardconfig->setTemplateEntityId($chiliId);
        $cardconfig->setTemplateSku($resourceModel->getAttributeRawValue($chiliId, 'sku', 1));
        $cardconfig->setTemplatePriceExclTax($templatePrice);
        $cardconfig->setTemplatePriceInclTax($helperPrice->getTtcTemplatePriceCalculation($templatePrice));
        $cardconfig->setTemplateTaxTotal(
            $cardconfig->getTemplatePriceInclTax() -
            $cardconfig->getTemplatePriceExclTax()
        );
        $cardconfig->setTemplateTaxRate(20);

        $cardconfig->setSupplierEntityId($supplierId);
        $cardconfig->setSupplierSku($resourceModel->getAttributeRawValue($supplierId, 'sku', 1));
        $cardconfig->setSupplierName('EXAPRINT');
        $cardconfig->setSupplierBuyingPrice($price);
        $cardconfig->setSupplierMarginPrice($supplierPrice - $price);
        $cardconfig->setSupplierPriceExclTax($supplierPrice);
        $cardconfig->setSupplierPriceInclTax(
            number_format($helperPrice->getTtcPriceCalculation($price, null, $forcedMargin)), 2
        );
        $cardconfig->setSupplierMarginRate($margin);
        $cardconfig->setSupplierTaxTotal(
            $cardconfig->getSupplierPriceInclTax() -
            $cardconfig->getSupplierPriceExclTax()
        );
        $cardconfig->setSupplierTaxRate(20);
        $cardconfig->setSupplierQty($supplierQty);

        $cardconfig->setItemPriceExclTaxWithoutDiscount($priceHt);
        $cardconfig->setItemPriceInclTaxWithoutDiscount($priceTtc);
//        $cardconfig->setItemPriceExclTaxWithDiscount($priceHt);
//        $cardconfig->setItemPriceInclTaxWithDiscount($priceHt);
//        $cardconfig->setItemDiscountAmountWithTax($priceHt);

        $cardconfig->setRowNumber(1);
        $cardconfig->setOrderNbSent(1);

        $cardconfig->setItemOrderStatus(self::ORDER_NEW);
        $cardconfig->setQuoteItemId($quoteItem->getItemId());
        $cardconfig->setCreatedAt($date);
        $cardconfig->setChiliConfigLabels($oldConfig->getChiliConfigLabels());
        $cardconfig->save();

        Mage::unregister('last_card_config');
        Mage::register('last_card_config', $cardconfig);

        $configurations = Mage::getModel('magoffice_exaprint/cardconfig')->getCardConfigsQuote($quoteItem->getItemId());

        $totalHt  = 0;
        $totalTtc = 0;
        if ($configurations->count()) {
            foreach ($configurations as $configuration) {
                $totalHt += $configuration->getItemPriceExclTaxWithoutDiscount();
                $totalTtc += $configuration->getItemPriceInclTaxWithoutDiscount();
            }

            if ($totalHt && $totalTtc) {
                $quoteItem->setCustomPrice($totalTtc);
                $quoteItem->setOriginalCustomPrice($totalTtc);
                $quoteItem->getProduct()->setIsSuperMode(true);
                $quoteItem->setQty(1);
                $quoteItem->save();
            }
        }
    }

    /**
     * transformWsResult
     *
     * @param stdClass $result
     *
     * @return array
     */
    public function transformWsResult(stdClass $result)
    {
        $wsResult = array();

        foreach ($result as $key => $data) {
            $wsResult[$key] = $data;
        }

        return $wsResult;
    }

    /**
     * getPdfById
     *
     * @param $pdfId
     *
     * @return mixed
     */
    public function getPdfById($pdfId)
    {
        /** @var Magoffice_Chili_Model_Pdf $requestedPdf */
        $requestedPdf = Mage::getModel('web2print/pdf')->load($pdfId);

        return $requestedPdf->getData('path');
    }

    /**
     * getPdfUrlById
     *
     * @param $pdfId
     *
     * @return mixed
     */
    public function getPdfUrlById($pdfId)
    {
        /** @var Magoffice_Chili_Model_Pdf $requestedPdf */
        $requestedPdf = Mage::getModel('web2print/pdf')->load($pdfId);

        return $requestedPdf->getData('pdf_url');
    }

}
