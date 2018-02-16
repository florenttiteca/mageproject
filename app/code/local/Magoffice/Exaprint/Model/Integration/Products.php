<?php

/**
 * Class Magoffice_Exaprint_Model_Integration_Products
 *
 * @category     Magoffice
 * @package      Magoffice_Exaprint
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Exaprint_Model_Integration_Products extends Mage_Core_Model_Abstract
{
    const EXAPRINT_PRODUCTS_LOG_FILE_NAME = 'exaprint_products_integration';
    const EXAPRINT_PRODUCTS_FILE_NAME_PRODUCTS = 'exaprint_options';
    const EXAPRINT_PRODUCTS_FILE_NAME_PRICES = 'exaprint_prices';
    const EXAPRINT_PRODUCTS_LOG_TYPE_INFO = '[INFO]';
    const EXAPRINT_PRODUCTS_LOG_TYPE_ERROR = '[ERROR]';
    const EXAPRINT_PRODUCTS_LOG_TYPE_WARNING = '[WARNING]';
    const EXAPRINT_PRODUCTS_LOG_TYPE_DEBUG = '[DEBUG]';

    protected $_debug = 0;

    protected $_delimiter = ',';
    protected $_enclosure = '"';

    /**
     * Function _getProductsFileName
     *
     * @return string
     */
    protected function _getProductsFileName()
    {
        $fileName = self::EXAPRINT_PRODUCTS_FILE_NAME_PRODUCTS . '.csv';

        return $this->_getFtpPath() . 'in' . DS . $fileName;
    }

    /**
     * Function _getPricesFileName
     *
     * @return string
     */
    protected function _getPricesFileName()
    {
        $fileName = self::EXAPRINT_PRODUCTS_FILE_NAME_PRICES . '.csv';

        return $this->_getFtpPath() . 'in' . DS . $fileName;
    }

    /**
     * Function _getFtpPath
     *
     * @return string
     * @throws Exception
     */
    protected function _getFtpPath()
    {
        return Afg_Path::getFTPPath();
    }

    /**
     * Function call
     *
     */
    public function call()
    {
        $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_INFO, 'Start of Exaprint products integration.');

        $this->_exaprintProductsIntegration();

        $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_INFO, 'End of Exaprint products integration.');
    }

    /**
     * Function _log
     *
     * @param $type
     * @param $message
     */
    protected function _log($type, $message)
    {
        $logFile = $this->_getLogFileName();

        if (!file_exists($logFile)) {
            file_put_contents($logFile, $this->_getLogTimestamp() . ' ' . $type . ' ' . $message . PHP_EOL);
            chmod($logFile, 0777);
        } else {
            file_put_contents($logFile, $this->_getLogTimestamp() . ' ' . $type . ' ' . $message . PHP_EOL,
                FILE_APPEND);
        }
    }

    /**
     * Function _getLogFileName
     *
     */
    protected function _getLogFileName()
    {
        return Mage::getBaseDir('log') . DS . self::EXAPRINT_PRODUCTS_LOG_FILE_NAME . '_' . date('Ymd') . '.log';
    }

    /**
     * Function _getLogTimestamp
     *
     * @return string
     */
    protected function _getLogTimestamp()
    {
        return date('Ymd') . ' / ' . date('His') . ' : ';
    }

    /**
     * Function _exaprintProductsIntegration
     *
     * @return bool
     */
    protected function _exaprintProductsIntegration()
    {
        $productsFile = $this->_getProductsFileName();
        $pricesFile = $this->_getPricesFileName();

        $excludedProducts = explode(';', Mage::getStoreConfig('exaprint/product_filter/excluded_products'));
        $excludedByIntegration = array();

        $matrixConfiguration = Mage::getStoreConfig('exaprint/product_filter/matrix_configuration');
        $matrixConfiguration = json_decode($matrixConfiguration, true);

        if (!$matrixConfiguration) {
            $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_ERROR, 'Invalid JSON format matrix configuration');
            return false;
        }

        $productsHandle = fopen($productsFile, 'r');
        $pricesHandle = fopen($pricesFile, 'r');
        if ($productsHandle || $pricesHandle) {
            $productsHeader = fgetcsv($productsHandle, 4096, $this->_delimiter);
            $pricesHeader = fgetcsv($pricesHandle, 4096, $this->_delimiter);

            if ($productsHeader === false || count($productsHeader) < 7) {
                fclose($productsHandle);
                $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_ERROR, "Products file does'nt have columns enough");
            }

            if ($pricesHeader === false || count($pricesHeader) < 19) {
                fclose($pricesHandle);
                $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_ERROR, "Prices file does'nt have columns enough");
            }


            $prices = array();
            while (($data = fgetcsv($pricesHandle, 4096, $this->_delimiter, $this->_enclosure)) !== false) {
                $prices[$data[2]] = $data[3];
            }
            fclose($pricesHandle);

            $productsCreationCnt = 0;
            $productsUpdatedCnt = 0;
            $productsExcludedCnt = 0;
            $productsDisabledCnt = 0;
            while (($data = fgetcsv($productsHandle, 4096, $this->_delimiter, $this->_enclosure)) !== false) {
                $products = array();
                $products[$data[2]][$data[4]] = $data;

                for ($inc = 0; $inc <= 1000; $inc++) {
                    if (($data = fgetcsv($productsHandle, 4096, $this->_delimiter, $this->_enclosure)) !== false) {
                        $products[$data[2]][$data[4]] = $data;
                    } else {
                        break;
                    }
                }

                $entityTypeId = Mage::getModel('catalog/product')
                                    ->getResource()
                                    ->getEntityType()
                                    ->getId();

                $exaprintLabel = null;
                $prdctModel = Mage::getModel('catalog/product');
                $attrLabel = "fournisseur";
                $fournisseurAttribute = $prdctModel->getResource()->getAttribute($attrLabel);

                if ($fournisseurAttribute->usesSource()) {
                    $exaprintLabel = $fournisseurAttribute->getSource()->getOptionId('EXAPRINT');
                }

                foreach ($products as $key => $productData) {
                    $familyId = current($productData)[0];
                    $articleFamilyId = current($productData)[1];

                    if (!$this->_isProductFamilyConfigured($familyId, $matrixConfiguration)) {
                        continue;
                    }

                    $productConfiguration = $matrixConfiguration[$familyId];

                    $attributesConfig = array();
                    if (array_key_exists('attributes', $productConfiguration)) {
                        $attributesConfig = $productConfiguration['attributes'];
                    }

                    if (!$attributesConfig) {
                        $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_ERROR,
                            "Attributes configuration not found for the family '$familyId'");
                        continue;
                    }

                    $attributeSetName = null;
                    if (array_key_exists('attributes_group', $productConfiguration)) {
                        $attributeSetName = $productConfiguration['attributes_group'];
                    }

                    if (!$attributeSetName) {
                        $attributeSetName = 'CARTE_VISITE_EXAPRINT';
                    }

                    /** @var Mage_Eav_Model_Entity_Attribute_Set $attributeSetModel */
                    $attributeSetModel = Mage::getModel('eav/entity_attribute_set');

                    $attributeSetId = $attributeSetModel
                        ->getCollection()
                        ->setEntityTypeFilter($entityTypeId)
                        ->addFieldToFilter('attribute_set_name', $attributeSetName)
                        ->getFirstItem()
                        ->getAttributeSetId();

                    if (!$attributeSetId) {
                        $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_ERROR,
                            "The attribute set '$attributeSetName' doesn't exists");
                        continue;
                    }

                    /** @var C2bi_Catalogc2bi_Model_Productc2bi $product */
                    $product = Mage::getModel('catalog/product');

                    $sku = 'EXA' . $key;
                    $code = current($productData)[3];

                    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

                    if (in_array($sku, $excludedByIntegration)) {
                        continue;
                    }

                    $productId = $product->getIdBySku($sku);
                    if (!$productId) {
                        try {
                            if (!$this->_isArtFamilyAccepted($articleFamilyId, $productConfiguration)) {
                                $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_INFO,
                                    "Product $sku was ignored, criteria '$articleFamilyId' is not included");
                                $excludedByIntegration[] = $sku;
                                $productsExcludedCnt++;
                                continue;
                            }

                            if (in_array($key, $excludedProducts)) {
                                $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_INFO,
                                    "The product $sku was ignored, ID is excluded");
                                $productsExcludedCnt++;
                                continue;
                            }

                            $exclValAttr = $this->_isAttributesNotIncluded($productData, $productConfiguration);

                            if ($exclValAttr) {
                                $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_INFO,
                                    "Product $sku was ignored, criteria '$exclValAttr' is not included");
                                $excludedByIntegration[] = $sku;
                                $productsExcludedCnt++;
                                continue;
                            }


                            $product->setWebsiteIds(array(1))
                                    ->setAttributeSetId($attributeSetId)
                                    ->setTypeId('simple')
                                    ->setCreatedAt(strtotime('now'))
                                    ->setSku($sku)
                                    ->setName($code)
                                    ->setWeight(1.000)
                                    ->setStatus(2)
                                    ->setTaxClassId(0)
                                    ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)
                                    ->setSellFlag(1)
                                    ->setFournisseur($exaprintLabel)
                                    ->setEan("0000000000000")
                                    ->setReferenceChezFournisseur($key)
                                    ->setFlagLivraison(0)
                                    ->setPrice(0);

                            $this->_setAttributesData($product, $productData, $productConfiguration);
                            $this->_setPricesData($product, $articleFamilyId, $prices);

                            if (!$this->_isDebug()) {
                                $product->save();
                            }
                            $productsCreationCnt++;

                            $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_INFO, "product $sku was created");
                        } catch (Exception $exception) {
                            $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_ERROR,
                                "There is an error during the product creation: " . $exception->getMessage());
                        }
                    } else {

                        try {
                            /** @var C2bi_Catalogc2bi_Model_Productc2bi $productModel */
                            $productModel = Mage::getModel('catalog/product');

                            $product = $productModel->load($productId);

                            if (!$product->getId()) {
                                continue;
                            }

                            $exclValAttr = $this->_isAttributesNotIncluded($productData, $productConfiguration);

                            if (!$this->_isArtFamilyAccepted($articleFamilyId, $productConfiguration)) {
                                $product->setData('sell_flag', 0);
                                $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_INFO,
                                    "Product $sku was ignored, criteria '$articleFamilyId' is not included");
                                $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_INFO,
                                    "Product $sku was disabled from sell");
                                $productsExcludedCnt++;
                                $productsDisabledCnt++;
                            } elseif (in_array($key, $excludedProducts)) {
                                $product->setData('sell_flag', 0);
                                $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_INFO,
                                    "Product $sku was disabled from sell");
                                $productsDisabledCnt++;
                            } elseif ($exclValAttr) {
                                $product->setData('sell_flag', 0);
                                $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_INFO,
                                    "Product $sku was ignored, criteria '$exclValAttr' is not included");
                                $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_INFO,
                                    "Product $sku was disabled from sell");
                                $productsExcludedCnt++;
                                $productsDisabledCnt++;
                            } else {
                                $product->setData('sell_flag', 1);
                                $this->_setAttributesData($product, $productData, $productConfiguration);
                                $this->_setPricesData($product, $articleFamilyId, $prices);
                            }

                            if (!$this->_isDebug()) {
                                $product->save();
                            }
                            $productsUpdatedCnt++;

                            $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_INFO, "product $sku was updated");
                        } catch (Exception $exception) {
                            $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_ERROR,
                                "There is an error during the product update: " . $exception->getMessage());
                        }
                    }

                    unset($productId);
                    unset($product);
                    unset($products[$key]);
                    unset($prices[$key]);
                }

                unset($products);
            }
            fclose($productsHandle);

            $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_INFO,
                "$productsExcludedCnt product(s) ignored ($productsDisabledCnt were disable from sell)");
            $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_INFO, "$productsCreationCnt product(s) created");
            $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_INFO, "$productsUpdatedCnt product(s) updated");
        } else {
            $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_ERROR, "Products or prices file not found");
            return false;
        }

        return true;
    }

    /**
     * Function setDebug
     *
     * @param $debug
     */
    public function setDebug($debug)
    {
        $this->_debug = $debug;
    }

    /**
     * Function _isDebug
     *
     * @return int
     */
    protected function _isDebug()
    {
        return $this->_debug;
    }

    /**
     * Function _isProductFamilyConfigured
     *
     * @param $familyId
     * @param $matrixConfiguration
     * @return bool
     */
    protected function _isProductFamilyConfigured($familyId, $matrixConfiguration)
    {
        if (array_key_exists($familyId, $matrixConfiguration)) {
            return true;
        } else {
            $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_ERROR,
                "The product family '$familyId' is not configured in the matrix");
            return false;
        }
    }

    /**
     * Function _isArtFamilyAccepted
     *
     * @param $articleFamilyId
     * @param $productConfiguration
     * @return bool
     */
    protected function _isArtFamilyAccepted($articleFamilyId, $productConfiguration)
    {
        if (array_key_exists('accepted_article_families', $productConfiguration)) {
            $acceptedFamilies = $productConfiguration['accepted_article_families'];
            if (in_array($articleFamilyId, $acceptedFamilies)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Function _setAttributesData
     *
     * @param $product
     * @param $productData
     * @param $productConfiguration
     */
    protected function _setAttributesData($product, $productData, $productConfiguration)
    {
        $optionsCodes = explode(';', Mage::getStoreConfig('exaprint/product_filter/options_codes'));
        $attributesConfig = $productConfiguration['attributes'];

        foreach ($attributesConfig as $key => $attributeConfig) {
            $attrIdentifier = null;
            if (array_key_exists('attribute', $attributeConfig)) {
                $attrIdentifier = $attributeConfig['attribute'];
            }

            if ($attrIdentifier) {
                if (!array_key_exists($key, $productData)) {
                    $defaultValue = null;
                    if (array_key_exists('default', $attributeConfig)) {
                        $defaultValue = $attributeConfig['default'];
                    }

                    if ($defaultValue) {
                        $product->setData($attrIdentifier, $defaultValue);
                    }
                } else {
                    $productAttributeData = $productData[$key];
                    if (array_key_exists('attribute', $attributeConfig)) {
                        $acceptValues = array();

                        if (array_key_exists('accept', $attributeConfig)) {
                            $acceptValues = $attributeConfig['accept'];
                        }

                        $acceptValuesLower = array_map('strtolower', $acceptValues);

                        $productAttributeVal = $productAttributeData[6];
                        if (in_array(strtolower($productAttributeVal), $acceptValuesLower) || !$acceptValuesLower) {
                            $product->setData($attrIdentifier, $productAttributeVal);
                        }
                    }
                }
            } else {
                $this->_log($this::EXAPRINT_PRODUCTS_LOG_TYPE_ERROR,
                    "There is no attribute identifier for the attribute code $key configured");
            }
        }

        $product->setData('exa_recto_verso', 'RECTO');
        foreach ($productData as $data) {
            $optionCode = $data[4];
            $label = $data[5];
            $value = $data[6];

            if (in_array($optionCode, $optionsCodes) || strpos(strtoupper($value), 'VERSO')
                || strpos(strtoupper($label), 'VERSO')
            ) {
                $product->setData('exa_recto_verso', 'RECTO_VERSO');
            }
        }
    }

    /**
     * Function _setPricesData
     *
     * @param $product
     * @param $articleFamilyId
     * @param $prices
     * @return bool
     */
    protected function _setPricesData($product, $articleFamilyId, $prices)
    {
        $family = $prices[$articleFamilyId];
        $familyLabel = $family;

        if ($familyLabel) {
            $product->setData('exa_product_family', $familyLabel);
            $position = strpos($familyLabel, 'cm');
            if ($position) {
                $size = explode('x', substr($familyLabel, 0, $position));
                if (count($size) >= 2) {
                    $width = trim($size[0]);
                    $height = trim($size[1]);
                    $product->setData('largeur_gnx', $width);
                    $product->setData('hauteur_gnx', $height);
                }
            }
        }

        return true;
    }

    /**
     * Function _isAttributesNotIncluded
     *
     * @param $productData
     * @param $productConfiguration
     * @return bool
     */
    protected function _isAttributesNotIncluded($productData, $productConfiguration)
    {
        $attributesConfig = $productConfiguration['attributes'];

        foreach ($attributesConfig as $key => $attributeConfig) {
            $attrIdentifier = null;
            if (array_key_exists('attribute', $attributeConfig)) {
                $attrIdentifier = $attributeConfig['attribute'];
            }

            if ($attrIdentifier) {
                if (array_key_exists($key, $productData)) {
                    $productAttributeData = $productData[$key];

                    if (array_key_exists('attribute', $attributeConfig)) {
                        $acceptValues = array();

                        if (array_key_exists('accept', $attributeConfig)) {
                            $acceptValues = $attributeConfig['accept'];
                        }

                        $acceptValuesLower = array_map('strtolower', $acceptValues);
                        $productAttributeVal = $productAttributeData[6];

                        if ($acceptValuesLower && !in_array(strtolower($productAttributeVal), $acceptValuesLower)) {
                            return $productAttributeVal;
                        }
                    }
                }
            }
        }

        return false;
    }
}
